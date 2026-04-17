<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;

class LeTanApiController extends ApiController
{
    /**
     * Kiểm tra quyền Lễ Tân
     */
    private function requireLeTan(): int
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Chưa đăng nhập');
            exit;
        }

        if (!Auth::hasRole(3)) {
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
     * GET /api/letan/stats
     */
    public function stats(): void
    {
        $maNguoiDung = $this->requireLeTan();

        // Lịch hẹn chờ xác nhận hôm nay
        $choXacNhan = Database::fetchOne(
            "SELECT COUNT(*) as total FROM LichHen
             WHERE TrangThai = 0
               AND CAST(ThoiGianHen AS DATE) = CAST(GETDATE() AS DATE)", []
        );

        // Lịch hẹn đã xác nhận hôm nay
        $daXacNhan = Database::fetchOne(
            "SELECT COUNT(*) as total FROM LichHen
             WHERE TrangThai = 1
               AND CAST(ThoiGianHen AS DATE) = CAST(GETDATE() AS DATE)", []
        );

        // Tổng lịch hẹn hôm nay
        $tongHomNay = Database::fetchOne(
            "SELECT COUNT(*) as total FROM LichHen
             WHERE CAST(ThoiGianHen AS DATE) = CAST(GETDATE() AS DATE)", []
        );

        // Lịch hẹn bị hủy tháng này
        $daHuy = Database::fetchOne(
            "SELECT COUNT(*) as total FROM LichHen
             WHERE TrangThai = 3
               AND MONTH(ThoiGianHen) = MONTH(GETDATE())
               AND YEAR(ThoiGianHen) = YEAR(GETDATE())", []
        );

        // Ca làm hôm nay của lễ tân
        $caHomNay = Database::fetchAll(
            "SELECT pc.MaPhanCong, c.TenCa, c.GioBatDau, c.GioKetThuc, pc.TrangThaiDiemDanh
             FROM PhanCongCa pc
             INNER JOIN CaLamViec c ON pc.MaCa = c.MaCa
             WHERE pc.MaNguoiDung = ? AND pc.NgayLamViec = CAST(GETDATE() AS DATE)
             ORDER BY c.GioBatDau", [$maNguoiDung]
        );

        $this->success([
            'choXacNhan' => (int)($choXacNhan['total'] ?? 0),
            'daXacNhan' => (int)($daXacNhan['total'] ?? 0),
            'tongHomNay' => (int)($tongHomNay['total'] ?? 0),
            'daHuy' => (int)($daHuy['total'] ?? 0),
            'caHomNay' => $caHomNay,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  LỊCH HẸN
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/letan/lich-hen?ngay=2026-04-16&trangThai=0
     */
    public function dsLichHen(): void
    {
        $this->requireLeTan();

        $ngay = $_GET['ngay'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            $ngay = date('Y-m-d');
        }

        $trangThai = isset($_GET['trangThai']) && is_numeric($_GET['trangThai']) ? (int)$_GET['trangThai'] : null;

        $sql = "SELECT lh.MaLichHen, lh.ThoiGianHen, lh.TrangThai, lh.GhiChu, lh.SoDienThoaiKhach,
                       bn.MaBenhNhan, bn.HoTen, bn.SoDienThoai, bn.NgaySinh, bn.GioiTinh,
                       nd.HoTen as TenBacSi
                FROM LichHen lh
                INNER JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
                LEFT JOIN NguoiDung nd ON lh.MaNguoiDung = nd.MaNguoiDung
                WHERE CAST(lh.ThoiGianHen AS DATE) = ?";
        $params = [$ngay];

        if ($trangThai !== null) {
            $sql .= " AND lh.TrangThai = ?";
            $params[] = $trangThai;
        }

        $sql .= " ORDER BY lh.ThoiGianHen ASC";

        $lichHen = Database::fetchAll($sql, $params);

        $this->success(['lichHen' => $lichHen, 'ngay' => $ngay]);
    }

    // ═══════════════════════════════════════════════════════
    //  LỊCH LÀM VIỆC
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/letan/lich-lam-viec?thang=2026-04
     */
    public function lichLamViec(): void
    {
        $maNguoiDung = $this->requireLeTan();

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
    //  BẢNG LƯƠNG
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/letan/luong?nam=2026
     */
    public function luong(): void
    {
        $maNguoiDung = $this->requireLeTan();

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
    //  THỐNG KÊ BIỂU ĐỒ
    // ═══════════════════════════════════════════════════════

    /**
     * GET /api/letan/thong-ke-lh
     * Lịch hẹn theo tháng (6 tháng) + phân bố trạng thái
     */
    public function thongKeLH(): void
    {
        $this->requireLeTan();

        $lhTheoThang = Database::fetchAll(
            "SELECT FORMAT(ThoiGianHen, 'yyyy-MM') as thang, COUNT(*) as soLuong
             FROM LichHen
             WHERE ThoiGianHen >= DATEADD(MONTH, -6, GETDATE())
             GROUP BY FORMAT(ThoiGianHen, 'yyyy-MM')
             ORDER BY thang ASC", []
        );

        $phanBoTT = Database::fetchAll(
            "SELECT TrangThai, COUNT(*) as soLuong
             FROM LichHen
             WHERE MONTH(ThoiGianHen) = MONTH(GETDATE())
               AND YEAR(ThoiGianHen) = YEAR(GETDATE())
             GROUP BY TrangThai
             ORDER BY TrangThai", []
        );

        // Số BN theo bác sĩ hôm nay
        $bnTheoBacSi = Database::fetchAll(
            "SELECT nd.HoTen as TenBacSi, COUNT(lh.MaLichHen) as soLuong
             FROM LichHen lh
             INNER JOIN NguoiDung nd ON lh.MaNguoiDung = nd.MaNguoiDung
             WHERE CAST(lh.ThoiGianHen AS DATE) = CAST(GETDATE() AS DATE)
               AND nd.MaVaiTro = 2
             GROUP BY nd.MaNguoiDung, nd.HoTen
             ORDER BY soLuong DESC", []
        );

        $this->success([
            'lhTheoThang' => $lhTheoThang,
            'phanBoTrangThai' => $phanBoTT,
            'bnTheoBacSi' => $bnTheoBacSi,
        ]);
    }
}
