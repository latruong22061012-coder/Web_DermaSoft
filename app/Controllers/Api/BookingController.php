<?php
/**
 * Booking API Controller
 * Xử lý đặt lịch hẹn từ website (cả khách vãng lai lẫn user đã login)
 *
 * Luồng bảo mật:
 * - User đã đăng nhập: dùng HoTen/SĐT từ session (không cho sửa)
 * - Khách vãng lai: nếu SĐT thuộc tài khoản đã đăng ký → từ chối, yêu cầu đăng nhập
 * - Rate limit: tối đa 5 lịch hẹn chờ xử lý tương lai / SĐT
 * - Auto-cancel: tự động hủy lịch hẹn chưa xác nhận đã qua ngày
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;
use App\Models\ThongTinPhongKham;

class BookingController extends ApiController
{
    /**
     * POST /api/booking/create
     * Body: { hoTen, soDienThoai, thoiGianHen (Y-m-d H:i), ghiChu? }
     */
    public function create(): void
    {
        Auth::startSession();

        $currentUser = Auth::getCurrentUser();
        $data = $this->getJSON();

        // Admin không được phép đặt lịch hẹn qua website
        if ($currentUser && (int)($currentUser['MaVaiTro'] ?? 0) === 1) {
            $this->error('Tài khoản quản trị không thể đặt lịch hẹn.', null, 403);
            return;
        }

        // Nếu user đã đăng nhập → ép dùng thông tin từ session (không cho giả mạo)
        if ($currentUser) {
            $data['hoTen'] = $currentUser['HoTen'];
            $data['soDienThoai'] = $currentUser['SoDienThoai'];
        }

        $errors = $this->validate($data, [
            'hoTen'      => 'required|minlen:3',
            'soDienThoai'=> 'required',
            'thoiGianHen'=> 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        // Validate phone
        $phone = trim($data['soDienThoai']);
        if (!preg_match('/^(0)(3[2-9]|5[6-9]|7[06-9]|8[0-9]|9[0-9])[0-9]{7}$/', $phone)) {
            $this->error('Số điện thoại không đúng định dạng', null, 400);
            return;
        }

        // Khách vãng lai: kiểm tra SĐT có thuộc tài khoản đã đăng ký không
        if (!$currentUser) {
            try {
                $registeredUser = Database::fetchOne(
                    "SELECT MaNguoiDung FROM NguoiDung WHERE SoDienThoai = ? AND IsDeleted = 0",
                    [$phone]
                );
                if ($registeredUser) {
                    $this->error(
                        'Số điện thoại này đã được đăng ký tài khoản. Vui lòng đăng nhập để đặt lịch.',
                        ['requireLogin' => true],
                        403
                    );
                    return;
                }
            } catch (\Exception $e) {
                error_log('Lỗi kiểm tra SĐT đã đăng ký: ' . $e->getMessage());
            }
        }

        // Validate & parse datetime (format: Y-m-d H:i)
        $thoiGianHenStr = trim($data['thoiGianHen']);
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $thoiGianHenStr);
        if (!$dt || $dt->format('Y-m-d H:i') !== $thoiGianHenStr) {
            $this->error('Thời gian hẹn không đúng định dạng (YYYY-MM-DD HH:MM)', null, 400);
            return;
        }

        // Phải là ngày trong tương lai
        if ($dt <= new \DateTime()) {
            $this->error('Thời gian hẹn phải sau thời điểm hiện tại', null, 400);
            return;
        }

        // Không đặt quá 60 ngày từ hôm nay
        $maxDate = new \DateTime('+60 days');
        if ($dt > $maxDate) {
            $this->error('Không thể đặt lịch quá 60 ngày từ hôm nay', null, 400);
            return;
        }

        // Validate giờ hẹn theo khung giờ hoạt động phòng khám (trước giờ đóng cửa 1 tiếng)
        $pkModel = new ThongTinPhongKham();
        $pkInfo = $pkModel->getInfo();
        $openTime = $pkInfo['GioMoCua'] ?? '08:00:00';
        $closeTime = $pkInfo['GioDongCua'] ?? '17:00:00';
        $openParts = explode(':', $openTime);
        $closeParts = explode(':', $closeTime);
        $openMin = (int)$openParts[0] * 60 + (int)($openParts[1] ?? 0);
        $closeMin = (int)$closeParts[0] * 60 + (int)($closeParts[1] ?? 0);
        $lastSlotMin = $closeMin - 60; // trước 1 tiếng

        $henH = (int)$dt->format('H');
        $henM = (int)$dt->format('i');
        $henMin = $henH * 60 + $henM;

        if ($henMin < $openMin || $henMin > $lastSlotMin) {
            $openLabel = sprintf('%02d:%02d', intdiv($openMin, 60), $openMin % 60);
            $lastLabel = sprintf('%02d:%02d', intdiv($lastSlotMin, 60), $lastSlotMin % 60);
            $this->error("Giờ hẹn phải trong khung {$openLabel} - {$lastLabel} (trước giờ đóng cửa 1 tiếng)", null, 400);
            return;
        }

        // Bỏ qua giờ nghỉ trưa 12:00 - 12:59
        if ($henMin >= 720 && $henMin < 780) {
            $this->error('Không thể đặt lịch trong giờ nghỉ trưa (12:00 - 13:00)', null, 400);
            return;
        }

        $ghiChu = isset($data['ghiChu']) ? trim($data['ghiChu']) : null;
        if ($ghiChu === '') $ghiChu = null;

        // Xử lý bác sĩ được chọn (nếu có)
        $maNguoiDung = isset($data['maNguoiDung']) && $data['maNguoiDung'] !== '' ? (int)$data['maNguoiDung'] : null;

        if ($maNguoiDung !== null) {
            // Validate bác sĩ tồn tại và có vai trò Bác Sĩ
            $doctor = Database::fetchOne(
                "SELECT MaNguoiDung FROM NguoiDung WHERE MaNguoiDung = ? AND MaVaiTro = 2 AND TrangThaiTK = 1 AND IsDeleted = 0",
                [$maNguoiDung]
            );
            if (!$doctor) {
                $this->error('Bác sĩ không hợp lệ hoặc không còn hoạt động.', null, 400);
                return;
            }

            // Validate bác sĩ có ca làm việc trong ngày đã chọn
            $shift = Database::fetchOne(
                "SELECT pc.MaCa FROM PhanCongCa pc WHERE pc.MaNguoiDung = ? AND pc.NgayLamViec = ?",
                [$maNguoiDung, $dt->format('Y-m-d')]
            );
            if (!$shift) {
                $this->error('Bác sĩ này không có ca làm việc vào ngày đã chọn.', null, 400);
                return;
            }

            // Giới hạn 8 bệnh nhân / bác sĩ / ngày
            $bnCount = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM LichHen WHERE MaNguoiDung = ? AND CAST(ThoiGianHen AS DATE) = ? AND TrangThai IN (0, 1)",
                [$maNguoiDung, $dt->format('Y-m-d')]
            );
            if ($bnCount && (int)$bnCount['cnt'] >= 8) {
                $this->error('Bác sĩ đã đầy lịch trong ngày này (tối đa 8 bệnh nhân). Vui lòng chọn bác sĩ khác.', null, 400);
                return;
            }
        }

        // Tự động hủy lịch hẹn chưa xác nhận (TrangThai=0) đã qua ngày
        try {
            Database::query(
                "UPDATE LichHen SET TrangThai = 3
                 WHERE TrangThai = 0
                   AND MaBenhNhan IN (SELECT bn.MaBenhNhan FROM BenhNhan bn WHERE bn.SoDienThoai = ?)
                   AND CAST(ThoiGianHen AS DATE) < CAST(GETDATE() AS DATE)",
                [$phone]
            );
        } catch (\Exception $e) {
            error_log('Lỗi tự động hủy lịch hẹn quá hạn: ' . $e->getMessage());
        }

        // Rate limit: tối đa 5 lịch hẹn đang chờ xử lý / SĐT (chỉ đếm tương lai)
        try {
            $pendingCount = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM LichHen lh
                 JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
                 WHERE bn.SoDienThoai = ? AND lh.TrangThai IN (0, 1)
                   AND CAST(lh.ThoiGianHen AS DATE) >= CAST(GETDATE() AS DATE)",
                [$phone]
            );
            if ($pendingCount && (int)$pendingCount['cnt'] >= 5) {
                $this->error('Bạn đã có quá nhiều lịch hẹn đang chờ xử lý. Vui lòng chờ hoàn thành hoặc hủy bớt.', null, 429);
                return;
            }
        } catch (\Exception $e) {
            error_log('Lỗi kiểm tra rate limit: ' . $e->getMessage());
        }

        // Kiểm tra trùng lịch ở PHP trước khi gọi SP
        try {
            $duplicate = Database::fetchOne(
                "SELECT TOP 1 lh.MaLichHen
                 FROM LichHen lh
                 JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
                 WHERE bn.SoDienThoai = ?
                   AND CAST(lh.ThoiGianHen AS DATE) = CAST(? AS DATE)
                   AND lh.TrangThai IN (0, 1)",
                [$phone, $dt->format('Y-m-d')]
            );
            if ($duplicate) {
                $this->error('Số điện thoại này đã có lịch hẹn trong ngày đã chọn. Vui lòng chọn ngày khác.', null, 400);
                return;
            }
        } catch (\Exception $e) {
            error_log('Lỗi kiểm tra trùng lịch: ' . $e->getMessage());
        }

        try {
            $result = Database::fetchOne(
                "EXEC SP_DatLichHen @HoTen = ?, @SoDienThoai = ?, @ThoiGianHen = ?, @GhiChu = ?, @MaNguoiDung = ?",
                [
                    trim($data['hoTen']),
                    $phone,
                    $dt->format('Y-m-d H:i:s'),
                    $ghiChu,
                    $maNguoiDung,
                ]
            );

            $this->success([
                'maLichHen'  => $result['MaLichHen']  ?? null,
                'maBenhNhan' => $result['MaBenhNhan'] ?? null,
                'thoiGianHen'=> $dt->format('d/m/Y H:i'),
            ], 'Đặt lịch thành công! Chúng tôi sẽ liên hệ xác nhận trong vòng 30 phút.');

        } catch (\Exception $e) {
            error_log('Lỗi đặt lịch: ' . $e->getMessage());
            $this->error('Lỗi hệ thống khi đặt lịch. Vui lòng thử lại sau.', null, 500);
        }
    }

    /**
     * GET /api/booking/doctors?ngay=2026-04-17
     * Trả DS bác sĩ có ca làm việc ngày đã chọn + số BN hiện tại (giới hạn 8)
     */
    public function doctors(): void
    {
        $ngay = $_GET['ngay'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            $this->error('Ngày không hợp lệ', null, 400);
            return;
        }

        // Lấy bác sĩ có phân công ca trong ngày + đếm BN đã hẹn
        $doctors = Database::fetchAll(
            "SELECT nd.MaNguoiDung, nd.HoTen,
                    c.MaCa, c.TenCa, c.GioBatDau, c.GioKetThuc,
                    ISNULL(bn_count.SoBN, 0) AS SoBN
             FROM PhanCongCa pc
             INNER JOIN NguoiDung nd ON pc.MaNguoiDung = nd.MaNguoiDung
             INNER JOIN CaLamViec c ON pc.MaCa = c.MaCa
             LEFT JOIN (
                 SELECT MaNguoiDung, COUNT(*) AS SoBN
                 FROM LichHen
                 WHERE TrangThai IN (0, 1)
                   AND CAST(ThoiGianHen AS DATE) = ?
                 GROUP BY MaNguoiDung
             ) bn_count ON bn_count.MaNguoiDung = nd.MaNguoiDung
             WHERE nd.MaVaiTro = 2
               AND nd.TrangThaiTK = 1
               AND nd.IsDeleted = 0
               AND pc.NgayLamViec = ?
             ORDER BY nd.HoTen, c.GioBatDau",
            [$ngay, $ngay]
        );

        $this->success(['doctors' => $doctors, 'gioiHanBN' => 8]);
    }
}
