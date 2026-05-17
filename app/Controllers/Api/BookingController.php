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
use App\Models\LichHen;

class BookingController extends ApiController
{
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int)($parts[0] ?? 0) * 60) + (int)($parts[1] ?? 0);
    }

    private function formatSlotLabel(int $minutes): string
    {
        $value = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        return $value . (intdiv($minutes, 60) < 12 ? ' sáng' : ' chiều');
    }

    private function getClinicSlotBounds(): array
    {
        $pkModel = new ThongTinPhongKham();
        $pkInfo = $pkModel->getInfo();

        $openMin = $this->timeToMinutes($pkInfo['GioMoCua'] ?? '08:00:00');
        $closeMin = $this->timeToMinutes($pkInfo['GioDongCua'] ?? '17:00:00');

        return [$openMin, $closeMin - 60];
    }

    private function getDoctorShiftRanges(int $maNguoiDung, string $ngay): array
    {
        $shifts = Database::fetchAll(
            "SELECT c.MaCa, c.TenCa, c.GioBatDau, c.GioKetThuc
             FROM PhanCongCa pc
             INNER JOIN CaLamViec c ON pc.MaCa = c.MaCa
             WHERE pc.MaNguoiDung = ? AND pc.NgayLamViec = ?
             ORDER BY c.GioBatDau",
            [$maNguoiDung, $ngay]
        );

        return array_map(function (array $shift): array {
            return [
                'maCa' => (int)($shift['MaCa'] ?? 0),
                'tenCa' => $shift['TenCa'] ?? '',
                'gioBatDau' => substr((string)($shift['GioBatDau'] ?? ''), 0, 5),
                'gioKetThuc' => substr((string)($shift['GioKetThuc'] ?? ''), 0, 5),
                'startMin' => $this->timeToMinutes((string)($shift['GioBatDau'] ?? '00:00:00')),
                'endMin' => $this->timeToMinutes((string)($shift['GioKetThuc'] ?? '00:00:00')),
            ];
        }, $shifts);
    }

    private function getBookedSlotTimes(int $maNguoiDung, string $ngay): array
    {
        $bookedRows = Database::fetchAll(
            "SELECT CONVERT(VARCHAR(5), ThoiGianHen, 108) AS GioHen
             FROM LichHen
             WHERE MaNguoiDung = ?
               AND CAST(ThoiGianHen AS DATE) = ?
               AND TrangThai IN (0, 1)",
            [$maNguoiDung, $ngay]
        );

        $booked = [];
        foreach ($bookedRows as $row) {
            if (!empty($row['GioHen'])) {
                $booked[(string)$row['GioHen']] = true;
            }
        }

        return $booked;
    }

    private function getAvailableSlotsForDoctor(int $maNguoiDung, string $ngay): array
    {
        [$clinicOpenMin, $clinicLastSlotMin] = $this->getClinicSlotBounds();
        $shiftRanges = $this->getDoctorShiftRanges($maNguoiDung, $ngay);
        $bookedSlots = $this->getBookedSlotTimes($maNguoiDung, $ngay);
        $slots = [];

        $today = (new \DateTime())->format('Y-m-d');
        $currentMin = null;
        if ($ngay === $today) {
            $now = new \DateTime();
            $currentMin = ((int)$now->format('H') * 60) + (int)$now->format('i');
        }

        foreach ($shiftRanges as $shift) {
            $startMin = max($clinicOpenMin, (int)$shift['startMin']);
            $lastSlotMin = min($clinicLastSlotMin, (int)$shift['endMin'] - 60);

            for ($minutes = $startMin; $minutes <= $lastSlotMin; $minutes += 30) {
                if ($minutes >= 720 && $minutes < 780) {
                    continue;
                }

                if ($currentMin !== null && $minutes <= $currentMin) {
                    continue;
                }

                $value = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
                if (isset($bookedSlots[$value]) || isset($slots[$value])) {
                    continue;
                }

                $slots[$value] = [
                    'value' => $value,
                    'label' => $this->formatSlotLabel($minutes),
                    'caLam' => $shift['tenCa'],
                ];
            }
        }

        ksort($slots);
        return array_values($slots);
    }

    private function isDoctorSlotAvailable(int $maNguoiDung, string $ngay, string $gioHen): bool
    {
        foreach ($this->getAvailableSlotsForDoctor($maNguoiDung, $ngay) as $slot) {
            if (($slot['value'] ?? null) === $gioHen) {
                return true;
            }
        }

        return false;
    }

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
        [$openMin, $lastSlotMin] = $this->getClinicSlotBounds();

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

            $ngayHen = $dt->format('Y-m-d');
            $gioHen = $dt->format('H:i');
            $shiftRanges = $this->getDoctorShiftRanges($maNguoiDung, $ngayHen);

            if (empty($shiftRanges)) {
                $this->error('Bác sĩ này không có ca làm việc vào ngày đã chọn.', null, 400);
                return;
            }

            if (!$this->isDoctorSlotAvailable($maNguoiDung, $ngayHen, $gioHen)) {
                $this->error('Khung giờ đã chọn không thuộc ca làm việc khả dụng của bác sĩ hoặc đã được đặt.', null, 400);
                return;
            }

            // Giới hạn 8 bệnh nhân / bác sĩ / ngày
            $bnCount = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM LichHen WHERE MaNguoiDung = ? AND CAST(ThoiGianHen AS DATE) = ? AND TrangThai IN (0, 1)",
                [$maNguoiDung, $ngayHen]
            );
            if ($bnCount && (int)$bnCount['cnt'] >= 8) {
                $this->error('Bác sĩ đã đầy lịch trong ngày này (tối đa 8 bệnh nhân). Vui lòng chọn bác sĩ khác.', null, 400);
                return;
            }
        }

        // Tự động hủy toàn hệ thống lịch hẹn chưa xác nhận đã quá giờ hẹn
        LichHen::autoExpireOverdue();

        // Rate limit: tối đa 5 lịch hẹn đang chờ xử lý / SĐT (chỉ đếm tương lai)
        try {
            $pendingCount = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM LichHen lh
                 JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
                 WHERE bn.SoDienThoai = ? AND lh.TrangThai IN (0, 1)
                   AND lh.ThoiGianHen > GETDATE()",
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

            // Gửi email xác nhận cho user đã đăng nhập (best-effort, không chặn response nếu fail)
            $this->sendBookingConfirmationEmail(
                $currentUser,
                $result['MaLichHen'] ?? null,
                $dt,
                $maNguoiDung,
                $ghiChu
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
     * Gửi email xác nhận đặt lịch (best-effort).
     * Chỉ gửi nếu user đã đăng nhập và có email. Fail im lặng, log lỗi.
     */
    private function sendBookingConfirmationEmail(
        ?array $currentUser,
        $maLichHen,
        \DateTime $dt,
        ?int $maNguoiDungBacSi,
        ?string $ghiChu
    ): void {
        try {
            if (!$currentUser || empty($currentUser['Email']) || empty($maLichHen)) {
                return;
            }

            // Lấy tên bác sĩ nếu có chọn
            $tenBacSi = null;
            if ($maNguoiDungBacSi) {
                $bs = Database::fetchOne(
                    "SELECT HoTen FROM NguoiDung WHERE MaNguoiDung = ?",
                    [$maNguoiDungBacSi]
                );
                $tenBacSi = $bs['HoTen'] ?? null;
            }

            $emailService = $this->getEmailService();
            if (!$emailService) {
                error_log('BookingController: không khởi tạo được EmailService — bỏ qua gửi xác nhận.');
                return;
            }

            $emailService->sendBookingConfirmation([
                'to'          => $currentUser['Email'],
                'hoTen'       => $currentUser['HoTen'] ?? 'Khách hàng',
                'maLichHen'   => $maLichHen,
                'thoiGianHen' => $dt->format('d/m/Y H:i'),
                'tenBacSi'    => $tenBacSi,
                'ghiChu'      => $ghiChu,
                'clinic'      => $this->getClinicInfoForEmail(),
            ]);
        } catch (\Throwable $e) {
            error_log('Lỗi gửi email xác nhận đặt lịch: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thông tin phòng khám thực từ bảng ThongTinPhongKham để dùng trong email.
     * Trả mảng rỗng nếu không có / lỗi → template sẽ fallback về config.
     */
    private function getClinicInfoForEmail(): array
    {
        try {
            $info = (new ThongTinPhongKham())->getInfo();
            if (!$info) {
                return [];
            }
            return [
                'name'    => $info['TenPhongKham'] ?? null,
                'address' => $info['DiaChi'] ?? null,
                'phone'   => $info['SoDienThoai'] ?? null,
                'email'   => $info['Email'] ?? null,
                'website' => $info['Website'] ?? null,
            ];
        } catch (\Throwable $e) {
            error_log('Lỗi lấy ThongTinPhongKham cho email: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Khởi tạo EmailService lazy. Trả về null nếu lỗi.
     */
    private $emailService = null;
    private function getEmailService()
    {
        if ($this->emailService !== null) {
            return $this->emailService ?: null;
        }
        try {
            $email_config_path = __DIR__ . '/../../Config/email_config.php';
            if (!file_exists($email_config_path)) {
                error_log("Không tìm thấy email_config.php tại: $email_config_path");
                $this->emailService = false;
                return null;
            }
            require_once $email_config_path;

            $email_service_path = __DIR__ . '/../../Services/EmailService.php';
            if (!file_exists($email_service_path)) {
                error_log("Không tìm thấy EmailService.php tại: $email_service_path");
                $this->emailService = false;
                return null;
            }
            require_once $email_service_path;

            if (!isset($EMAIL_CONFIG) || empty($EMAIL_CONFIG)) {
                error_log('$EMAIL_CONFIG không được định nghĩa trong email_config.php');
                $this->emailService = false;
                return null;
            }

            $this->emailService = new \EmailService($EMAIL_CONFIG);
            return $this->emailService;
        } catch (\Throwable $e) {
            error_log('Lỗi khởi tạo EmailService trong BookingController: ' . $e->getMessage());
            $this->emailService = false;
            return null;
        }
    }

    /**
     * GET /api/booking/doctors?ngay=2026-04-17
     * Trả DS toàn bộ bác sĩ có ca làm việc trong ngày + số BN hiện tại (giới hạn 8).
     * Mỗi (bác sĩ, ca) có cờ `DaKetThuc` = true khi ca đã qua giờ hoặc đã kín slot.
     */
    public function doctors(): void
    {
        $ngay = $_GET['ngay'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            $this->error('Ngày không hợp lệ', null, 400);
            return;
        }

        $today = (new \DateTime())->format('Y-m-d');
        $isToday = ($ngay === $today);
        $isPast = ($ngay < $today);

        $currentTimeMin = null;
        if ($isToday) {
            $now = new \DateTime();
            $currentTimeMin = ((int)$now->format('H') * 60) + (int)$now->format('i');
        }

        // Lấy toàn bộ bác sĩ có phân công ca trong ngày + đếm BN đã hẹn (theo ngày)
        $rows = Database::fetchAll(
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

        [$clinicOpenMin, $clinicLastSlotMin] = $this->getClinicSlotBounds();
        $bookedCache = [];

        $doctors = [];
        foreach ($rows as $row) {
            $maNguoiDung = (int)$row['MaNguoiDung'];
            $startMin = $this->timeToMinutes((string)($row['GioBatDau'] ?? '00:00:00'));
            $endMin = $this->timeToMinutes((string)($row['GioKetThuc'] ?? '00:00:00'));

            $daKetThuc = false;
            if ($isPast) {
                $daKetThuc = true;
            } elseif ($isToday && $currentTimeMin !== null && $currentTimeMin >= $endMin) {
                $daKetThuc = true;
            } else {
                // Kiểm tra ca có còn slot trống không
                if (!isset($bookedCache[$maNguoiDung])) {
                    $bookedCache[$maNguoiDung] = $this->getBookedSlotTimes($maNguoiDung, $ngay);
                }
                $bookedSlots = $bookedCache[$maNguoiDung];

                $shiftStartMin = max($clinicOpenMin, $startMin);
                $shiftLastSlotMin = min($clinicLastSlotMin, $endMin - 60);

                $hasFree = false;
                for ($m = $shiftStartMin; $m <= $shiftLastSlotMin; $m += 30) {
                    if ($m >= 720 && $m < 780) {
                        continue; // bỏ giờ nghỉ trưa 12:00-13:00
                    }
                    if ($currentTimeMin !== null && $m <= $currentTimeMin) {
                        continue;
                    }
                    $value = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                    if (isset($bookedSlots[$value])) {
                        continue;
                    }
                    $hasFree = true;
                    break;
                }
                if (!$hasFree) {
                    $daKetThuc = true;
                }
            }

            $row['DaKetThuc'] = $daKetThuc;
            $doctors[] = $row;
        }

        $this->success([
            'doctors' => $doctors,
            'gioiHanBN' => 8,
            'chiCaHienTai' => false,
        ]);
    }

    /**
     * GET /api/booking/slots?ngay=2026-04-17&maNguoiDung=5
     * Trả danh sách khung giờ còn trống theo ca làm của bác sĩ trong ngày
     */
    public function slots(): void
    {
        $ngay = $_GET['ngay'] ?? '';
        $maNguoiDung = isset($_GET['maNguoiDung']) ? (int)$_GET['maNguoiDung'] : 0;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            $this->error('Ngày không hợp lệ', null, 400);
            return;
        }

        if ($maNguoiDung <= 0) {
            $this->error('Bác sĩ không hợp lệ', null, 400);
            return;
        }

        $doctor = Database::fetchOne(
            "SELECT MaNguoiDung, HoTen FROM NguoiDung WHERE MaNguoiDung = ? AND MaVaiTro = 2 AND TrangThaiTK = 1 AND IsDeleted = 0",
            [$maNguoiDung]
        );

        if (!$doctor) {
            $this->error('Bác sĩ không hợp lệ hoặc không còn hoạt động.', null, 400);
            return;
        }

        $shifts = $this->getDoctorShiftRanges($maNguoiDung, $ngay);

        $this->success([
            'doctor' => $doctor,
            'shifts' => array_map(function (array $shift): array {
                return [
                    'maCa' => $shift['maCa'],
                    'tenCa' => $shift['tenCa'],
                    'gioBatDau' => $shift['gioBatDau'],
                    'gioKetThuc' => $shift['gioKetThuc'],
                ];
            }, $shifts),
            'slots' => $this->getAvailableSlotsForDoctor($maNguoiDung, $ngay),
        ]);
    }
}
