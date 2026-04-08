<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Models\ThongTinPhongKham;

class ProfileController extends Controller
{
    public function index(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            header('Location: index.php?route=login');
            exit;
        }

        // Admin truy cập profile → chuyển sang dashboard
        if (Auth::hasRole(1)) {
            header('Location: index.php?route=admin/dashboard');
            exit;
        }

        $sessionUser = Auth::getCurrentUser();
        $freshUser   = User::findByPhone($sessionUser['SoDienThoai'] ?? '');
        $user        = $freshUser ?: $sessionUser;
        $_SESSION['authenticated_user'] = $user;

        $phone = $user['SoDienThoai'] ?? '';

        // ─── Tìm hồ sơ BenhNhan theo SĐT ────────────────────────────
        $benhNhan = Database::fetchOne(
            "SELECT * FROM BenhNhan WHERE SoDienThoai = ? AND IsDeleted = 0",
            [$phone]
        );

        // ─── Tải danh sách hạng thành viên (luôn cần, kể cả khi chưa có BenhNhan) ───
        $allHangs = Database::fetchAll(
            "SELECT * FROM HangThanhVien ORDER BY DiemToiThieu ASC",
            []
        );

        // ─── Giá trị mặc định khi chưa có hồ sơ ─────────────────────
        $thanhVienInfo        = null;
        $hangHienTai          = null;
        $hangColor            = '#0F5C4D';
        $progressPercent      = 0;
        $diemConLai           = 0;
        $tenHangTiep          = null;
        $upcomingAppointments = [];
        $historyRecords       = [];
        $diemTichLuy          = 0;
        $soLichSapToi         = 0;
        $tyLeHaiLong          = 0;

        if ($benhNhan) {
            $maBenhNhan = (int)$benhNhan['MaBenhNhan'];

            // ThanhVienInfo kèm tên hạng + thông tin khuyến mãi
            $thanhVienInfo = Database::fetchOne(
                "SELECT tvi.*, htv.TenHang, htv.DiemToiThieu AS DiemHangHienTai, htv.MauHangHex,
                        htv.PhanTramGiamDuocPham, htv.PhanTramGiamTongHD, htv.GiamGiaCodinh, htv.GhiChuKhuyenMai
                 FROM ThanhVienInfo tvi
                 LEFT JOIN HangThanhVien htv ON tvi.MaHang = htv.MaHang
                 WHERE tvi.MaBenhNhan = ?",
                [$maBenhNhan]
            );

            if ($thanhVienInfo) {
                $diemTichLuy = (int)$thanhVienInfo['DiemTichLuy'];
                $tyLeHaiLong  = (float)($thanhVienInfo['TyLeHaiLong'] ?? 0);
                $hangHienTai  = $thanhVienInfo['TenHang'] ?? 'Thành Viên';
                $hangColor    = $thanhVienInfo['MauHangHex'] ?? '#0F5C4D';
                $diemHangMin  = (int)($thanhVienInfo['DiemHangHienTai'] ?? 0);

                // Tìm hạng tiếp theo để tính progress
                foreach ($allHangs as $hang) {
                    if ((int)$hang['DiemToiThieu'] > $diemHangMin) {
                        $tenHangTiep    = $hang['TenHang'];
                        $diemConLai     = (int)$hang['DiemToiThieu'] - $diemTichLuy;
                        $khoangCach     = (int)$hang['DiemToiThieu'] - $diemHangMin;
                        $progressPercent = $khoangCach > 0
                            ? min(100, (int)round(($diemTichLuy - $diemHangMin) / $khoangCach * 100))
                            : 100;
                        break;
                    }
                }
                if ($tenHangTiep === null) {
                    $progressPercent = 100; // Đã đạt hạng cao nhất
                    $diemConLai = 0;
                }
            }

            // Lịch hẹn sắp tới (TrangThai 0=chờ xác nhận, 1=đã xác nhận)
            $upcomingAppointments = Database::fetchAll(
                "SELECT lh.*, nd.HoTen AS TenBacSi
                 FROM LichHen lh
                 LEFT JOIN NguoiDung nd ON lh.MaNguoiDung = nd.MaNguoiDung
                 WHERE lh.MaBenhNhan = ? AND lh.TrangThai IN (0, 1)
                   AND lh.ThoiGianHen >= GETDATE()
                 ORDER BY lh.ThoiGianHen ASC",
                [$maBenhNhan]
            );
            $soLichSapToi = count($upcomingAppointments);

            // Lịch sử khám (10 phiếu khám gần nhất)
            $historyRecords = Database::fetchAll(
                "SELECT TOP 10 pk.*, nd.HoTen AS TenBacSi
                 FROM PhieuKham pk
                 LEFT JOIN NguoiDung nd ON pk.MaNguoiDung = nd.MaNguoiDung
                 WHERE pk.MaBenhNhan = ? AND pk.IsDeleted = 0
                 ORDER BY pk.NgayKham DESC",
                [$maBenhNhan]
            );
        }

        $phongKhamModel = new ThongTinPhongKham();
        $phongKham = $phongKhamModel->getAllInfo() ?? [];

        $this->render('profile', [
            'user'                 => $user,
            'benhNhan'             => $benhNhan,
            'thanhVienInfo'        => $thanhVienInfo,
            'hangHienTai'          => $hangHienTai,
            'hangColor'            => $hangColor,
            'allHangs'             => $allHangs,
            'progressPercent'      => $progressPercent,
            'diemTichLuy'          => $diemTichLuy,
            'diemConLai'           => $diemConLai,
            'tenHangTiep'          => $tenHangTiep,
            'tyLeHaiLong'          => $tyLeHaiLong,
            'soLichSapToi'         => $soLichSapToi,
            'upcomingAppointments' => $upcomingAppointments,
            'historyRecords'       => $historyRecords,
            'phongKham'            => $phongKham,
        ]);
    }
}
