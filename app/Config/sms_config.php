<?php
/**
 * ============================================================
 * CẤU HÌNH SMS GATEWAY - ESMS.vn
 * ============================================================
 *
 * Hướng dẫn lấy API Key:
 *   1. Đăng ký tài khoản tại https://esms.vn
 *   2. Vào Dashboard → Cài đặt → API → lấy ApiKey + SecretKey
 *   3. Điền vào bên dưới
 *
 * Brandname (tên hiển thị trên SMS):
 *   - Cần đăng ký với ESMS (mất ~1-3 ngày duyệt)
 *   - Hoặc dùng SmsType = 2 (không cần Brandname, nhưng hiện số lạ)
 *
 * SmsType:
 *   2 = SMS thường (không cần Brandname, rẻ hơn)
 *   4 = SMS Brandname quảng cáo (cần đăng ký Brandname)
 *   8 = SMS Brandname chăm sóc khách hàng (cần đăng ký Brandname)
 * ============================================================
 */

$SMS_CONFIG = [
    'provider' => 'esms',   // esms | speedsms | twilio

    // ── ESMS.vn ──────────────────────────────────────────────
    'esms' => [
        'api_key'    => 'EB9A4C7F8FEEE7DD2B59959386FED0',          // TODO: Điền ApiKey từ esms.vn
        'secret_key' => '11537F37A3EAC3E0676AAD61087A87',          // TODO: Điền SecretKey từ esms.vn
        'brandname'  => '', // Để trống nếu chưa đăng ký Brandname (sms_type=2 không cần)
        'sms_type'   => 2,           // 2 = thường, 4 = quảng cáo, 8 = CSKH
        'api_url'    => 'https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post_json/',
    ],

    // ── SpeedSMS.vn (để trống nếu không dùng) ────────────────
    'speedsms' => [
        'access_token' => '',        // TODO: Điền access token từ speedsms.vn
        'sender'       => 'DERMASOFT',
        'type'         => 2,
        'api_url'      => 'https://api.speedsms.vn/index.php/sms/send',
    ],

    // ── Twilio (để trống nếu không dùng) ─────────────────────
    'twilio' => [
        'account_sid'  => '',        // TODO: Điền Account SID từ twilio.com
        'auth_token'   => '',        // TODO: Điền Auth Token từ twilio.com
        'from_number'  => '',        // Số điện thoại Twilio (VD: +12345678901)
    ],
];
