<?php
/**
 * HoaDonController - API Endpoints cho Hóa đơn
 * GET /api/hoadon - Danh sách hóa đơn
 * GET /api/hoadon/{id} - Chi tiết hóa đơn
 * GET /api/hoadon/patient/{id} - Hóa đơn của bệnh nhân
 * GET /api/hoadon/unpaid - Hóa đơn chưa thanh toán
 * GET /api/hoadon/paid - Hóa đơn đã thanh toán
 * POST /api/hoadon - Tạo hóa đơn
 * PUT /api/hoadon/{id}/payment - Cập nhật thanh toán
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Models\HoaDon;
use App\Models\BenhNhan;
use App\Models\PhieuKham;

class HoaDonController extends ApiController
{
    /**
     * GET /api/hoadon?page=1&limit=20
     * Lấy danh sách hóa đơn (pagination)
     */
    public function index(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $page = $this->getPage();
        $limit = $this->getLimit(20, 100);
        $offset = $this->getOffset($page, $limit);

        $invoices = HoaDon::all($limit, $offset);
        $total = HoaDon::count();

        $this->success([
            'data' => $invoices,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ], 'Lấy danh sách hóa đơn thành công');
    }

    /**
     * GET /api/hoadon/{id}
     * Lấy chi tiết hóa đơn
     */
    public function show(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        
        if (!$id) {
            $this->error('ID hóa đơn không được cung cấp', null, 400);
            return;
        }

        $invoice = HoaDon::getById((int)$id);
        
        if (!$invoice) {
            $this->notFound('Hóa đơn không tồn tại');
            return;
        }

        $this->success($invoice, 'Lấy chi tiết hóa đơn thành công');
    }

    /**
     * GET /api/hoadon/patient/{id}
     * Lấy hóa đơn của bệnh nhân
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

        $invoices = HoaDon::getByPatientId((int)$patientId);
        $this->success($invoices, 'Lấy hóa đơn của bệnh nhân thành công');
    }

    /**
     * GET /api/hoadon/unpaid?page=1
     * Lấy danh sách hóa đơn chưa thanh toán
     */
    public function getUnpaid(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $invoices = HoaDon::getUnpaidInvoices();
        
        $this->success($invoices, 'Lấy hóa đơn chưa thanh toán thành công');
    }

    /**
     * GET /api/hoadon/paid?page=1
     * Lấy danh sách hóa đơn đã thanh toán
     */
    public function getPaid(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $invoices = HoaDon::getPaidInvoices();
        
        $this->success($invoices, 'Lấy hóa đơn đã thanh toán thành công');
    }

    /**
     * POST /api/hoadon
     * Tạo hóa đơn từ phiếu khám
     * Yêu cầu: {ma_phieukham, ma_benhnhan, ma_bacsi, tongtien, ghichu?}
     */
    public function create(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $data = $this->getJSON();

        // Validate
        $errors = $this->validate($data, [
            'ma_phieukham' => 'required|numeric',
            'ma_benhnhan' => 'required|numeric',
            'ma_bacsi' => 'required|numeric',
            'tongtien' => 'required|numeric'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        // Kiểm tra phiếu khám tồn tại
        if (!PhieuKham::exists((int)$data['ma_phieukham'])) {
            $this->error('Phiếu khám không tồn tại', null, 400);
            return;
        }

        // Kiểm tra bệnh nhân tồn tại
        if (!BenhNhan::exists((int)$data['ma_benhnhan'])) {
            $this->error('Bệnh nhân không tồn tại', null, 400);
            return;
        }

        // Tạo hóa đơn
        $invoiceId = HoaDon::create([
            'MaPhieuKham' => (int)$data['ma_phieukham'],
            'MaBenhNhan' => (int)$data['ma_benhnhan'],
            'MaBacSi' => (int)$data['ma_bacsi'],
            'NgayTao' => date('Y-m-d H:i:s'),
            'TongTien' => (float)$data['tongtien'],
            'GhiChu' => $data['ghichu'] ?? '',
            'TrangThai' => 0  // 0 = chưa thanh toán
        ]);

        if (!$invoiceId) {
            $this->internalError('Không thể tạo hóa đơn');
            return;
        }

        $this->logAccess("Create invoice - ID: $invoiceId");

        $invoice = HoaDon::getById($invoiceId);
        $this->success($invoice, 'Tạo hóa đơn thành công', 201);
    }

    /**
     * PUT /api/hoadon/{id}/payment
     * Cập nhật thanh toán hóa đơn (Windows App gọi)
     * Yêu cầu: {sotienphat, phuongthuc?, ghichu?}
     */
    public function updatePayment(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $id = $this->getParam('id');
        $data = $this->getJSON();

        if (!$id || !isset($data['sotienphat'])) {
            $this->error('ID hóa đơn hoặc số tiền thanh toán không được cung cấp', null, 400);
            return;
        }

        // Kiểm tra hóa đơn tồn tại
        if (!HoaDon::exists((int)$id)) {
            $this->notFound('Hóa đơn không tồn tại');
            return;
        }

        $invoice = HoaDon::getById((int)$id);

        // Kiểm tra hóa đơn đã thanh toán chưa (chống cộng điểm trùng)
        if ((int)$invoice['TrangThai'] === 1) {
            $this->error('Hóa đơn này đã được thanh toán.', null, 400);
            return;
        }
        
        // Kiểm tra số tiền thanh toán
        $paidAmount = (float)$data['sotienphat'];
        $totalAmount = (float)$invoice['TongTien'];
        
        if ($paidAmount < 0) {
            $this->error('Số tiền thanh toán không hợp lệ', null, 400);
            return;
        }

        // Tính tiền thừa
        $change = $paidAmount - $totalAmount;

        // Đánh dấu hóa đơn là đã thanh toán
        HoaDon::updatePaymentStatus((int)$id, [
            'SoTienTra' => $paidAmount,
            'TienThua' => max(0, $change),
            'PhuongThuc' => $data['phuongthuc'] ?? 'Tiền mặt',
            'NgayThanhToan' => date('Y-m-d H:i:s')
        ]);

        // Trigger TRG_HoaDon_CapPhatDiem tự động cộng điểm tích lũy,
        // cập nhật SoLanKham, MaHang, và tạo ThanhVienInfo mới nếu cần
        // khi TrangThai chuyển từ 0→1

        $this->logAccess("Update invoice payment - ID: $id, Amount: $paidAmount");

        $invoice = HoaDon::getById((int)$id);
        $this->success($invoice, 'Cập nhật thanh toán thành công');
    }

    /**
     * GET /api/hoadon/revenue/total?from=2024-01-01&to=2024-12-31
     * Lấy tổng doanh thu
     */
    public function getTotalRevenue(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;

        // Lấy tổng doanh thu
        $totalRevenue = HoaDon::getTotalRevenue($from, $to);
        
        $this->success([
            'total_revenue' => $totalRevenue,
            'from' => $from,
            'to' => $to
        ], 'Lấy tổng doanh thu thành công');
    }

    /**
     * GET /api/hoadon/revenue/daily?date=2024-01-15
     * Lấy doanh thu theo ngày
     */
    public function getDailyRevenue(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $date = $_GET['date'] ?? date('Y-m-d');

        // Lấy doanh thu ngày
        $dailyRevenue = HoaDon::getDailyRevenue($date);
        
        $this->success([
            'date' => $date,
            'revenue' => $dailyRevenue
        ], 'Lấy doanh thu ngày thành công');
    }

    /**
     * DELETE /api/hoadon/{id}
     * Xóa hóa đơn (chủ yếu cho dữ liệu test)
     */
    public function delete(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        // Chỉ admin có thể xóa
        if ($user['MaVaiTro'] != 1) {
            $this->forbidden('Chỉ admin mới có thể xóa hóa đơn');
            return;
        }

        $id = $this->getParam('id');

        if (!$id) {
            $this->error('ID hóa đơn không được cung cấp', null, 400);
            return;
        }

        if (!HoaDon::exists((int)$id)) {
            $this->notFound('Hóa đơn không tồn tại');
            return;
        }

        HoaDon::delete((int)$id);

        $this->logAccess("Delete invoice - ID: $id");

        $this->success(null, 'Xóa hóa đơn thành công');
    }
}
