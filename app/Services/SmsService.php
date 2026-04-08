<?php
/**
 * ============================================================
 * SMSSERVICE - Dịch vụ gửi SMS OTP qua ESMS / SpeedSMS / Twilio
 * ============================================================
 */

class SmsService
{
    private $config;
    private $provider;

    public function __construct(array $config)
    {
        $this->config   = $config;
        $this->provider = $config['provider'] ?? 'esms';
    }

    /**
     * Gửi OTP qua SMS đến số điện thoại
     *
     * @param string $phone  Số điện thoại (VD: 0912345678)
     * @param string $otp    Mã OTP 6 chữ số
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendOtp(string $phone, string $otp): array
    {
        // Chuẩn hóa số điện thoại → định dạng quốc tế 84xxxxxxxxx
        $phone = $this->normalizePhone($phone);

        $content = "DERMASOFT: Ma OTP cua ban la $otp. Co hieu luc 5 phut. Khong cung cap cho bat ky ai.";

        switch ($this->provider) {
            case 'esms':
                return $this->sendViaEsms($phone, $content);
            case 'speedsms':
                return $this->sendViaSpeedSms($phone, $content);
            case 'twilio':
                return $this->sendViaTwilio($phone, $content);
            default:
                return $this->error("Provider không hỗ trợ: {$this->provider}");
        }
    }

    // ============================================================
    // ESMS.vn
    // ============================================================
    private function sendViaEsms(string $phone, string $content): array
    {
        $cfg = $this->config['esms'];

        if (empty($cfg['api_key']) || empty($cfg['secret_key'])) {
            return $this->error('ESMS chưa cấu hình API Key / Secret Key');
        }

        $payload = [
            'ApiKey'    => $cfg['api_key'],
            'Content'   => $content,
            'Phone'     => $phone,
            'SecretKey' => $cfg['secret_key'],
            'SmsType'   => $cfg['sms_type'] ?? 2,
            'IsUnicode' => 0,
            'Sandbox'   => 0,
        ];

        if (!empty($cfg['brandname'])) {
            $payload['Brandname'] = $cfg['brandname'];
        }

        $response = $this->httpPost($cfg['api_url'], json_encode($payload), [
            'Content-Type: application/json',
        ]);

        if ($response === false) {
            return $this->error('Không thể kết nối ESMS API');
        }

        $data = json_decode($response, true);

        // ESMS CodeResult: 100 = thành công
        if (isset($data['CodeResult']) && $data['CodeResult'] == '100') {
            return [
                'success'  => true,
                'message'  => 'SMS gửi thành công qua ESMS',
                'sms_id'   => $data['SMSID'] ?? null,
            ];
        }

        // CodeResult 103 = hết tiền trong tài khoản ESMS
        if (isset($data['CodeResult']) && $data['CodeResult'] == '103') {
            error_log('ESMS: Tài khoản hết tiền (CodeResult=103). Vui lòng nạp tiền tại esms.vn');
            return $this->error('ESMS: Tài khoản chưa có tiền. Vui lòng nạp tiền tại esms.vn');
        }

        $errMsg = $data['ErrorMessage'] ?? ($data['CodeResult'] ?? 'Lỗi không xác định');
        return $this->error("ESMS lỗi: $errMsg");
    }

    // ============================================================
    // SpeedSMS.vn
    // ============================================================
    private function sendViaSpeedSms(string $phone, string $content): array
    {
        $cfg = $this->config['speedsms'];

        if (empty($cfg['access_token'])) {
            return $this->error('SpeedSMS chưa cấu hình access_token');
        }

        $payload = [
            'to'      => [$phone],
            'content' => $content,
            'sms_type'=> $cfg['type'] ?? 2,
            'sender'  => $cfg['sender'] ?? '',
        ];

        $response = $this->httpPost(
            $cfg['api_url'],
            json_encode($payload),
            [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($cfg['access_token'] . ':x'),
            ]
        );

        if ($response === false) {
            return $this->error('Không thể kết nối SpeedSMS API');
        }

        $data = json_decode($response, true);

        if (isset($data['status']) && $data['status'] === 'success') {
            return ['success' => true, 'message' => 'SMS gửi thành công qua SpeedSMS'];
        }

        $errMsg = $data['message'] ?? 'Lỗi không xác định';
        return $this->error("SpeedSMS lỗi: $errMsg");
    }

    // ============================================================
    // Twilio
    // ============================================================
    private function sendViaTwilio(string $phone, string $content): array
    {
        $cfg = $this->config['twilio'];

        if (empty($cfg['account_sid']) || empty($cfg['auth_token']) || empty($cfg['from_number'])) {
            return $this->error('Twilio chưa cấu hình đầy đủ');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$cfg['account_sid']}/Messages.json";

        $payload = http_build_query([
            'To'   => '+' . ltrim($phone, '+'),
            'From' => $cfg['from_number'],
            'Body' => $content,
        ]);

        $response = $this->httpPost(
            $url,
            $payload,
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($cfg['account_sid'] . ':' . $cfg['auth_token']),
            ]
        );

        if ($response === false) {
            return $this->error('Không thể kết nối Twilio API');
        }

        $data = json_decode($response, true);

        if (!empty($data['sid'])) {
            return ['success' => true, 'message' => 'SMS gửi thành công qua Twilio'];
        }

        $errMsg = $data['message'] ?? 'Lỗi không xác định';
        return $this->error("Twilio lỗi: $errMsg");
    }

    // ============================================================
    // HELPER: HTTP POST dùng cURL
    // ============================================================
    private function httpPost(string $url, string $body, array $headers = [])
    {
        if (!function_exists('curl_init')) {
            error_log('SmsService: cURL không khả dụng');
            return false;
        }

        $ch = curl_init($url);
        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        // Dự phòng CA bundle cho XAMPP Windows
        $xamppCa = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
        if (file_exists($xamppCa)) {
            $curlOpts[CURLOPT_CAINFO] = $xamppCa;
        }
        curl_setopt_array($ch, $curlOpts);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $errmsg   = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            error_log("SmsService cURL error [$errno]: $errmsg");
            return false;
        }

        return $response;
    }

    // ============================================================
    // HELPER: Chuẩn hóa số điện thoại VN → 84xxxxxxxxx
    // ============================================================
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone); // Bỏ ký tự không phải số

        // 0xxxxxxxxx → 84xxxxxxxxx
        if (strlen($phone) === 10 && $phone[0] === '0') {
            return '84' . substr($phone, 1);
        }

        // Đã có 84 ở đầu
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '84') {
            return $phone;
        }

        return $phone;
    }

    private function error(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
