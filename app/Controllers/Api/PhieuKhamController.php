<?php
/**
 * PhieuKhamController - API Endpoints cho Phiếu khám
 * GET /api/phieukham - Danh sách phiếu khám
 * GET /api/phieukham/{id} - Chi tiết phiếu khám
 * GET /api/phieukham/patient/{id} - Phiếu khám của bệnh nhân
 * GET /api/phieukham/appointment/{id} - Phiếu khám từ lịch hẹn
 * POST /api/phieukham - Tạo phiếu khám
 * PUT /api/phieukham/{id} - Cập nhật phiếu khám (Windows App ghi kết quả)
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Models\PhieuKham;
use App\Models\LichHen;
use App\Models\BenhNhan;

class PhieuKhamController extends ApiController
{
    /**
     * GET /api/phieukham?page=1&limit=20
     * Lấy danh sách phiếu khám (pagination)
     */
    public function index(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $page = $this->getPage();
        $limit = $this->getLimit(20, 100);
        $offset = $this->getOffset($page, $limit);

        $records = PhieuKham::all($limit, $offset);
        $total = PhieuKham::count();

        $this->success([
            'data' => $records,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ], 'Lấy danh sách phiếu khám thành công');
    }

    /**
     * GET /api/phieukham/{id}
     * Lấy chi tiết phiếu khám
     */
    public function show(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        
        if (!$id) {
            $this->error('ID phiếu khám không được cung cấp', null, 400);
            return;
        }

        $record = PhieuKham::getById((int)$id);
        
        if (!$record) {
            $this->notFound('Phiếu khám không tồn tại');
            return;
        }

        // Lấy tên bác sĩ
        if (!empty($record['MaNguoiDung'])) {
            $doctor = \App\Core\Database::fetchOne(
                "SELECT HoTen FROM NguoiDung WHERE MaNguoiDung = ?",
                [(int)$record['MaNguoiDung']]
            );
            $record['TenBacSi'] = $doctor ? $doctor['HoTen'] : null;
        }

        // Lấy dịch vụ và thuốc liên quan
        $services = PhieuKham::getServices((int)$id);
        $medicines = PhieuKham::getMedicines((int)$id);

        $record['services'] = $services;
        $record['medicines'] = $medicines;

        // Lấy hóa đơn liên quan
        $hoaDon = \App\Core\Database::fetchOne(
            "SELECT MaHoaDon, TongTien, TongTienDichVu, TongThuoc, GiamGia,
                    TienKhachTra, TienThua, PhuongThucThanhToan, NgayThanhToan, TrangThai
             FROM HoaDon WHERE MaPhieuKham = ? AND IsDeleted = 0",
            [(int)$id]
        );
        $record['hoaDon'] = $hoaDon ?: null;

        $this->success($record, 'Lấy chi tiết phiếu khám thành công');
    }

    /**
     * GET /api/phieukham/patient/{id}
     * Lấy phiếu khám của bệnh nhân
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

        $records = PhieuKham::getByPatientId((int)$patientId);
        $this->success($records, 'Lấy phiếu khám của bệnh nhân thành công');
    }

    /**
     * GET /api/phieukham/appointment/{id}
     * Lấy phiếu khám từ lịch hẹn
     */
    public function getByAppointment(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $appointmentId = $this->getParam('appointment_id');
        
        if (!$appointmentId) {
            $this->error('ID lịch hẹn không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra lịch hẹn tồn tại
        if (!LichHen::exists((int)$appointmentId)) {
            $this->notFound('Lịch hẹn không tồn tại');
            return;
        }

        $record = PhieuKham::getByAppointmentId((int)$appointmentId);
        
        if (!$record) {
            $this->notFound('Phiếu khám cho lịch hẹn này không tồn tại');
            return;
        }

        // Lấy dịch vụ và thuốc liên quan
        $services = PhieuKham::getServices($record['MaPhieuKham']);
        $medicines = PhieuKham::getMedicines($record['MaPhieuKham']);

        $record['services'] = $services;
        $record['medicines'] = $medicines;

        $this->success($record, 'Lấy phiếu khám từ lịch hẹn thành công');
    }

    /**
     * POST /api/phieukham
     * Tạo phiếu khám mới (khi tiếp nhận bệnh nhân)
     * Yêu cầu: {ma_benhnhan, ma_lichhhen, ma_bacsi}
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
            'ma_lichhhen' => 'required|numeric',
            'ma_bacsi' => 'required|numeric'
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

        // Kiểm tra lịch hẹn tồn tại
        if (!LichHen::exists((int)$data['ma_lichhhen'])) {
            $this->error('Lịch hẹn không tồn tại', null, 400);
            return;
        }

        // Tạo phiếu khám
        $recordId = PhieuKham::create([
            'MaBenhNhan' => (int)$data['ma_benhnhan'],
            'MaLichHen' => (int)$data['ma_lichhhen'],
            'MaNguoiDung' => (int)$data['ma_bacsi'],
            'NgayKham' => date('Y-m-d H:i:s')
        ]);

        if (!$recordId) {
            $this->internalError('Không thể tạo phiếu khám');
            return;
        }

        $this->logAccess("Create medical record - ID: $recordId");

        $record = PhieuKham::getById($recordId);
        $this->success($record, 'Tạo phiếu khám thành công', 201);
    }

    /**
     * PUT /api/phieukham/{id}
     * Cập nhật phiếu khám (Windows App ghi kết quả khám)
     * Yêu cầu: {trieuchung?, chandoan?, ngayhen_taikham?}
     */
    public function update(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id) {
            $this->error('ID phiếu khám không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra phiếu khám tồn tại
        if (!PhieuKham::exists((int)$id)) {
            $this->notFound('Phiếu khám không tồn tại');
            return;
        }

        // Cập nhật kết quả khám
        PhieuKham::updateResults((int)$id, [
            'TrieuChung' => $data['trieuchung'] ?? null,
            'ChanDoan' => $data['chandoan'] ?? null,
            'NgayHen_TaiKham' => $data['ngayhen_taikham'] ?? null
        ]);

        $this->logAccess("Update consultation results - ID: $id");

        $record = PhieuKham::getById((int)$id);
        $this->success($record, 'Cập nhật kết quả khám thành công');
    }

    /**
     * PUT /api/phieukham/{id}/services
     * Thêm dịch vụ vào phiếu khám
     * Yêu cầu: {services: [{ma_dichvu, soluong, thanhtien}, ...]}
     */
    public function addServices(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id || !isset($data['services'])) {
            $this->error('ID phiếu khám hoặc danh sách dịch vụ không được cung cấp', null, 400);
            return;
        }

        if (!PhieuKham::exists((int)$id)) {
            $this->notFound('Phiếu khám không tồn tại');
            return;
        }

        // Thêm dịch vụ
        foreach ($data['services'] as $service) {
            PhieuKham::addService((int)$id, $service);
        }

        $this->logAccess("Add services to consultation - ID: $id, Count: " . count($data['services']));

        $record = PhieuKham::getById((int)$id);
        $this->success($record, 'Thêm dịch vụ thành công');
    }

    /**
     * GET /api/phieukham/{id}/services
     * Lấy danh sách dịch vụ của phiếu khám
     */
    public function getRecordServices(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID phiếu khám không được cung cấp', null, 400);
            return;
        }

        $services = PhieuKham::getServices((int)$id);
        $this->success($services, 'Lấy danh sách dịch vụ thành công');
    }

    /**
     * GET /api/phieukham/{id}/medicines
     * Lấy danh sách thuốc của phiếu khám
     */
    public function getRecordMedicines(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID phiếu khám không được cung cấp', null, 400);
            return;
        }

        $medicines = PhieuKham::getMedicines((int)$id);
        $this->success($medicines, 'Lấy danh sách thuốc thành công');
    }
}
