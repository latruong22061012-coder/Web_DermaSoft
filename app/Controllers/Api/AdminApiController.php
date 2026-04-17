<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;

class AdminApiController extends ApiController
{
    /**
     * Kiểm tra quyền Admin
     */
    private function requireAdmin(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Chưa đăng nhập');
            exit;
        }

        if (!Auth::hasRole(1)) {
            $this->forbidden('Không có quyền truy cập');
            exit;
        }
    }

    // ═══════════════════════════════════════════════════════
    //  DASHBOARD STATS
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/admin/stats
     * Thống kê tổng quan cho Dashboard
     */
    public function stats(): void
    {
        $this->requireAdmin();

        // Tổng bệnh nhân
        $totalBN = Database::fetchOne(
            "SELECT COUNT(*) as total FROM BenhNhan WHERE IsDeleted = 0", []
        );

        // Tổng thành viên
        $totalTV = Database::fetchOne(
            "SELECT COUNT(*) as total FROM ThanhVienInfo", []
        );

        // Lịch hẹn chờ xác nhận hôm nay
        $lichHenHomNay = Database::fetchOne(
            "SELECT COUNT(*) as total FROM LichHen
             WHERE TrangThai IN (0, 1)
               AND CAST(ThoiGianHen AS DATE) = CAST(GETDATE() AS DATE)", []
        );

        // Điểm đánh giá trung bình
        $diemTB = Database::fetchOne(
            "SELECT AVG(CAST(DiemDanh AS FLOAT)) as avg_score FROM DanhGia", []
        );

        // Phân bố hạng thành viên
        $hangPhanBo = Database::fetchAll(
            "SELECT h.TenHang, h.MauHangHex, COUNT(tv.MaThanhVien) as soLuong
             FROM HangThanhVien h
             LEFT JOIN ThanhVienInfo tv ON h.MaHang = tv.MaHang
             GROUP BY h.MaHang, h.TenHang, h.MauHangHex, h.DiemToiThieu
             ORDER BY h.DiemToiThieu ASC", []
        );

        // Bệnh nhân mới 6 tháng gần nhất
        $bnMoi6Thang = Database::fetchAll(
            "SELECT FORMAT(nd.NgayTao, 'yyyy-MM') as thang, COUNT(*) as soLuong
             FROM NguoiDung nd
             WHERE nd.MaVaiTro = 4
               AND nd.NgayTao >= DATEADD(MONTH, -6, GETDATE())
             GROUP BY FORMAT(nd.NgayTao, 'yyyy-MM')
             ORDER BY thang ASC", []
        );

        // 5 đánh giá mới nhất
        $danhGiaMoi = Database::fetchAll(
            "SELECT TOP 5 dg.DiemDanh, dg.NhanXet, dg.NgayDanhGia,
                    bn.HoTen AS TenBenhNhan
             FROM DanhGia dg
             INNER JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
             ORDER BY dg.NgayDanhGia DESC", []
        );

        // Doanh thu tháng hiện tại
        $doanhThuThang = Database::fetchOne(
            "SELECT ISNULL(SUM(TongTien), 0) as total
             FROM HoaDon
             WHERE TrangThai = 1
               AND MONTH(NgayThanhToan) = MONTH(GETDATE())
               AND YEAR(NgayThanhToan) = YEAR(GETDATE())", []
        );

        $this->success([
            'tongBenhNhan'      => (int)($totalBN['total'] ?? 0),
            'tongThanhVien'     => (int)($totalTV['total'] ?? 0),
            'lichHenHomNay'     => (int)($lichHenHomNay['total'] ?? 0),
            'diemDanhGiaTB'     => round((float)($diemTB['avg_score'] ?? 0), 1),
            'doanhThuThang'     => (float)($doanhThuThang['total'] ?? 0),
            'hangPhanBo'        => $hangPhanBo ?: [],
            'bnMoi6Thang'       => $bnMoi6Thang ?: [],
            'danhGiaMoi'        => $danhGiaMoi ?: [],
        ], 'Thống kê tổng quan');
    }

    // ═══════════════════════════════════════════════════════
    //  QUẢN LÝ BỆNH NHÂN (NguoiDung MaVaiTro=4)
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/admin/benh-nhan
     */
    public function listBenhNhan(): void
    {
        $this->requireAdmin();

        $page   = $this->getPage();
        $limit  = $this->getLimit();
        $offset = $this->getOffset($page, $limit);
        $search = $_GET['q'] ?? '';

        $where = "WHERE bn.IsDeleted = 0";
        $params = [];

        if (!empty($search)) {
            $where .= " AND (bn.HoTen LIKE ? OR bn.SoDienThoai LIKE ? OR nd.Email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) as total FROM BenhNhan bn
             LEFT JOIN NguoiDung nd ON bn.SoDienThoai = nd.SoDienThoai AND nd.MaVaiTro = 4
             {$where}", $params
        );
        $total = (int)($countRow['total'] ?? 0);

        $list = Database::fetchAll(
            "SELECT bn.MaBenhNhan, bn.HoTen, bn.SoDienThoai, bn.NgaySinh, 
                    bn.GioiTinh, bn.TienSuBenhLy,
                    nd.MaNguoiDung, nd.Email, nd.TrangThaiTK, nd.NgayTao, nd.AnhDaiDien,
                    tv.MaThanhVien, tv.DiemTichLuy,
                    h.TenHang, h.MauHangHex
             FROM BenhNhan bn
             LEFT JOIN NguoiDung nd ON bn.SoDienThoai = nd.SoDienThoai AND nd.MaVaiTro = 4
             LEFT JOIN ThanhVienInfo tv ON bn.MaBenhNhan = tv.MaBenhNhan
             LEFT JOIN HangThanhVien h ON tv.MaHang = h.MaHang
             {$where}
             ORDER BY bn.MaBenhNhan DESC
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
            array_merge($params, [$offset, $limit])
        );

        $this->success([
            'list'       => $list ?: [],
            'pagination' => [
                'page'      => $page,
                'limit'     => $limit,
                'total'     => $total,
                'totalPages' => (int)ceil($total / $limit),
            ]
        ], 'Danh sách bệnh nhân');
    }

    /**
     * GET /api/admin/benh-nhan/{id}
     */
    public function showBenhNhan(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');

        $user = Database::fetchOne(
            "SELECT nd.*, bn.MaBenhNhan, bn.NgaySinh, bn.GioiTinh, bn.TienSuBenhLy,
                    tv.MaThanhVien, tv.DiemTichLuy, tv.SoLanKham, tv.TyLeHaiLong,
                    h.TenHang, h.MauHangHex
             FROM NguoiDung nd
             LEFT JOIN BenhNhan bn ON nd.SoDienThoai = bn.SoDienThoai AND bn.IsDeleted = 0
             LEFT JOIN ThanhVienInfo tv ON bn.MaBenhNhan = tv.MaBenhNhan
             LEFT JOIN HangThanhVien h ON tv.MaHang = h.MaHang
             WHERE nd.MaNguoiDung = ? AND nd.MaVaiTro = 4", [$id]
        );

        if (!$user) {
            $this->notFound('Không tìm thấy bệnh nhân');
            return;
        }

        // Lịch sử lịch hẹn
        $lichHen = [];
        if ($user['MaBenhNhan']) {
            $lichHen = Database::fetchAll(
                "SELECT TOP 10 lh.*, nd2.HoTen AS TenBacSi
                 FROM LichHen lh
                 LEFT JOIN NguoiDung nd2 ON lh.MaNguoiDung = nd2.MaNguoiDung
                 WHERE lh.MaBenhNhan = ?
                 ORDER BY lh.ThoiGianHen DESC", [$user['MaBenhNhan']]
            ) ?: [];
        }

        // Hóa đơn (qua PhieuKham)
        $hoaDon = [];
        if ($user['MaBenhNhan']) {
            $hoaDon = Database::fetchAll(
                "SELECT TOP 10 hd.*, pk.NgayKham
                 FROM HoaDon hd
                 INNER JOIN PhieuKham pk ON hd.MaPhieuKham = pk.MaPhieuKham
                 WHERE pk.MaBenhNhan = ? AND hd.IsDeleted = 0
                 ORDER BY hd.NgayThanhToan DESC", [$user['MaBenhNhan']]
            ) ?: [];
        }

        // Xóa mật khẩu khỏi response
        unset($user['MatKhau']);

        $this->success([
            'user'    => $user,
            'lichHen' => $lichHen,
            'hoaDon'  => $hoaDon,
        ], 'Chi tiết bệnh nhân');
    }

    /**
     * PUT /api/admin/benh-nhan/{id}
     */
    public function updateBenhNhan(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');
        $data = $this->getJSON();

        // Chỉ cho phép cập nhật các trường an toàn
        $allowed = ['HoTen', 'Email'];
        $updates = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[]  = $data[$field];
            }
        }

        if (empty($updates)) {
            $this->error('Không có dữ liệu cập nhật', null, 400);
            return;
        }

        $params[] = $id;
        Database::execute(
            "UPDATE NguoiDung SET " . implode(', ', $updates) . " WHERE MaNguoiDung = ? AND MaVaiTro = 4",
            $params
        );

        $this->success(null, 'Cập nhật thành công');
    }

    /**
     * POST /api/admin/toggle-status
     * Body: {id, status}
     */
    public function toggleStatus(): void
    {
        $this->requireAdmin();
        $data = $this->getJSON();
        $id = (int)($data['id'] ?? 0);
        $status = (int)($data['status'] ?? 0);

        if ($id <= 0 || !in_array($status, [0, 1])) {
            $this->error('Dữ liệu không hợp lệ', null, 400);
            return;
        }

        Database::execute(
            "UPDATE NguoiDung SET TrangThaiTK = ? WHERE MaNguoiDung = ? AND MaVaiTro = 4",
            [$status, $id]
        );

        $label = $status === 1 ? 'Mở khóa' : 'Khóa';
        $this->success(null, "{$label} tài khoản thành công");
    }

    /**
     * POST /api/admin/reset-password
     * Body: {id}
     */
    public function resetPassword(): void
    {
        $this->requireAdmin();
        $data = $this->getJSON();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            $this->error('Dữ liệu không hợp lệ', null, 400);
            return;
        }

        Database::execute(
            "UPDATE NguoiDung SET DoiMatKhau = 1 WHERE MaNguoiDung = ? AND MaVaiTro = 4",
            [$id]
        );

        $this->success(null, 'Đã yêu cầu đổi mật khẩu cho tài khoản');
    }

    // ═══════════════════════════════════════════════════════
    //  QUẢN LÝ THÀNH VIÊN
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/admin/thanh-vien
     */
    public function listThanhVien(): void
    {
        $this->requireAdmin();

        $page   = $this->getPage();
        $limit  = $this->getLimit();
        $offset = $this->getOffset($page, $limit);
        $search = $_GET['q'] ?? '';

        $where = "";
        $params = [];

        if (!empty($search)) {
            $where = "WHERE bn.HoTen LIKE ? OR bn.SoDienThoai LIKE ?";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) as total
             FROM ThanhVienInfo tv
             INNER JOIN BenhNhan bn ON tv.MaBenhNhan = bn.MaBenhNhan
             {$where}", $params
        );
        $total = (int)($countRow['total'] ?? 0);

        $list = Database::fetchAll(
            "SELECT tv.*, bn.HoTen, bn.SoDienThoai, bn.GioiTinh,
                    h.TenHang, h.MauHangHex, h.PhanTramGiamDuocPham, h.PhanTramGiamTongHD
             FROM ThanhVienInfo tv
             INNER JOIN BenhNhan bn ON tv.MaBenhNhan = bn.MaBenhNhan
             LEFT JOIN HangThanhVien h ON tv.MaHang = h.MaHang
             {$where}
             ORDER BY tv.DiemTichLuy DESC
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
            array_merge($params, [$offset, $limit])
        );

        $this->success([
            'list'       => $list ?: [],
            'pagination' => [
                'page'      => $page,
                'limit'     => $limit,
                'total'     => $total,
                'totalPages' => (int)ceil($total / $limit),
            ]
        ], 'Danh sách thành viên');
    }

    /**
     * GET /api/admin/thanh-vien/{id}
     */
    public function showThanhVien(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');

        $tv = Database::fetchOne(
            "SELECT tv.*, bn.HoTen, bn.SoDienThoai, bn.GioiTinh,
                    h.TenHang, h.MauHangHex
             FROM ThanhVienInfo tv
             INNER JOIN BenhNhan bn ON tv.MaBenhNhan = bn.MaBenhNhan
             LEFT JOIN HangThanhVien h ON tv.MaHang = h.MaHang
             WHERE tv.MaThanhVien = ?", [$id]
        );

        if (!$tv) {
            $this->notFound('Không tìm thấy thành viên');
            return;
        }

        $this->success($tv, 'Chi tiết thành viên');
    }

    /**
     * PUT /api/admin/thanh-vien/{id}
     */
    public function updateThanhVien(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');
        $data = $this->getJSON();

        $allowed = ['DiemTichLuy', 'SoLanKham'];
        $updates = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[]  = (int)$data[$field];
            }
        }

        if (empty($updates)) {
            $this->error('Không có dữ liệu cập nhật', null, 400);
            return;
        }

        $params[] = $id;
        Database::execute(
            "UPDATE ThanhVienInfo SET " . implode(', ', $updates) . " WHERE MaThanhVien = ?",
            $params
        );

        // Cập nhật lại hạng dựa trên điểm
        $tv = Database::fetchOne("SELECT DiemTichLuy FROM ThanhVienInfo WHERE MaThanhVien = ?", [$id]);
        if ($tv) {
            $hang = Database::fetchOne(
                "SELECT TOP 1 MaHang FROM HangThanhVien WHERE DiemToiThieu <= ? ORDER BY DiemToiThieu DESC",
                [$tv['DiemTichLuy']]
            );
            if ($hang) {
                Database::execute(
                    "UPDATE ThanhVienInfo SET MaHang = ? WHERE MaThanhVien = ?",
                    [$hang['MaHang'], $id]
                );
            }
        }

        $this->success(null, 'Cập nhật thành viên thành công');
    }

    // ═══════════════════════════════════════════════════════
    //  CẤU HÌNH HẠNG THÀNH VIÊN
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/admin/hang-thanh-vien
     */
    public function listHangTV(): void
    {
        $this->requireAdmin();

        $list = Database::fetchAll(
            "SELECT h.*, (SELECT COUNT(*) FROM ThanhVienInfo tv WHERE tv.MaHang = h.MaHang) as SoThanhVien
             FROM HangThanhVien h
             ORDER BY h.DiemToiThieu ASC", []
        );

        $this->success($list ?: [], 'Danh sách hạng thành viên');
    }

    /**
     * GET /api/admin/hang-thanh-vien/{id}
     */
    public function showHangTV(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');

        $hang = Database::fetchOne("SELECT * FROM HangThanhVien WHERE MaHang = ?", [$id]);
        if (!$hang) {
            $this->notFound('Không tìm thấy hạng');
            return;
        }

        $this->success($hang, 'Chi tiết hạng');
    }

    /**
     * POST /api/admin/hang-thanh-vien
     */
    public function createHangTV(): void
    {
        $this->requireAdmin();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'TenHang'       => 'required',
            'DiemToiThieu'  => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        Database::query(
            "INSERT INTO HangThanhVien (TenHang, DiemToiThieu, MauHangHex, PhanTramGiamDuocPham, PhanTramGiamTongHD, GiamGiaCodinh)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['TenHang'],
                (int)$data['DiemToiThieu'],
                $data['MauHangHex'] ?? '#6c757d',
                (float)($data['PhanTramGiamDuocPham'] ?? 0),
                (float)($data['PhanTramGiamTongHD'] ?? 0),
                (float)($data['GiamGiaCodinh'] ?? 0),
            ]
        );

        $this->success(null, 'Thêm hạng thành viên thành công', 201);
    }

    /**
     * PUT /api/admin/hang-thanh-vien/{id}
     */
    public function updateHangTV(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');
        $data = $this->getJSON();

        $allowed = ['TenHang', 'DiemToiThieu', 'MauHangHex', 'PhanTramGiamDuocPham', 'PhanTramGiamTongHD', 'GiamGiaCodinh'];
        $updates = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[]  = $data[$field];
            }
        }

        if (empty($updates)) {
            $this->error('Không có dữ liệu cập nhật', null, 400);
            return;
        }

        $params[] = $id;
        Database::execute(
            "UPDATE HangThanhVien SET " . implode(', ', $updates) . " WHERE MaHang = ?",
            $params
        );

        $this->success(null, 'Cập nhật hạng thành công');
    }

    /**
     * DELETE /api/admin/hang-thanh-vien/{id}
     */
    public function deleteHangTV(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');

        // Kiểm tra có thành viên nào đang dùng hạng này không
        $count = Database::fetchOne(
            "SELECT COUNT(*) as total FROM ThanhVienInfo WHERE MaHang = ?", [$id]
        );

        if ((int)($count['total'] ?? 0) > 0) {
            $this->error('Không thể xóa hạng đang có thành viên sử dụng', null, 400);
            return;
        }

        Database::execute("DELETE FROM HangThanhVien WHERE MaHang = ?", [$id]);
        $this->success(null, 'Xóa hạng thành công');
    }

    // ═══════════════════════════════════════════════════════
    //  QUẢN LÝ ĐÁNH GIÁ
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/admin/danh-gia
     */
    public function listDanhGia(): void
    {
        $this->requireAdmin();

        $page   = $this->getPage();
        $limit  = $this->getLimit();
        $offset = $this->getOffset($page, $limit);
        $diem   = $_GET['diem'] ?? '';

        $where = "";
        $params = [];

        if (!empty($diem) && is_numeric($diem)) {
            $where = "WHERE dg.DiemDanh = ?";
            $params[] = (int)$diem;
        }

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) as total FROM DanhGia dg {$where}", $params
        );
        $total = (int)($countRow['total'] ?? 0);

        $list = Database::fetchAll(
            "SELECT dg.*, bn.HoTen AS TenBenhNhan, bn.SoDienThoai
             FROM DanhGia dg
             INNER JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
             {$where}
             ORDER BY dg.NgayDanhGia DESC
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
            array_merge($params, [$offset, $limit])
        );

        $this->success([
            'list'       => $list ?: [],
            'pagination' => [
                'page'      => $page,
                'limit'     => $limit,
                'total'     => $total,
                'totalPages' => (int)ceil($total / $limit),
            ]
        ], 'Danh sách đánh giá');
    }

    /**
     * DELETE /api/admin/danh-gia/{id}
     */
    public function deleteDanhGia(): void
    {
        $this->requireAdmin();
        $id = (int)$this->getParam('id');

        Database::execute("DELETE FROM DanhGia WHERE MaDanhGia = ?", [$id]);
        $this->success(null, 'Xóa đánh giá thành công');
    }

    /**
     * GET /api/admin/danh-gia-stats
     */
    public function danhGiaStats(): void
    {
        $this->requireAdmin();

        $stats = Database::fetchAll(
            "SELECT DiemDanh, COUNT(*) as SoLuong
             FROM DanhGia
             GROUP BY DiemDanh
             ORDER BY DiemDanh ASC", []
        );

        $avg = Database::fetchOne(
            "SELECT AVG(CAST(DiemDanh AS FLOAT)) as avg_score,
                    COUNT(*) as total
             FROM DanhGia", []
        );

        $this->success([
            'phanBo'      => $stats ?: [],
            'diemTrungBinh' => round((float)($avg['avg_score'] ?? 0), 1),
            'tongDanhGia'   => (int)($avg['total'] ?? 0),
        ], 'Thống kê đánh giá');
    }
}
