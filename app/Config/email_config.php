<?php
/**
 * ============================================================
 * CẤU HÌNH EMAIL - DERMASOFT
 * ============================================================
 * File này chứa tất cả cấu hình SMTP để gửi email
 * 
 * Ngày tạo: 23/03/2026
 * Phiên bản: 1.0 Production
 * ============================================================
 */

// ============================================================
// PHẦN 1: CÁCH SỬ DỤNG
// ============================================================
/*
 * VÍ DỤ:
 * 
 * require_once __DIR__ . '/email_config.php';
 * require_once __DIR__ . '/EmailService.php';
 * 
 * $emailService = new EmailService(EMAIL_CONFIG);
 * 
 * $result = $emailService->sendOtpEmail([
 *     'to' => 'user@example.com',
 *     'otp' => '123456',
 *     'hoTen' => 'Nguyễn Văn A'
 * ]);
 * 
 * if ($result['success']) {
 *     echo "Email OTP gửi thành công!";
 * } else {
 *     echo "Lỗi: " . $result['message'];
 * }
 */

// ============================================================
// PHẦN 2: CẤU HÌNH EMAIL SERVER
// ============================================================

// CHỌN MỘT TRONG 3 LOẠI SERVER SAU:
define('EMAIL_PROVIDER', 'custom');  // 'gmail' | 'outlook' | 'custom'

// ============================================================
// OPTION 1: GMAIL SMTP
// ============================================================
define('GMAIL_SENDER', 'your-email@gmail.com');
define('GMAIL_SENDER_NAME', 'Phòng Khám DERMASOFT');
define('GMAIL_PASSWORD', 'your-app-password');  // Mật khẩu ứng dụng (không phải mật khẩu thường)

// ============================================================
// OPTION 2: OUTLOOK/MICROSOFT 365
// ============================================================
define('OUTLOOK_SENDER', 'your-email@outlook.com');
define('OUTLOOK_SENDER_NAME', 'Phòng Khám DERMASOFT');
define('OUTLOOK_PASSWORD', 'your-password');

// ============================================================
// OPTION 3: CUSTOM SMTP SERVER (Khuyến khích sử dụng)
// ============================================================
// ⚠️ CẤU HÌNH GMAIL SMTP — Thay bằng Gmail thật của bạn
// Bước 1: Bật 2FA tại https://myaccount.google.com/security
// Bước 2: Tạo App Password tại https://myaccount.google.com/apppasswords
// Bước 3: Điền email và App Password (16 ký tự) vào đây
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'latruong22061012@gmail.com');   // ← Thay bằng Gmail của bạn
define('SMTP_PASSWORD', 'fsxo qrbm qscl ngvd');    // ← App Password 16 ký tự
define('SMTP_SECURE', 'tls');
define('SMTP_FROM_EMAIL', 'latruong22061012@gmail.com'); // ← Giống SMTP_USERNAME
define('SMTP_FROM_NAME', 'Phòng Khám Da Liễu DERMASOFT');

// ============================================================
// PHẦN 3: THÔNG TIN PHÒNG KHÁM
// ============================================================
define('CLINIC_NAME', 'Phòng Khám Da Liễu DERMASOFT');
define('CLINIC_PHONE', '0900-123-456');
define('CLINIC_EMAIL', 'support@dermasoft.local');
define('CLINIC_WEBSITE', 'https://dermasoft.local');
define('CLINIC_ADDRESS', '123 Nguyễn Huệ, Quận 1, TP.HCM');

// ============================================================
// PHẦN 4: CẤU HÌNH EMAIL
// ============================================================

// Thời gian hết hạn OTP (giây)
define('OTP_EXPIRE_TIME', 5 * 60);  // 5 phút

// Thời gian hết hạn Email Confirmation Token (ngày)
define('EMAIL_TOKEN_EXPIRE_TIME', 24 * 60 * 60);  // 24 giờ

// Thử lại tối đa
define('MAX_RETRY_SEND_EMAIL', 3);

