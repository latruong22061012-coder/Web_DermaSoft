<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;

class BacSiApiController extends ApiController
{
    /**
     * Kiểm tra quyền Bác Sĩ
     */
    private function requireBacSi(): int
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Chưa đăng nhập');
            exit;
        }

        if (!Auth::hasRole(2)) {
            $this->forbidden('Không có quyền truy cập');
            exit;
        }

        $user = Auth::getCurrentUser();
        return (int)$user['MaNguoiDung'];
    }

    // ═══════════════════════════════════════════════════════
    //  DASHBOARD STATS
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/bacsi/stats
     * KPI cards cho dashboard bác sĩ
     */
    public function stats(): void
    {
        $maNguoiDung = $this->requireBacSi();

        // BN đang chờ khám hôm nay (PhieuKham TrangThai=0 + LichHen hôm nay)
        $choKham = Database::fetchOne(
            "SELECT COUNT(*) as total FROM PhieuKham pk
             WHERE pk.MaNguoiDung = ? AND pk.TrangThai = 0 AND pk.IsDeleted = 0
               AND CAST(pk.NgayKham AS DATE) = CAST(GETDATE() AS DATE)", [$maNguoiDung]
        );

        // BN đã khám hôm nay (PhieuKham TrangThai >= 2)
        $daKham = Database::fetchOne(
            "SELECT COUNT(*) as total FROM PhieuKham pk
             WHERE pk.MaNguoiDung = ? AND pk.TrangThai >= 2 AND pk.IsDeleted = 0
               AND CAST(pk.NgayKham AS DATE) = CAST(GETDATE() AS DATE)", [$maNguoiDung]
        );

        // Tổng BN tháng này
        $bnThangNay = Database::fetchOne(
            "SELECT COUNT(*) as total FROM PhieuKham pk
             WHERE pk.MaNguoiDung = ? AND pk.TrangThai >= 2 AND pk.IsDeleted = 0
               AND MONTH(pk.NgayKham) = MONTH(GETDATE())
               AND YEAR(pk.NgayKham) = YEAR(GETDATE())", [$maNguoiDung]
        );

        // Điểm đánh giá trung bình
        $diemTB = Database::fetchOne(
            "SELECT AVG(CAST(dg.DiemDanh AS FLOAT)) as avg_score,
                    COUNT(*) as total_reviews
             FROM DanhGia dg
             INNER JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
             WHERE pk.MaNguoiDung = ? AND pk.IsDeleted = 0", [$maNguoiDung]
        );

        // Lịch hẹn đã được yêu cầu hôm nay (LichHen TrangThai IN (0,1) + gán cho bác sĩ)
        $lichYeuCau = Database::fetchOne(
            "SELECT COUNT(*) as total FROM LichHen lh
             WHERE lh.MaNguoiDung = ? AND lh.TrangThai IN (0, 1)
               AND CAST(lh.ThoiGianHen AS DATE) = CAST(GETDATE() AS DATE)", [$maNguoiDung]
        );

        // Ca làm hôm nay
        $caHomNay = Database::fetchAll(
            "SELECT pc.MaPhanCong, c.TenCa, c.GioBatDau, c.GioKetThuc, pc.TrangThaiDiemDanh
             FROM PhanCongCa pc
             INNER JOIN CaLamViec c ON pc.MaCa = c.MaCa
             WHERE pc.MaNguoiDung = ? AND pc.NgayLamViec = CAST(GETDATE() AS DATE)
             ORDER BY c.GioBatDau", [$maNguoiDung]
        );

        $this->success([
            'choKham' => (int)($choKham['total'] ?? 0),
            'daKham' => (int)($daKham['total'] ?? 0),
            'bnThangNay' => (int)($bnThangNay['total'] ?? 0),
            'diemTB' => round((float)($diemTB['avg_score'] ?? 0), 1),
            'tongDanhGia' => (int)($diemTB['total_reviews'] ?? 0),
            'lichYeuCau' => (int)($lichYeuCau['total'] ?? 0),
            'caHomNay' => $caHomNay,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  LỊCH LÀM VIỆC
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/bacsi/lich-lam-viec?thang=2026-04
     * Lịch phân công ca theo tháng
     */
    public function lichLamViec(): void
    {
        $maNguoiDung = $this->requireBacSi();

        // Hỗ trợ lọc theo ngày hoặc theo tháng
        $ngay = $_GET['ngay'] ?? null;
        if ($ngay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            $whereDate = "CAST(pc.NgayLamViec AS DATE) = ?";
            $whereStatDate = "CAST(NgayLamViec AS DATE) = ?";
            $paramDate = $ngay;
        } else {
            $thang = $_GET['thang'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $thang)) {
                $thang = date('Y-m');
            }
            $whereDate = "FORMAT(pc.NgayLamViec, 'yyyy-MM') = ?";
            $whereStatDate = "FORMAT(NgayLamViec, 'yyyy-MM') = ?";
            $paramDate = $thang;
        }

        $lichCa = Database::fetchAll(
            "SELECT pc.MaPhanCong, pc.NgayLamViec, pc.TrangThaiDiemDanh,
                    c.MaCa, c.TenCa, c.GioBatDau, c.GioKetThuc
             FROM PhanCongCa pc
             INNER JOIN CaLamViec c ON pc.MaCa = c.MaCa
             WHERE pc.MaNguoiDung = ?
               AND {$whereDate}
             ORDER BY pc.NgayLamViec, c.GioBatDau",
            [$maNguoiDung, $paramDate]
        );

        $thongKe = Database::fetchOne(
            "SELECT COUNT(*) as tongCa,
                    SUM(CASE WHEN TrangThaiDiemDanh = 2 THEN 1 ELSE 0 END) as daDiemDanh,
                    SUM(CASE WHEN TrangThaiDiemDanh = 3 THEN 1 ELSE 0 END) as caVang
             FROM PhanCongCa
             WHERE MaNguoiDung = ? AND {$whereStatDate}",
            [$maNguoiDung, $paramDate]
        );

        $this->success([
            'lichCa' => $lichCa,
            'thongKe' => [
                'tongCa' => (int)($thongKe['tongCa'] ?? 0),
                'daDiemDanh' => (int)($thongKe['daDiemDanh'] ?? 0),
                'caVang' => (int)($thongKe['caVang'] ?? 0),
            ]
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  BỆNH NHÂN CỦA TÔI
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/bacsi/benh-nhan?ngay=2026-04-16
     * DS bệnh nhân được gán cho bác sĩ (theo lịch hẹn / phiếu khám)
     */
    public function dsBenhNhan(): void
    {
        $maNguoiDung = $this->requireBacSi();

        $ngay = $_GET['ngay'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            $ngay = date('Y-m-d');
        }

        $benhNhan = Database::fetchAll(
            "SELECT pk.MaPhieuKham, pk.TrangThai as TrangThaiPK, pk.NgayKham,
                    pk.TrieuChung, pk.ChanDoan, pk.GhiChu,
                    bn.MaBenhNhan, bn.HoTen, bn.NgaySinh, bn.GioiTinh, bn.SoDienThoai,
                    bn.TienSuBenhLy,
                    lh.MaLichHen, lh.ThoiGianHen, lh.GhiChu as GhiChuHen
             FROM PhieuKham pk
             INNER JOIN BenhNhan bn ON pk.MaBenhNhan = bn.MaBenhNhan
             LEFT JOIN LichHen lh ON pk.MaLichHen = lh.MaLichHen
             WHERE pk.MaNguoiDung = ?
               AND CAST(pk.NgayKham AS DATE) = ?
               AND pk.IsDeleted = 0
             ORDER BY pk.NgayKham ASC",
            [$maNguoiDung, $ngay]
        );

        $this->success(['benhNhan' => $benhNhan, 'ngay' => $ngay]);
    }

    // ═══════════════════════════════════════════════════════
    //  BẢNG LƯƠNG
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/bacsi/luong?nam=2026
     * Bảng lương theo năm
     */
    public function luong(): void
    {
        $maNguoiDung = $this->requireBacSi();

        $nam = $_GET['nam'] ?? date('Y');
        if (!preg_match('/^\d{4}$/', $nam)) {
            $nam = date('Y');
        }

        $bangLuong = Database::fetchAll(
            "SELECT bl.MaBangLuong, bl.ThangNam, bl.LoaiTinhLuong, bl.DonGia,
                    bl.HeSoTangCa, bl.SoBenhNhan, bl.SoBNTangCa,
                    bl.SoGioLam, bl.SoGioTangCa, bl.SoCaDiemDanh, bl.SoCaVang,
                    bl.LuongChinh, bl.LuongTangCa, bl.ThuongThem, bl.KhauTru,
                    bl.TongLuong, bl.GhiChu, bl.TrangThai, bl.NgayTao,
                    nd.HoTen as NguoiDuyetTen, bl.NgayDuyet
             FROM BangLuong bl
             LEFT JOIN NguoiDung nd ON bl.NguoiDuyet = nd.MaNguoiDung
             WHERE bl.MaNguoiDung = ? AND YEAR(bl.ThangNam) = ?
             ORDER BY bl.ThangNam DESC",
            [$maNguoiDung, (int)$nam]
        );

        // Lịch sử trả lương
        $lichSuTra = Database::fetchAll(
            "SELECT lst.MaTraLuong, lst.SoTienTra, lst.PhuongThuc, lst.NgayTra,
                    lst.GhiChu, nd.HoTen as NguoiTraTen,
                    FORMAT(bl.ThangNam, 'yyyy-MM') as ThangLuong
             FROM LichSuTraLuong lst
             INNER JOIN BangLuong bl ON lst.MaBangLuong = bl.MaBangLuong
             LEFT JOIN NguoiDung nd ON lst.NguoiTra = nd.MaNguoiDung
             WHERE bl.MaNguoiDung = ? AND YEAR(bl.ThangNam) = ?
             ORDER BY lst.NgayTra DESC",
            [$maNguoiDung, (int)$nam]
        );

        $this->success([
            'bangLuong' => $bangLuong,
            'lichSuTra' => $lichSuTra,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  ĐÁNH GIÁ CỦA TÔI
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/bacsi/danh-gia
     * Danh sách đánh giá từ bệnh nhân
     */
    public function danhGia(): void
    {
        $maNguoiDung = $this->requireBacSi();

        $danhGia = Database::fetchAll(
            "SELECT dg.MaDanhGia, dg.DiemDanh, dg.NhanXet, dg.NgayDanhGia,
                    bn.HoTen as TenBenhNhan,
                    pk.MaPhieuKham, pk.ChanDoan
             FROM DanhGia dg
             INNER JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
             INNER JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
             WHERE pk.MaNguoiDung = ? AND pk.IsDeleted = 0
             ORDER BY dg.NgayDanhGia DESC",
            [$maNguoiDung]
        );

        // Phân bố điểm
        $phanBo = Database::fetchAll(
            "SELECT dg.DiemDanh, COUNT(*) as soLuong
             FROM DanhGia dg
             INNER JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
             WHERE pk.MaNguoiDung = ? AND pk.IsDeleted = 0
             GROUP BY dg.DiemDanh
             ORDER BY dg.DiemDanh",
            [$maNguoiDung]
        );

        $this->success([
            'danhGia' => $danhGia,
            'phanBoDiem' => $phanBo,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  THỐNG KÊ BIỂU ĐỒ
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/bacsi/thong-ke-bn
     * BN theo tháng (6 tháng gần nhất)
     */
    public function thongKeBN(): void
    {
        $maNguoiDung = $this->requireBacSi();

        $bnTheoThang = Database::fetchAll(
            "SELECT FORMAT(pk.NgayKham, 'yyyy-MM') as thang, COUNT(*) as soLuong
             FROM PhieuKham pk
             WHERE pk.MaNguoiDung = ? AND pk.IsDeleted = 0
               AND pk.NgayKham >= DATEADD(MONTH, -6, GETDATE())
             GROUP BY FORMAT(pk.NgayKham, 'yyyy-MM')
             ORDER BY thang ASC",
            [$maNguoiDung]
        );

        $this->success(['bnTheoThang' => $bnTheoThang]);
    }
}
