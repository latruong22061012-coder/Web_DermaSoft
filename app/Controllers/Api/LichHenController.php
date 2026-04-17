<?php
/**
 * LichHenController - API Endpoints cho Lịch hẹn
 * GET /api/lichhens - Danh sách lịch hẹn (pagination)
 * GET /api/lichhens/{id} - Chi tiết lịch hẹn
 * GET /api/lichhens/patient/{id} - Lịch hẹn của bệnh nhân
 * GET /api/lichhens/status/{status} - Lịch hẹn theo trạng thái
 * POST /api/lichhens - Tạo lịch hẹn
 * PUT /api/lichhens/{id} - Cập nhật lịch hẹn
 * PUT /api/lichhens/{id}/status - Cập nhật trạng thái (Win App gọi)
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;
use App\Models\LichHen;
use App\Models\BenhNhan;

class LichHenController extends ApiController
{
    /**
     * GET /api/lichhens?page=1&limit=20
     * Lấy danh sách lịch hẹn (pagination)
     */
    public function index(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $page = $this->getPage();
        $limit = $this->getLimit(20, 100);
        $offset = $this->getOffset($page, $limit);

        $appointments = LichHen::all($limit, $offset);
        $total = LichHen::count();

        $this->success([
            'data' => $appointments,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ], 'Lấy danh sách lịch hẹn thành công');
    }

    /**
     * GET /api/lichhens/{id}
     * Lấy chi tiết lịch hẹn
     */
    public function show(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        
        if (!$id) {
            $this->error('ID lịch hẹn không được cung cấp', null, 400);
        }

        $appointment = LichHen::getById((int)$id);
        
        if (!$appointment) {
            $this->notFound('Lịch hẹn không tồn tại');
        }

        $this->success($appointment, 'Lấy chi tiết lịch hẹn thành công');
    }

    /**
     * GET /api/lichhens/patient/{id}
     * Lấy lịch hẹn của bệnh nhân
     */
    public function getByPatient(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $patientId = $this->getParam('patient_id');
        
        if (!$patientId) {
            $this->error('ID bệnh nhân không được cung cấp', null, 400);
        }

        // Kiểm tra bệnh nhân tồn tại
        if (!BenhNhan::exists((int)$patientId)) {
            $this->notFound('Bệnh nhân không tồn tại');
        }

        $appointments = LichHen::getByPatientId((int)$patientId);
        $this->success($appointments, 'Lấy lịch hẹn của bệnh nhân thành công');
    }

    /**
     * GET /api/lichhens/status/{status}
     * Lấy lịch hẹn theo trạng thái
     * 0=pending, 1=confirmed, 2=completed, 3=cancelled
     */
    public function getByStatus(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $status = $this->getParam('status');
        
        if ($status === null || !in_array((int)$status, [0, 1, 2, 3])) {
            $this->error('Trạng thái không hợp lệ. Phải là 0-3', null, 400);
        }

        $appointments = LichHen::getByStatus((int)$status);
        $this->success($appointments, "Lấy lịch hẹn trạng thái $status thành công");
    }

    /**
     * GET /api/lichhens/pending
     * Lấy lịch hẹn chưa được xác nhận (Windows App sẽ poll)
     */
    public function getPending(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $appointments = LichHen::getPendingAppointments();
        $this->success($appointments, 'Lấy lịch hẹn chưa xác nhận thành công');
    }

    /**
     * POST /api/lichhens
     * Tạo lịch hẹn mới (từ website)
     * Yêu cầu: {ma_benhnhan, ma_bacsi, thoigian_hen, ghi_chu?}
     */
    public function create(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $data = $this->getJSON();

        // Validate
        $errors = $this->validate($data, [
            'ma_benhnhan' => 'required|numeric',
            'ma_bacsi' => 'required|numeric',
            'thoigian_hen' => 'required'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
        }

        // Kiểm tra bệnh nhân tồn tại
        if (!BenhNhan::exists((int)$data['ma_benhnhan'])) {
            $this->error('Bệnh nhân không tồn tại', null, 400);
        }

        // Tạo lịch hẹn
        $appointmentId = LichHen::createAppointment([
            'MaBenhNhan' => (int)$data['ma_benhnhan'],
            'MaBacSi' => (int)$data['ma_bacsi'],
            'ThoiGianHen' => $data['thoigian_hen'],
            'GhiChu' => $data['ghi_chu'] ?? ''
        ]);

        if (!$appointmentId) {
            $this->internalError('Không thể tạo lịch hẹn');
        }

        $this->logAccess("Create appointment - ID: $appointmentId");

        $appointment = LichHen::getById($appointmentId);
        $this->success($appointment, 'Tạo lịch hẹn thành công', 201);
    }

    /**
     * PUT /api/lichhens/{id}
     * Cập nhật lịch hẹn
     */
    public function update(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id) {
            $this->error('ID lịch hẹn không được cung cấp', null, 400);
        }

        // Kiểm tra lịch hẹn tồn tại
        if (!LichHen::exists((int)$id)) {
            $this->notFound('Lịch hẹn không tồn tại');
        }

        // Cập nhật
        $result = LichHen::updateAppointment((int)$id, $data);
        
        if ($result === 0) {
            $this->error('Không có thay đổi hoặc lỗi cập nhật', null, 400);
        }

        $this->logAccess("Update appointment - ID: $id");

        $appointment = LichHen::getById((int)$id);
        $this->success($appointment, 'Cập nhật lịch hẹn thành công');
    }

    /**
     * PUT /api/lichhens/{id}/status
     * Cập nhật trạng thái lịch hẹn (Windows App gọi)
     * Yêu cầu: {status}
     * 0=pending, 1=confirmed, 2=completed, 3=cancelled
     */
    public function updateStatus(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id || !isset($data['status'])) {
            $this->error('ID hoặc trạng thái không được cung cấp', null, 400);
        }

        // Kiểm tra trạng thái hợp lệ
        if (!in_array((int)$data['status'], [0, 1, 2, 3])) {
            $this->error('Trạng thái không hợp lệ. Phải là 0-3', null, 400);
        }

        // Kiểm tra lịch hẹn tồn tại
        if (!LichHen::exists((int)$id)) {
            $this->notFound('Lịch hẹn không tồn tại');
        }

        // Cập nhật trạng thái
        LichHen::updateStatus((int)$id, (int)$data['status']);

        // Nếu xác nhận (status=1) và có bác sĩ → tự tạo PhieuKham chờ khám
        if ((int)$data['status'] === 1) {
            $this->autoCreatePhieuKham((int)$id);
        }

        $this->logAccess("Update appointment status - ID: $id, Status: {$data['status']}");

        $appointment = LichHen::getById((int)$id);
        $this->success($appointment, 'Cập nhật trạng thái thành công');
    }

    /**
     * POST /api/lichhens/{id}/confirm
     * Xác nhận lịch hẹn (trạng thái = 1)
     * Windows App gọi
     */
    public function confirm(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID lịch hẹn không được cung cấp', null, 400);
        }

        if (!LichHen::exists((int)$id)) {
            $this->notFound('Lịch hẹn không tồn tại');
        }

        LichHen::confirm((int)$id);

        // Tự tạo PhieuKham chờ khám nếu có bác sĩ
        $this->autoCreatePhieuKham((int)$id);

        $this->logAccess("Confirm appointment - ID: $id");

        $appointment = LichHen::getById((int)$id);
        $this->success($appointment, 'Xác nhận lịch hẹn thành công');
    }

    /**
     * POST /api/lichhens/{id}/cancel
     * Hủy lịch hẹn (TrangThai = 3) — chỉ người dùng sở hữu lịch + đang chờ xác nhận mới được hủy
     */
    public function cancel(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id || !is_numeric($id)) {
            $this->error('ID lịch hẹn không hợp lệ', null, 400);
            return;
        }

        $appointment = LichHen::getById((int)$id);
        if (!$appointment) {
            $this->notFound('Lịch hẹn không tồn tại');
            return;
        }

        // Chỉ được hủy lịch đang chờ xác nhận (TrangThai = 0)
        if ((int)$appointment['TrangThai'] !== 0) {
            $this->error('Chỉ có thể hủy lịch hẹn đang chờ xác nhận.', null, 400);
            return;
        }

        // Kiểm tra quyền sở hữu: lịch phải thuộc về bệnh nhân của user hiện tại
        $phone    = $user['SoDienThoai'] ?? '';
        $benhNhan = $phone
            ? \App\Core\Database::fetchOne(
                "SELECT MaBenhNhan FROM BenhNhan WHERE SoDienThoai = ? AND IsDeleted = 0",
                [$phone]
              )
            : false;

        if ($benhNhan && (int)$appointment['MaBenhNhan'] !== (int)$benhNhan['MaBenhNhan']) {
            $this->forbidden('Bạn không có quyền hủy lịch hẹn này.');
            return;
        }

        try {
            LichHen::cancel((int)$id);
            $this->logAccess("Cancel appointment - ID: $id");
            $this->success(null, 'Hủy lịch hẹn thành công.');
        } catch (\Exception $e) {
            error_log('Lỗi hủy lịch hẹn #' . $id . ': ' . $e->getMessage());
            $this->internalError('Không thể hủy lịch hẹn. Vui lòng thử lại.');
        }
    }

    /**
     * Tự tạo PhieuKham (TrangThai=0) khi lịch hẹn được xác nhận và có bác sĩ
     * → Cập nhật KPI "Chờ khám hôm nay" trên Dashboard BacSi
     */
    private function autoCreatePhieuKham(int $maLichHen): void
    {
        try {
            $lichHen = LichHen::getById($maLichHen);
            if (!$lichHen) return;

            $maNguoiDung = $lichHen['MaNguoiDung'] ?? null;
            $maBenhNhan  = $lichHen['MaBenhNhan'] ?? null;

            // Chỉ tạo nếu lịch hẹn có bác sĩ được chỉ định
            if (!$maNguoiDung || !$maBenhNhan) return;

            // Kiểm tra đã có PhieuKham cho lịch hẹn này chưa (tránh trùng)
            $existing = Database::fetchOne(
                "SELECT MaPhieuKham FROM PhieuKham WHERE MaLichHen = ? AND IsDeleted = 0",
                [$maLichHen]
            );
            if ($existing) return;

            // Tạo PhieuKham chờ khám
            Database::execute(
                "INSERT INTO PhieuKham (MaBenhNhan, MaNguoiDung, MaLichHen, NgayKham, TrieuChung, ChanDoan, TrangThai, GhiChu, IsDeleted)
                 VALUES (?, ?, ?, ?, N'', N'', 0, ?, 0)",
                [
                    (int)$maBenhNhan,
                    (int)$maNguoiDung,
                    $maLichHen,
                    $lichHen['ThoiGianHen'],
                    $lichHen['GhiChu'] ?? '',
                ]
            );
        } catch (\Exception $e) {
            error_log('Lỗi tự tạo PhieuKham cho LichHen #' . $maLichHen . ': ' . $e->getMessage());
        }
    }
}