// Rate limiting: Giới hạn gửi email
define('EMAIL_RATE_LIMIT_PER_MINUTE', 5);   // 5 emails per minute
define('EMAIL_RATE_LIMIT_PER_HOUR', 50);    // 50 emails per hour
define('EMAIL_RATE_LIMIT_PER_DAY', 500);    // 500 emails per day

// ============================================================
// PHẦN 5: BỘ CẤU HÌNH CHÍNH (EMAIL_CONFIG)
// ============================================================

$EMAIL_CONFIG = [
    // Provider: gmail | outlook | custom
    'provider' => EMAIL_PROVIDER,
    
    // SMTP Server Configuration
    'smtp' => [
        'host'     => SMTP_HOST,
        'port'     => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'secure'   => SMTP_SECURE,  // 'tls' hoặc 'ssl'
    ],
    
    // Người gửi (From)
    'from' => [
        'email' => SMTP_FROM_EMAIL,
        'name'  => SMTP_FROM_NAME,
    ],
    
    // Thông tin phòng khám
    'clinic' => [
        'name'    => CLINIC_NAME,
        'phone'   => CLINIC_PHONE,
        'email'   => CLINIC_EMAIL,
        'website' => CLINIC_WEBSITE,
        'address' => CLINIC_ADDRESS,
    ],
    
    // Email templates
    'templates' => [
        'otp'                 => __DIR__ . '/../Templates/email_otp.html',
        'verify_email'        => __DIR__ . '/../Templates/email_verify.html',
        'password_reset'      => __DIR__ . '/../Templates/email_password_reset.html',
        'welcome'             => __DIR__ . '/../Templates/email_welcome.html',
        'update_phone'        => __DIR__ . '/../Templates/email_update_phone.html',
        'confirm_phone_change' => __DIR__ . '/../Templates/email_confirm_phone.html',
    ],
    
    // Cấu hình thời gian
    'timing' => [
        'otp_expire'              => OTP_EXPIRE_TIME,
        'email_token_expire'      => EMAIL_TOKEN_EXPIRE_TIME,
        'max_retry'               => MAX_RETRY_SEND_EMAIL,
        'rate_limit_per_minute'   => EMAIL_RATE_LIMIT_PER_MINUTE,
        'rate_limit_per_hour'     => EMAIL_RATE_LIMIT_PER_HOUR,
        'rate_limit_per_day'      => EMAIL_RATE_LIMIT_PER_DAY,
    ],
    
    // PHPMailer options
    'phpmailer' => [
        'debug'          => 0,           // 0=Không debug, 1=Errors, 2=Data, 3=All, 4=SMTP
        'charset'        => 'UTF-8',     // Hỗ trợ Tiếng Việt
        'language_path'  => __DIR__ . '/../Config/PHPMailer-master/PHPMailer-master/language/',
        'language'       => 'vi',        // Tiếng Việt
    ],
];

/**
 * Định hằng EMAIL_CONFIG toàn cục
 */
if (!defined('EMAIL_CONFIG')) {
    define('EMAIL_CONFIG', $EMAIL_CONFIG);
}

/**
 * ============================================================
 * PHẦN 6: HƯỚNG DẪN THIẾT LẬP
 * ============================================================
 * 
 * A. DÙNG GMAIL:
 *    1. Bật 2-Step Verification: https://accounts.google.com/security
 *    2. Tạo App Password: https://myaccount.google.com/apppasswords
 *    3. Copy app password vào GMAIL_PASSWORD
 *    4. Set EMAIL_PROVIDER = 'gmail'
 * 
 * B. DÙNG OUTLOOK/MICROSOFT 365:
 *    1. Kích hoạt tài khoản Outlook
 *    2. Set EMAIL_PROVIDER = 'outlook'
 *    3. Nhập email và mật khẩu
 * 
 * C. DÙNG CUSTOM SMTP (KHUYẾN KHÍCH):
 *    1. Cài đặt mail server (Sendmail, Postfix, etc.)
 *    2. Cung cấp SMTP_HOST, SMTP_PORT, etc.
 *    3. Set EMAIL_PROVIDER = 'custom'
 * 
 * ============================================================
 */

return $EMAIL_CONFIG;
