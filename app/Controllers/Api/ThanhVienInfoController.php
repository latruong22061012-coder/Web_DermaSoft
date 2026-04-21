<?php
/**
 * ThanhVienInfoController - API Endpoints cho Thông tin Thành viên (Loyalty)
 * GET /api/thanh-vien - Danh sách thành viên
 * GET /api/thanh-vien/{id} - Chi tiết thành viên
 * POST /api/thanh-vien - Tạo thành viên mới
 * PUT /api/thanh-vien/{id} - Cập nhật thànhviên
 * PUT /api/thanh-vien/{id}/points - Cập nhật điểm thưởng
 * GET /api/thanh-vien/{id}/points - Lấy thông tin điểm & cấp
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;
use App\Models\ThanhVienInfo;
use App\Models\HangThanhVien;
use App\Models\BenhNhan;

class ThanhVienInfoController extends ApiController
{
    /**
     * GET /api/thanh-vien?page=1&limit=20
     * Lấy danh sách thành viên (pagination)
     */
    public function index(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $page = $this->getPage();
        $limit = $this->getLimit(20, 100);
        $offset = $this->getOffset($page, $limit);

        $members = ThanhVienInfo::all($limit, $offset);
        $total = ThanhVienInfo::count();

        $this->success([
            'data' => $members,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ], 'Lấy danh sách thành viên thành công');
    }

    /**
     * GET /api/thanh-vien/{id}
     * Lấy chi tiết thành viên
     */
    public function show(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        
        if (!$id) {
            $this->error('ID thành viên không được cung cấp', null, 400);
            return;
        }

        $member = ThanhVienInfo::getById((int)$id);
        
        if (!$member) {
            $this->notFound('Thành viên không tồn tại');
            return;
        }

        // Lấy thông tin cấp thành viên
        if (isset($member['MaHang'])) {
            $tier = HangThanhVien::getById($member['MaHang']);
            $member['tier_info'] = $tier;
        }

        $this->success($member, 'Lấy chi tiết thành viên thành công');
    }

    /**
     * GET /api/thanh-vien/{id}/by-patient
     * Lấy thông tin thành viên theo bệnh nhân
     */
    public function getByPatient(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $patientId = $this->getParam('patient_id');
        
        if (!$patientId) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra bệnh nhân tồn tại
        if (!BenhNhan::exists((int)$patientId)) {
            $this->notFound('Bệnh nhân không tồn tại');
            return;
        }

        $member = ThanhVienInfo::getByPatientId((int)$patientId);

        if (!$member) {
            // Tạo thành viên mới nếu chưa có
            $this->success(null, 'Bệnh nhân này chưa là thành viên');
            return;
        }

        // Lấy thông tin cấp thành viên
        if (isset($member['MaHang'])) {
            $tier = HangThanhVien::getById($member['MaHang']);
            $member['tier_info'] = $tier;
        }

        $this->success($member, 'Lấy thông tin thành viên thành công');
    }

    /**
     * POST /api/thanh-vien
     * Tạo thành viên mới
     * Yêu cầu: {ma_benhnhan}
     */
    public function create(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $data = $this->getJSON();

        // Validate
        $errors = $this->validate($data, [
            'ma_benhnhan' => 'required|numeric'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        // Kiểm tra bệnh nhân tồn tại
        if (!BenhNhan::exists((int)$data['ma_benhnhan'])) {
            $this->error('Bệnh nhân không tồn tại', null, 400);
            return;
        }

        // Kiểm tra đã là thành viên chưa
        $existing = ThanhVienInfo::getByPatientId((int)$data['ma_benhnhan']);
        if ($existing) {
            $this->error('Bệnh nhân này đã là thành viên', null, 400);
            return;
        }

        // Tạo thành viên mới (mặc định cấp Bronze)
        $memberId = ThanhVienInfo::create([
            'MaBenhNhan' => (int)$data['ma_benhnhan'],
            'MaHang' => 1,  // Bronze tier
            'DiemTichLuy' => 0
        ]);

        if (!$memberId) {
            $this->internalError('Không thể tạo thành viên');
            return;
        }

        $this->logAccess("Create member - ID: $memberId");

        $member = ThanhVienInfo::getById($memberId);
        $this->success($member, 'Tạo thành viên mới thành công', 201);
    }

    /**
     * PUT /api/thanh-vien/{id}
     * Cập nhật thông tin thành viên
     */
    public function update(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id) {
            $this->error('ID thành viên không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra thành viên tồn tại
        if (!ThanhVienInfo::exists((int)$id)) {
            $this->notFound('Thành viên không tồn tại');
            return;
        }

        // Cập nhật
        ThanhVienInfo::update((int)$id, $data);

        $this->logAccess("Update member - ID: $id");

        $member = ThanhVienInfo::getById((int)$id);
        $this->success($member, 'Cập nhật thành viên thành công');
    }

    /**
     * PUT /api/thanh-vien/{id}/points
     * Cập nhật điểm thưởng (Windows App gọi sau khi = thanh toán)
     * Yêu cầu: {points_add, reason?}
     */
    public function updatePoints(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id || !isset($data['points_add'])) {
            $this->error('ID thành viên hoặc điểm cần cộng không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra thành viên tồn tại
        if (!ThanhVienInfo::exists((int)$id)) {
            $this->notFound('Thành viên không tồn tại');
            return;
        }

        $member = ThanhVienInfo::getById((int)$id);
        $currentPoints = (int)$member['DiemTichLuy'];
        $pointsToAdd = (int)$data['points_add'];

        // Cộng điểm
        $newPoints = $currentPoints + $pointsToAdd;

        // Xác định hạng mới theo điểm (tra bảng HangThanhVien)
        $hangMoi = Database::fetchOne(
            "SELECT TOP 1 MaHang FROM HangThanhVien WHERE DiemToiThieu <= ? ORDER BY DiemToiThieu DESC",
            [$newPoints]
        );
        $maHang = $hangMoi ? (int)$hangMoi['MaHang'] : (int)$member['MaHang'];

        // Cập nhật
        ThanhVienInfo::update((int)$id, [
            'DiemTichLuy' => $newPoints,
            'MaHang' => $maHang
        ]);

        // Ghi log
        $this->logAccess("Update member points - ID: $id, Add: $pointsToAdd, New Total: $newPoints, Reason: " . ($data['reason'] ?? 'Service purchase'));

        $member = ThanhVienInfo::getById((int)$id);
        $this->success($member, 'Cập nhật điểm thưởng thành công');
    }

    /**
     * GET /api/thanh-vien/{id}/points
     * Lấy thông tin điểm & cấp thành viên
     */
    public function getPoints(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID thành viên không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra thành viên tồn tại
        if (!ThanhVienInfo::exists((int)$id)) {
            $this->notFound('Thành viên không tồn tại');
            return;
        }

        $member = ThanhVienInfo::getById((int)$id);
        
        // Lấy thông tin cấp
        $tier = HangThanhVien::getById($member['MaHang']);

        $response = [
            'member_id' => $member['MaBenhNhan'],
            'points' => (int)$member['DiemTichLuy'],
            'tier' => $tier,
            'registered_date' => $member['NgayTaoTaiKhoan']
        ];

        $this->success($response, 'Lấy thông tin điểm thành công');
    }

    /**
     * GET /api/thanh-vien/tiers
     * Lấy danh sách cấp thành viên
     */
    public function getTiers(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $tiers = HangThanhVien::all();
        
        $this->success($tiers, 'Lấy danh sách cấp thành viên thành công');
    }

    /**
     * GET /api/thanh-vien/top-points?limit=10
     * Lấy danh sách thành viên có nhiều điểm nhất
     */
    public function getTopPoints(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $limit = (int)($_GET['limit'] ?? 10);
        $limit = min($limit, 100);  // Max 100

        $members = ThanhVienInfo::getTopPoints($limit);
        
        $this->success($members, "Lấy top $limit thành viên theo điểm thành công");
    }

    /**
     * DELETE /api/thanh-vien/{id}
     * Xóa thành viên (admin only)
     */
    public function delete(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        // Chỉ admin có thể xóa
        if ($user['MaVaiTro'] != 1) {
            $this->forbidden('Chỉ admin mới có thể xóa thành viên');
            return;
        }

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID thành viên không được cung cấp', null, 400);
            return;
        }

        if (!ThanhVienInfo::exists((int)$id)) {
            $this->notFound('Thành viên không tồn tại');
            return;
        }

        ThanhVienInfo::delete((int)$id);

        $this->logAccess("Delete member - ID: $id");

        $this->success(null, 'Xóa thành viên thành công');
    }
}
