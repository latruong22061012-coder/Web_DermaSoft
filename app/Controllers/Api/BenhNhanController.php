<?php
/**
 * BenhNhanController - API Endpoints cho Bệnh nhân
 * GET /api/benhnhan - Danh sách bệnh nhân
 * GET /api/benhnhan/{id} - Chi tiết bệnh nhân
 * GET /api/benhnhan/search - Tìm kiếm bệnh nhân
 * POST /api/benhnhan - Đăng ký bệnh nhân mới
 * PUT /api/benhnhan/{id} - Cập nhật thông tin bệnh nhân
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Models\BenhNhan;

class BenhNhanController extends ApiController
{
    /**
     * GET /api/benhnhan?page=1&limit=20
     * Lấy danh sách bệnh nhân (pagination)
     */
    public function index(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $page = $this->getPage();
        $limit = $this->getLimit(20, 100);
        $offset = $this->getOffset($page, $limit);

        $patients = BenhNhan::all($limit, $offset);
        $total = BenhNhan::count();

        $this->success([
            'data' => $patients,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ], 'Lấy danh sách bệnh nhân thành công');
    }

    /**
     * GET /api/benhnhan/{id}
     * Lấy chi tiết bệnh nhân
     */
    public function show(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        
        if (!$id) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
            return;
        }

        $patient = BenhNhan::getById((int)$id);
        
        if (!$patient) {
            $this->notFound('Bệnh nhân không tồn tại');
            return;
        }

        $this->success($patient, 'Lấy chi tiết bệnh nhân thành công');
    }

    /**
     * GET /api/benhnhan/search?q=john
     * Tìm kiếm bệnh nhân theo tên hoặc số điện thoại
     */
    public function search(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $query = $_GET['q'] ?? '';

        if (strlen($query) < 2) {
            $this->error('Từ khóa tìm kiếm phải ít nhất 2 ký tự', null, 400);
            return;
        }

        $patients = [];

        // Tìm kiếm theo tên
        $byName = BenhNhan::getByName($query);
        if ($byName) {
            $patients = array_merge($patients, is_array($byName) ? $byName : [$byName]);
        }

        // Tìm kiếm theo số điện thoại
        $byPhone = BenhNhan::getByPhone($query);
        if ($byPhone) {
            $patients = array_merge($patients, is_array($byPhone) ? $byPhone : [$byPhone]);
        }

        // Loại bỏ trùng lặp
        $unique = [];
        $ids = [];
        foreach ($patients as $patient) {
            if (!in_array($patient['MaBenhNhan'], $ids)) {
                $unique[] = $patient;
                $ids[] = $patient['MaBenhNhan'];
            }
        }

        $this->success($unique, 'Tìm kiếm bệnh nhân thành công', 200);
    }

    /**
     * GET /api/benhnhan/phone/{phone}
     * Tìm bệnh nhân theo số điện thoại
     */
    public function getByPhone(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $phone = $this->getParam('phone');

        if (!$phone) {
            $this->error('Số điện thoại không được cung cấp', null, 400);
            return;
        }

        $patient = BenhNhan::getByPhone($phone);

        if (!$patient) {
            $this->notFound('Bệnh nhân với số điện thoại này không tồn tại');
            return;
        }

        $this->success($patient, 'Tìm bệnh nhân theo số điện thoại thành công');
    }

    /**
     * POST /api/benhnhan
     * Đăng ký bệnh nhân mới
     * Yêu cầu: {hoten, ngaysinh, gioitinh, diachi, sodienthoai, email?}
     */
    public function create(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $data = $this->getJSON();

        // Validate
        $errors = $this->validate($data, [
            'hoten' => 'required',
            'ngaysinh' => 'required',
            'sodienthoai' => 'required'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        // Kiểm tra số điện thoại đã tồn tại
        $existingPatient = BenhNhan::getByPhone($data['sodienthoai']);
        if ($existingPatient) {
            $this->error('Số điện thoại này đã được đăng ký', null, 400);
            return;
        }

        // Đăng ký bệnh nhân
        $patientId = BenhNhan::register([
            'HoTen' => $data['hoten'],
            'NgaySinh' => $data['ngaysinh'],
            'GioiTinh' => $data['gioitinh'] ?? 'Khác',
            'DiaChi' => $data['diachi'] ?? '',
            'SoDienThoai' => $data['sodienthoai'],
            'Email' => $data['email'] ?? ''
        ]);

        if (!$patientId) {
            $this->internalError('Không thể đăng ký bệnh nhân');
            return;
        }

        $this->logAccess("Register patient - ID: $patientId, Phone: {$data['sodienthoai']}");

        $patient = BenhNhan::getById($patientId);
        $this->success($patient, 'Đăng ký bệnh nhân thành công', 201);
    }

    /**
     * PUT /api/benhnhan/{id}
     * Cập nhật thông tin bệnh nhân
     * Yêu cầu: {hoten?, ngaysinh?, gioitinh?, diachi?, sodienthoai?, email?}
     */
    public function update(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra bệnh nhân tồn tại
        if (!BenhNhan::exists((int)$id)) {
            $this->notFound('Bệnh nhân không tồn tại');
            return;
        }

        // Nếu cập nhật số điện thoại, kiểm tra không trùng
        if (isset($data['sodienthoai'])) {
            $existingPatient = BenhNhan::getByPhone($data['sodienthoai']);
            if ($existingPatient && $existingPatient['MaBenhNhan'] != $id) {
                $this->error('Số điện thoại này đã được đăng ký bởi bệnh nhân khác', null, 400);
                return;
            }
        }

        // Cập nhật thông tin
        BenhNhan::updateInfo((int)$id, $data);

        $this->logAccess("Update patient info - ID: $id");

        $patient = BenhNhan::getById((int)$id);
        $this->success($patient, 'Cập nhật thông tin bệnh nhân thành công');
    }

    /**
     * GET /api/benhnhan/{id}/appointments
     * Lấy danh sách lịch hẹn của bệnh nhân
     */
    public function getAppointments(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
            return;
        }

        if (!BenhNhan::exists((int)$id)) {
            $this->notFound('Bệnh nhân không tồn tại');
            return;
        }

        // Gọi model LichHen
        $appointments = \App\Models\LichHen::getByPatientId((int)$id);
        
        $this->success($appointments, 'Lấy lịch hẹn của bệnh nhân thành công');
    }

    /**
     * GET /api/benhnhan/{id}/consultations
     * Lấy danh sách phiếu khám của bệnh nhân
     */
    public function getConsultations(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
            return;
        }

        if (!BenhNhan::exists((int)$id)) {
            $this->notFound('Bệnh nhân không tồn tại');
            return;
        }

        // Gọi model PhieuKham
        $consultations = \App\Models\PhieuKham::getByPatientId((int)$id);
        
        $this->success($consultations, 'Lấy phiếu khám của bệnh nhân thành công');
    }

    /**
     * GET /api/benhnhan/{id}/invoices
     * Lấy danh sách hóa đơn của bệnh nhân
     */
    public function getInvoices(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
            return;
        }

        if (!BenhNhan::exists((int)$id)) {
            $this->notFound('Bệnh nhân không tồn tại');
            return;
        }

        // Gọi model HoaDon
        $invoices = \App\Models\HoaDon::getByPatientId((int)$id);
        
        $this->success($invoices, 'Lấy hóa đơn của bệnh nhân thành công');
    }

    /**
     * DELETE /api/benhnhan/{id}
     * Xóa bệnh nhân (chỉ admin)
     */
    public function delete(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        // Chỉ admin có thể xóa
        if ($user['MaVaiTro'] != 1) {
            $this->forbidden('Chỉ admin mới có thể xóa bệnh nhân');
            return;
        }

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
            return;
        }

        if (!BenhNhan::exists((int)$id)) {
            $this->notFound('Bệnh nhân không tồn tại');
            return;
        }

        BenhNhan::delete((int)$id);

        $this->logAccess("Delete patient - ID: $id");

        $this->success(null, 'Xóa bệnh nhân thành công');
    }
}
