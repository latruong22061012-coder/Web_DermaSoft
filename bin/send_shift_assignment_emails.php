<?php
/**
 * ============================================================
 * CLI: Gửi email phân công / cập nhật / hủy ca làm việc
 * ============================================================
 * Chạy định kỳ qua Windows Task Scheduler (mỗi 5 phút).
 * Xem hướng dẫn ở bin/README.md.
 *
 * Logic:
 *  A) Phân công MỚI:
 *     - Quét PhanCongCa có DaGuiEmailPhanCong = 0
 *     - Chỉ xét NgayLamViec >= hôm nay (không gửi cho ca đã qua)
 *     - Nhân viên phải là Bác sĩ (MaVaiTro=2) hoặc Lễ tân (MaVaiTro=3)
 *       và có Email hợp lệ.
 *     - Sau khi gửi (thành công hay thất bại) → set DaGuiEmailPhanCong=1.
 *
 *  B) Cập nhật / Hủy ca (từ bảng PhanCongCa_AuditEmail):
 *     - Quét audit có DaGuiEmail = 0, NgayTao trong 24h gần nhất
 *       (để tránh spam backlog khi mới migrate).
 *     - Với SUA: lấy ca cũ + ca mới để render email so sánh.
 *     - Với XOA: chỉ có ca cũ.
 *     - Sau khi gửi → set DaGuiEmail=1.
 *
 *  Lưu ý: Vì WinApp ghi thẳng vào DB, kiến trúc này KHÔNG cần
 *  WinApp gọi PHP. Trigger DB sẽ ghi vào audit; cron polling chạy
 *  độc lập.
 * ============================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: script này chỉ chạy từ CLI.\n");
}

$ROOT = dirname(__DIR__);

$LOG_FILE = $ROOT . '/app/shift_email.log';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $LOG_FILE);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Ho_Chi_Minh');

function slog(string $msg): void
{
    global $LOG_FILE;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($LOG_FILE, $line, FILE_APPEND);
    echo $line;
}

slog('=== Bắt đầu chạy shift email job ===');

// Bootstrap autoloader giống index.php
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

require_once $ROOT . '/app/Config/config.php';
require_once $ROOT . '/app/Config/email_config.php';
require_once $ROOT . '/app/Services/EmailService.php';

if (!isset($EMAIL_CONFIG) || empty($EMAIL_CONFIG)) {
    slog('LỖI: $EMAIL_CONFIG không được định nghĩa. Dừng.');
    exit(1);
}

try {
    $emailService = new \EmailService($EMAIL_CONFIG);
} catch (\Throwable $e) {
    slog('LỖI khởi tạo EmailService: ' . $e->getMessage());
    exit(1);
}

// ── Lấy thông tin phòng khám một lần ──────────────────────────
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
        slog('Đã nạp thông tin phòng khám: ' . ($clinicInfo['name'] ?? 'N/A'));
    } else {
        slog('Không tìm thấy ThongTinPhongKham — dùng fallback config.');
    }
} catch (\Throwable $e) {
    slog('LỖI lấy ThongTinPhongKham: ' . $e->getMessage());
}

// Helper: tên thứ trong tuần tiếng Việt
$weekdayMap = [
    1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm',
    5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ Nhật',
];
function vnWeekday(?string $dateStr, array $map): string
{
    if (!$dateStr) return '';
    try {
        $dt = new \DateTime($dateStr);
        $iso = (int)$dt->format('N'); // 1=Mon ... 7=Sun
        return $map[$iso] ?? '';
    } catch (\Throwable $e) {
        return '';
    }
}

function fmtDate(?string $dateStr): string
{
    if (!$dateStr) return '';
    try {
        return (new \DateTime($dateStr))->format('d/m/Y');
    } catch (\Throwable $e) {
        return (string)$dateStr;
    }
}

function fmtTime(?string $timeStr): string
{
    if (!$timeStr) return '';
    // SQL Server TIME thường về 'HH:MM:SS' hoặc 'HH:MM:SS.nnnnnnn'
    return substr((string)$timeStr, 0, 5);
}

$totalOk = 0;
$totalFail = 0;

// ════════════════════════════════════════════════════════════════
// PHASE A: Phân công MỚI (cờ trên PhanCongCa)
// ════════════════════════════════════════════════════════════════
$sqlMoi = "
    SELECT
        pc.MaPhanCong,
        pc.MaNguoiDung,
        pc.MaCa,
        pc.NgayLamViec,
        c.TenCa,
        c.GioBatDau,
        c.GioKetThuc,
        nd.HoTen,
        nd.Email,
        nd.MaVaiTro,
        vt.TenVaiTro
    FROM PhanCongCa pc
    INNER JOIN NguoiDung nd ON nd.MaNguoiDung = pc.MaNguoiDung
    INNER JOIN CaLamViec  c ON c.MaCa         = pc.MaCa
    LEFT  JOIN VaiTro     vt ON vt.MaVaiTro   = nd.MaVaiTro
    WHERE pc.DaGuiEmailPhanCong = 0
      AND pc.NgayLamViec >= CAST(GETDATE() AS DATE)
      AND nd.MaVaiTro IN (2, 3)
      AND nd.IsDeleted = 0
      AND nd.Email IS NOT NULL
      AND LEN(LTRIM(RTRIM(nd.Email))) > 0
";

try {
    $rowsMoi = \App\Core\Database::fetchAll($sqlMoi);
} catch (\Throwable $e) {
    slog('LỖI truy vấn PhanCongCa: ' . $e->getMessage());
    $rowsMoi = [];
}

$cntMoi = count($rowsMoi);
slog("[MOI] Tìm được $cntMoi bản ghi cần gửi email phân công.");

foreach ($rowsMoi as $r) {
    $maPC = (int)$r['MaPhanCong'];
    $email = trim((string)$r['Email']);
    $hoTen = (string)($r['HoTen'] ?? 'Nhân viên');

    $payload = [
        'to'           => $email,
        'hoTen'        => $hoTen,
        'vaiTro'       => (string)($r['TenVaiTro'] ?? 'Nhân viên'),
        'tenCa'        => (string)($r['TenCa'] ?? ''),
        'gioBatDau'    => fmtTime($r['GioBatDau'] ?? null),
        'gioKetThuc'   => fmtTime($r['GioKetThuc'] ?? null),
        'ngayLamViec'  => fmtDate($r['NgayLamViec'] ?? null),
        'thuTrongTuan' => vnWeekday($r['NgayLamViec'] ?? null, $weekdayMap),
        'loai'         => 'MOI',
        'clinic'       => $clinicInfo,
    ];

    try {
        $result = $emailService->sendShiftAssignment($payload);
        $success = (bool)($result['success'] ?? false);
    } catch (\Throwable $e) {
        $success = false;
        $result = ['message' => $e->getMessage()];
    }

    // Đánh dấu đã gửi để không thử lại (tránh spam khi lỗi cấu hình)
    try {
        \App\Core\Database::query(
            'UPDATE PhanCongCa SET DaGuiEmailPhanCong = 1 WHERE MaPhanCong = ?',
            [$maPC]
        );
    } catch (\Throwable $e) {
        slog("LỖI cập nhật DaGuiEmailPhanCong cho #$maPC: " . $e->getMessage());
    }

    if ($success) {
        $totalOk++;
        slog("[MOI] ✓ #$maPC → $email | {$payload['tenCa']} ({$payload['ngayLamViec']})");
    } else {
        $totalFail++;
        slog("[MOI] ✗ #$maPC → $email | " . ($result['message'] ?? 'unknown'));
    }
}

// ════════════════════════════════════════════════════════════════
// PHASE B: Cập nhật & Hủy ca (từ audit table)
// ════════════════════════════════════════════════════════════════
$sqlAudit = "
    SELECT
        a.MaAudit,
        a.LoaiThaoTac,
        a.MaNguoiDung,
        a.MaCaCu,
        a.NgayLamViecCu,
        a.MaCaMoi,
        a.NgayLamViecMoi,
        nd.HoTen,
        nd.Email,
        nd.MaVaiTro,
        vt.TenVaiTro,
        cc.TenCa      AS TenCaCu,
        cc.GioBatDau  AS GioBatDauCu,
        cc.GioKetThuc AS GioKetThucCu,
        cm.TenCa      AS TenCaMoi,
        cm.GioBatDau  AS GioBatDauMoi,
        cm.GioKetThuc AS GioKetThucMoi
    FROM PhanCongCa_AuditEmail a
    INNER JOIN NguoiDung  nd ON nd.MaNguoiDung = a.MaNguoiDung
    LEFT  JOIN VaiTro     vt ON vt.MaVaiTro    = nd.MaVaiTro
    LEFT  JOIN CaLamViec  cc ON cc.MaCa        = a.MaCaCu
    LEFT  JOIN CaLamViec  cm ON cm.MaCa        = a.MaCaMoi
    WHERE a.DaGuiEmail = 0
      AND a.NgayTao >= DATEADD(HOUR, -24, GETDATE())
      AND nd.MaVaiTro IN (2, 3)
      AND nd.IsDeleted = 0
      AND nd.Email IS NOT NULL
      AND LEN(LTRIM(RTRIM(nd.Email))) > 0
";

try {
    $rowsAudit = \App\Core\Database::fetchAll($sqlAudit);
} catch (\Throwable $e) {
    slog('LỖI truy vấn PhanCongCa_AuditEmail: ' . $e->getMessage());
    $rowsAudit = [];
}

$cntAudit = count($rowsAudit);
slog("[SUA/XOA] Tìm được $cntAudit bản ghi audit cần gửi email.");

foreach ($rowsAudit as $r) {
    $maAudit = (int)$r['MaAudit'];
    $loai    = strtoupper((string)$r['LoaiThaoTac']); // SUA | XOA
    $email   = trim((string)$r['Email']);
    $hoTen   = (string)($r['HoTen'] ?? 'Nhân viên');

    if ($loai === 'SUA') {
        // Hiển thị thông tin MỚI làm chính, kèm ca cũ để so sánh
        $payload = [
            'to'           => $email,
            'hoTen'        => $hoTen,
            'vaiTro'       => (string)($r['TenVaiTro'] ?? 'Nhân viên'),
            'tenCa'        => (string)($r['TenCaMoi'] ?? ''),
            'gioBatDau'    => fmtTime($r['GioBatDauMoi'] ?? null),
            'gioKetThuc'   => fmtTime($r['GioKetThucMoi'] ?? null),
            'ngayLamViec'  => fmtDate($r['NgayLamViecMoi'] ?? null),
            'thuTrongTuan' => vnWeekday($r['NgayLamViecMoi'] ?? null, $weekdayMap),
            'loai'         => 'SUA',
            'caCu'         => [
                'tenCa'       => (string)($r['TenCaCu'] ?? ''),
                'gioBatDau'   => fmtTime($r['GioBatDauCu'] ?? null),
                'gioKetThuc'  => fmtTime($r['GioKetThucCu'] ?? null),
                'ngayLamViec' => fmtDate($r['NgayLamViecCu'] ?? null),
            ],
            'clinic'       => $clinicInfo,
        ];
    } else { // XOA
        $payload = [
            'to'           => $email,
            'hoTen'        => $hoTen,
            'vaiTro'       => (string)($r['TenVaiTro'] ?? 'Nhân viên'),
            'tenCa'        => (string)($r['TenCaCu'] ?? ''),
            'gioBatDau'    => fmtTime($r['GioBatDauCu'] ?? null),
            'gioKetThuc'   => fmtTime($r['GioKetThucCu'] ?? null),
            'ngayLamViec'  => fmtDate($r['NgayLamViecCu'] ?? null),
            'thuTrongTuan' => vnWeekday($r['NgayLamViecCu'] ?? null, $weekdayMap),
            'loai'         => 'XOA',
            'clinic'       => $clinicInfo,
        ];
    }

    try {
        $result = $emailService->sendShiftAssignment($payload);
        $success = (bool)($result['success'] ?? false);
    } catch (\Throwable $e) {
        $success = false;
        $result = ['message' => $e->getMessage()];
    }

    try {
        \App\Core\Database::query(
            'UPDATE PhanCongCa_AuditEmail SET DaGuiEmail = 1 WHERE MaAudit = ?',
            [$maAudit]
        );
    } catch (\Throwable $e) {
        slog("LỖI cập nhật DaGuiEmail cho audit #$maAudit: " . $e->getMessage());
    }

    if ($success) {
        $totalOk++;
        slog("[$loai] ✓ audit#$maAudit → $email | {$payload['tenCa']} ({$payload['ngayLamViec']})");
    } else {
        $totalFail++;
        slog("[$loai] ✗ audit#$maAudit → $email | " . ($result['message'] ?? 'unknown'));
    }
}

slog("=== Kết thúc. OK: $totalOk | FAIL: $totalFail ===");
exit(0);
