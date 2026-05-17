<?php
/**
 * ============================================================
 * CLI: Gửi email nhắc lịch hẹn trước 1 tiếng
 * ============================================================
 * Chạy định kỳ qua Windows Task Scheduler (mỗi 5 phút).
 * Xem hướng dẫn ở bin/README.md.
 *
 * Logic:
 *  - Quét LichHen có ThoiGianHen trong khoảng [now+55min, now+65min]
 *  - TrangThai IN (0, 1): cả chờ duyệt và đã xác nhận đều được nhắc
 *  - DaGuiNhacEmail = 0 (chưa gửi nhắc)
 *  - Bệnh nhân phải có tài khoản NguoiDung (match qua SoDienThoai) và Email
 *  - Sau khi gửi (thành công hoặc thất bại email) → set DaGuiNhacEmail=1
 *    để không gửi lại. Trường hợp thất bại email được ghi log để rà soát.
 * ============================================================
 */

declare(strict_types=1);

// Chỉ cho phép chạy từ CLI để tránh truy cập qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: script này chỉ chạy từ CLI.\n");
}

$ROOT = dirname(__DIR__);

// Log file riêng cho cron job
$LOG_FILE = $ROOT . '/app/reminder.log';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $LOG_FILE);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Ho_Chi_Minh');

function rlog(string $msg): void
{
    global $LOG_FILE;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($LOG_FILE, $line, FILE_APPEND);
    echo $line;
}

rlog('=== Bắt đầu chạy reminder job ===');

// Bootstrap autoloader (lấy từ index.php logic)
spl_autoload_register(function ($class) use ($ROOT) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Load DB config (định nghĩa hằng DB_*)
require_once $ROOT . '/app/Config/config.php';

// Load EmailService (global namespace)
require_once $ROOT . '/app/Config/email_config.php';
require_once $ROOT . '/app/Services/EmailService.php';

if (!isset($EMAIL_CONFIG) || empty($EMAIL_CONFIG)) {
    rlog('LỖI: $EMAIL_CONFIG không được định nghĩa. Dừng.');
    exit(1);
}

try {
    $emailService = new \EmailService($EMAIL_CONFIG);
} catch (\Throwable $e) {
    rlog('LỖI khởi tạo EmailService: ' . $e->getMessage());
    exit(1);
}

// Lấy thông tin phòng khám thực từ DB (bảng ThongTinPhongKham) để inject vào email.
// Nếu lỗi/không có → để mảng rỗng, template sẽ fallback về config.
$clinicInfo = [];
try {
    $row = \App\Core\Database::fetchOne(
        "SELECT TOP 1 TenPhongKham, DiaChi, SoDienThoai, Email, Website
         FROM ThongTinPhongKham
         ORDER BY MaThongTin DESC"
    );
    if ($row) {
        $clinicInfo = [
            'name'    => $row['TenPhongKham'] ?? null,
            'address' => $row['DiaChi'] ?? null,
            'phone'   => $row['SoDienThoai'] ?? null,
            'email'   => $row['Email'] ?? null,
            'website' => $row['Website'] ?? null,
        ];
        rlog('Đã nạp thông tin phòng khám: ' . ($clinicInfo['name'] ?? 'N/A'));
    } else {
        rlog('Không tìm thấy bản ghi ThongTinPhongKham — dùng fallback config.');
    }
} catch (\Throwable $e) {
    rlog('LỖI lấy ThongTinPhongKham: ' . $e->getMessage());
}

// Query: lịch hẹn sắp đến trong 55-65 phút tới, chưa gửi nhắc
// Match bệnh nhân với tài khoản NguoiDung qua SoDienThoai để lấy Email.
$sql = "
    SELECT
        lh.MaLichHen,
        lh.ThoiGianHen,
        lh.TrangThai,
        lh.GhiChu,
        lh.MaNguoiDung AS MaBacSi,
        bn.HoTen     AS BN_HoTen,
        bn.SoDienThoai AS BN_SoDienThoai,
        nd.Email     AS BN_Email,
        bs.HoTen     AS BS_HoTen
    FROM LichHen lh
    JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
    LEFT JOIN NguoiDung nd
        ON nd.SoDienThoai = bn.SoDienThoai AND nd.IsDeleted = 0
    LEFT JOIN NguoiDung bs
        ON bs.MaNguoiDung = lh.MaNguoiDung
    WHERE lh.DaGuiNhacEmail = 0
      AND lh.TrangThai IN (0, 1)
      AND lh.ThoiGianHen BETWEEN DATEADD(MINUTE, 55, GETDATE())
                             AND DATEADD(MINUTE, 65, GETDATE())
      AND nd.Email IS NOT NULL
      AND LEN(LTRIM(RTRIM(nd.Email))) > 0
";

try {
    $rows = \App\Core\Database::fetchAll($sql);
} catch (\Throwable $e) {
    rlog('LỖI truy vấn DB: ' . $e->getMessage());
    exit(1);
}

$total = count($rows);
rlog("Tìm được $total lịch hẹn cần gửi nhắc.");

if ($total === 0) {
    rlog('=== Kết thúc (không có lịch nào) ===');
    exit(0);
}

$ok = 0;
$fail = 0;

foreach ($rows as $row) {
    $maLichHen = (int)$row['MaLichHen'];
    $email = trim((string)$row['BN_Email']);
    $hoTen = $row['BN_HoTen'] ?? 'Khách hàng';

    // Parse ThoiGianHen → format đẹp + tính phút còn lại
    try {
        $dt = new \DateTime((string)$row['ThoiGianHen']);
        $thoiGianHenFmt = $dt->format('d/m/Y H:i');
        $phutConLai = (int)round(($dt->getTimestamp() - time()) / 60);
    } catch (\Throwable $e) {
        $thoiGianHenFmt = (string)$row['ThoiGianHen'];
        $phutConLai = 60;
    }

    $payload = [
        'to'          => $email,
        'hoTen'       => $hoTen,
        'maLichHen'   => $maLichHen,
        'thoiGianHen' => $thoiGianHenFmt,
        'tenBacSi'    => $row['BS_HoTen'] ?? null,
        'phutConLai'  => $phutConLai,
        'clinic'      => $clinicInfo,
    ];

    try {
        $result = $emailService->sendBookingReminder($payload);
        $success = (bool)($result['success'] ?? false);
    } catch (\Throwable $e) {
        $success = false;
        $result = ['message' => $e->getMessage()];
    }

    // Đánh dấu đã gửi bất kể thành công hay thất bại (tránh spam khi lỗi cấu hình)
    try {
        \App\Core\Database::query(
            'UPDATE LichHen SET DaGuiNhacEmail = 1 WHERE MaLichHen = ?',
            [$maLichHen]
        );
    } catch (\Throwable $e) {
        rlog("LỖI cập nhật DaGuiNhacEmail cho #$maLichHen: " . $e->getMessage());
    }

    if ($success) {
        $ok++;
        rlog("✓ Gửi nhắc #$maLichHen → $email ($thoiGianHenFmt)");
    } else {
        $fail++;
        rlog("✗ Lỗi gửi #$maLichHen → $email: " . ($result['message'] ?? 'unknown'));
    }
}

rlog("=== Kết thúc. Tổng: $total | Thành công: $ok | Thất bại: $fail ===");
exit(0);
