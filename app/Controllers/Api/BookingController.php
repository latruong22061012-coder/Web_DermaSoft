<?php
/**
 * Booking API Controller
 * Xử lý đặt lịch hẹn từ website (cả khách vãng lai lẫn user đã login)
 *
 * Luồng bảo mật:
 * - User đã đăng nhập: dùng HoTen/SĐT từ session (không cho sửa)
 * - Khách vãng lai: nếu SĐT thuộc tài khoản đã đăng ký → từ chối, yêu cầu đăng nhập
 * - Rate limit: tối đa 5 lịch hẹn chờ xử lý / SĐT
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;

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

        $ghiChu = isset($data['ghiChu']) ? trim($data['ghiChu']) : null;
        if ($ghiChu === '') $ghiChu = null;

        // Rate limit: tối đa 5 lịch hẹn đang chờ xử lý / SĐT
        try {
            $pendingCount = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM LichHen lh
                 JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
                 WHERE bn.SoDienThoai = ? AND lh.TrangThai IN (0, 1)",
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
                "EXEC SP_DatLichHen @HoTen = ?, @SoDienThoai = ?, @ThoiGianHen = ?, @GhiChu = ?",
                [
                    trim($data['hoTen']),
                    $phone,
                    $dt->format('Y-m-d H:i:s'),
                    $ghiChu,
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
}
