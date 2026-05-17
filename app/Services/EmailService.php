<?php
/**
 * ============================================================
 * EMAILSERVICE - Dịch vụ gửi email qua PHPMailer
 * ============================================================
 * 
 * Mục đích: Gửi email OTP, xác thực tài khoản, thay đổi mật khẩu
 * Sử dụng: PHPMailer library
 * 
 * Ngày tạo: 23/03/2026
 * Phiên bản: 1.0 Production
 * 
 * ============================================================
 */

// Load PHPMailer library
require_once __DIR__ . '/../Config/PHPMailer-master/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../Config/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../Config/PHPMailer-master/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    /**
     * PHPMailer instance
     * @var PHPMailer
     */
    private $mail;
    
    /**
     * Email configuration
     * @var array
     */
    private $config;
    
    /**
     * Log email sending history
     * @var array
     */
    private $log = [];
    
    /**
     * Constructor - Khởi tạo EmailService
     * 
     * @param array $config Cấu hình email từ email_config.php
     * @throws Exception
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->initializeMailer();
    }
    
    /**
     * Khởi tạo PHPMailer với cấu hình
     * @throws Exception
     */
    private function initializeMailer()
    {
        $this->mail = new PHPMailer(true);
        
        try {
            // ============================================================
            // CẤU HÌNH PHPMailer
            // ============================================================
            
            // Sử dụng SMTP
            $this->mail->isSMTP();
            
            // SMTP server
            $this->mail->Host = $this->config['smtp']['host'];
            $this->mail->Port = $this->config['smtp']['port'];
            
            // SMTP authentication
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config['smtp']['username'];
            $this->mail->Password = $this->config['smtp']['password'];
            
            // SMTP security (TLS hoặc SSL)
            if ($this->config['smtp']['secure'] === 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // ============================================================
            // CẤUE HÌNH PHIÊN BẢN NGÔN NGỮ & DEBUG
            // ============================================================
            
            // Set character encoding to UTF-8 (hỗ trợ Tiếng Việt)
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            
            // Debug mode (0 = không debug, 1-4 = tăng mức độ debug)
            $this->mail->SMTPDebug = $this->config['phpmailer']['debug'];
            
            // Ngôn ngữ lỗi
            if (!empty($this->config['phpmailer']['language_path'])) {
                $this->mail->setLanguage(
                    $this->config['phpmailer']['language'],
                    $this->config['phpmailer']['language_path']
                );
            }
            
            // Từ người gửi
            $this->mail->setFrom(
                $this->config['from']['email'],
                $this->config['from']['name']
            );
            
        } catch (Exception $e) {
            throw new Exception("Lỗi khởi tạo PHPMailer: " . $e->getMessage());
        }
    }
    
    /**
     * ============================================================
     * HÀM GỬI EMAIL OTP
     * ============================================================
     * 
     * Gửi email chứa mã OTP 6 chữ số để xác thực
     * 
     * @param array $data Chứa:
     *                    - 'to': Email người nhận
     *                    - 'otp': Mã OTP (6 chữ số)
     *                    - 'hoTen': Tên người nhận (tùy chọn)
     * 
     * @return array ['success' => bool, 'message' => string, 'timestamp' => datetime]
     */
    public function sendOtpEmail($data)
    {
        try {
            // ============================================================
            // KIỂM TRA DỮ LIỆU ĐẦU VÀO
            // ============================================================
            
            if (empty($data['to'])) {
                return $this->errorResponse('Email người nhận không được để trống');
            }
            
            if (empty($data['otp']) || strlen($data['otp']) !== 6 || !is_numeric($data['otp'])) {
                return $this->errorResponse('OTP phải là 6 chữ số');
            }
            
            // ============================================================
            // THIẾT LẬP EMAIL
            // ============================================================
            
            // Xóa recipients cũ
            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();
            
            // Người nhận
            $this->mail->addAddress($data['to'], $data['hoTen'] ?? 'Khách hàng');
            
            // Reply-to
            $this->mail->addReplyTo($this->config['clinic']['email'], $this->config['clinic']['name']);
            
            // ============================================================
            // NỘI DUNG EMAIL
            // ============================================================
            
            $this->mail->Subject = 'Mã OTP xác thực tài khoản DERMASOFT';
            $this->mail->isHTML(true);
            
            // HTML Email Body
            $htmlBody = $this->getOtpEmailTemplate($data);
            $this->mail->Body = $htmlBody;
            
            // Plain text alternative
            $this->mail->AltBody = "Mã OTP của bạn: " . $data['otp'] . "\n\n"
                                  . "Hết hạn trong 5 phút.\n\n"
                                  . "Nếu bạn không yêu cầu, hãy bỏ qua email này.";
            
            // ============================================================
            // GỬI EMAIL
            // ============================================================
            
            if (!$this->mail->send()) {
                return $this->errorResponse('Lỗi gửi email: ' . $this->mail->ErrorInfo);
            }
            
            // ============================================================
            // GHI LOG
            // ============================================================
            
            $logEntry = [
                'type'      => 'OTP',
                'to'        => $data['to'],
                'status'    => 'SUCCESS',
                'timestamp' => date('Y-m-d H:i:s'),
                'otp'       => $data['otp'],
                'hoTen'     => $data['hoTen'] ?? 'Unknown',
            ];
            $this->addLog($logEntry);
            
            return [
                'success'   => true,
                'message'   => 'Email OTP gửi thành công',
                'timestamp' => date('Y-m-d H:i:s'),
                'to'        => $data['to'],
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse('Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * ============================================================
     * HÀM GỬI EMAIL XÁC THỰC TÀI KHOẢN
     * ============================================================
     * 
     * Gửi email chứa link xác thực tài khoản
     * 
     * @param array $data:
     *               - 'to': Email người nhận
     *               - 'hoTen': Tên người nhận
     *               - 'verifyLink': Link xác thực (với token)
     *               - 'expireTime': Thời gian hết hạn (phút)
     * 
     * @return array
     */
    public function sendVerifyEmailLink($data)
    {
        try {
            if (empty($data['to'])) {
                return $this->errorResponse('Email người nhận không được để trống');
            }
            
            if (empty($data['verifyLink'])) {
                return $this->errorResponse('Link xác thực không được để trống');
            }
            
            // Thiết lập email
            $this->mail->clearAddresses();
            $this->mail->addAddress($data['to'], $data['hoTen'] ?? 'Khách hàng');
            
            $this->mail->Subject = 'Xác thực địa chỉ email - DERMASOFT';
            $this->mail->isHTML(true);
            
            $htmlBody = $this->getVerifyEmailTemplate($data);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = "Xác thực email của bạn: " . $data['verifyLink'];
            
            if (!$this->mail->send()) {
                return $this->errorResponse('Lỗi gửi email: ' . $this->mail->ErrorInfo);
            }
            
            return [
                'success'   => true,
                'message'   => 'Email xác thực gửi thành công',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse('Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * ============================================================
     * HÀM GỬI EMAIL THAY ĐỔI MẬT KHẨU
     * ============================================================
     * 
     * @param array $data:
     *               - 'to': Email người nhận
     *               - 'hoTen': Tên người nhận
     *               - 'resetLink': Link reset mật khẩu
     * 
     * @return array
     */
    public function sendPasswordResetEmail($data)
    {
        try {
            if (empty($data['to'])) {
                return $this->errorResponse('Email người nhận không được để trống');
            }
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($data['to'], $data['hoTen'] ?? 'Khách hàng');
            
            $this->mail->Subject = 'Đặt lại mật khẩu - DERMASOFT';
            $this->mail->isHTML(true);
            
            $htmlBody = $this->getPasswordResetTemplate($data);
            $this->mail->Body = $htmlBody;
            
            if (!$this->mail->send()) {
                return $this->errorResponse('Lỗi gửi email: ' . $this->mail->ErrorInfo);
            }
            
            return [
                'success'   => true,
                'message'   => 'Email đặt lại mật khẩu gửi thành công',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse('Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Gửi email thông tin số điện thoại (ẩn một phần) khi người dùng quên
     *
     * @param array $data ['to', 'hoTen', 'maskedPhone']
     * @return array ['success' => bool, 'message' => string, 'timestamp' => datetime]
     */
    public function sendPhoneInfoEmail($data)
    {
        try {
            if (empty($data['to'])) {
                return $this->errorResponse('Email người nhận không được để trống');
            }

            $this->mail->clearAddresses();
            $this->mail->addAddress($data['to'], $data['hoTen'] ?? 'Khách hàng');

            $this->mail->Subject = 'Thông tin số điện thoại - DERMASOFT';
            $this->mail->isHTML(true);

            $hoTen       = htmlspecialchars($data['hoTen'] ?? 'Khách hàng');
            $maskedPhone = htmlspecialchars($data['maskedPhone']);
            $clinicName  = htmlspecialchars($this->config['clinic']['name']);

            $this->mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông tin số điện thoại</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 3px solid #0d6efd; padding-bottom: 20px; margin-bottom: 20px; }
        .phone-box { background: #f0f7ff; border: 2px solid #0d6efd; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .phone-code { font-size: 28px; font-weight: bold; color: #0d6efd; letter-spacing: 3px; font-family: monospace; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0; color: #856404; }
        .footer { text-align: center; color: #999; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>&#128241; Thông Tin Số Điện Thoại</h1>
            <div style="color:#0d6efd;">$clinicName</div>
        </div>
        <p>Chào <strong>$hoTen</strong>,</p>
        <p>Theo yêu cầu khôi phục tài khoản, đây là số điện thoại đã đăng ký:</p>
        <div class="phone-box">
            <div class="phone-code">$maskedPhone</div>
            <div style="color:#999;font-size:12px;margin-top:8px;">Số điện thoại được ẩn một phần để bảo mật</div>
        </div>
        <div class="warning">
            <strong>&#9888; Lưu ý:</strong> Nếu bạn không yêu cầu email này, vui lòng bỏ qua hoặc liên hệ hỗ trợ ngay.
        </div>
        <div class="footer">
            <p>&#169; 2026 $clinicName. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

            if (!$this->mail->send()) {
                return $this->errorResponse('Lỗi gửi email: ' . $this->mail->ErrorInfo);
            }

            return [
                'success'   => true,
                'message'   => 'Email thông tin số điện thoại gửi thành công',
                'timestamp' => date('Y-m-d H:i:s'),
            ];

        } catch (Exception $e) {
            return $this->errorResponse('Exception: ' . $e->getMessage());
        }
    }

    /**
     * ============================================================
     * TEMPLATE EMAIL: OTP
     * ============================================================
     */
    private function getOtpEmailTemplate($data)
    {
        $otp = $data['otp'];
        $hoTen = $data['hoTen'] ?? 'Khách hàng';
        $clinicName = $this->config['clinic']['name'];
        
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã OTP xác thực</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { color: #333; margin: 0; }
        .clinic-name { color: #007bff; font-size: 14px; margin-top: 5px; }
        .content { color: #555; line-height: 1.6; }
        .otp-box { background: #f0f7ff; border: 2px solid #007bff; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .otp-code { font-size: 36px; font-weight: bold; color: #007bff; letter-spacing: 5px; font-family: monospace; }
        .expire-time { color: #999; font-size: 12px; margin-top: 10px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0; color: #856404; }
        .footer { text-align: center; color: #999; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; }
        .footer a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Xác Thực Tài Khoản</h1>
            <div class="clinic-name">$clinicName</div>
        </div>
        
        <div class="content">
            <p>Xin chào <strong>$hoTen</strong>,</p>
            
            <p>Mã OTP để xác thực tài khoản của bạn là:</p>
            
            <div class="otp-box">
                <div class="otp-code">$otp</div>
                <div class="expire-time">Hết hạn trong 5 phút</div>
            </div>
            
            <p>
                Vui lòng nhập mã OTP này vào ứng dụng để:.
                <ul>
                    <li>Xác thực tài khoản của bạn</li>
                    <li>Đăng nhập vào hệ thống</li>
                    <li>Hoàn tất các giao dịch</li>
                </ul>
            </p>
            
            <div class="warning">
                <strong>⚠️ Lưu ý:</strong> Nếu bạn không yêu cầu mã OTP này, vui lòng bỏ qua email này hoặc liên hệ support immediately.
            </div>
            
            <p>
                Với lý do bảo mật, chúng tôi không bao giờ yêu cầu OTP qua điện thoại hoặc email khác.
            </p>
        </div>
        
        <div class="footer">
            <p>
                💬 Hỗ trợ: <a href="mailto:{$this->config['clinic']['email']}">{$this->config['clinic']['email']}</a>
                | ☎️ {$this->config['clinic']['phone']}
            </p>
            <p>
                Địa chỉ: {$this->config['clinic']['address']}
            </p>
            <p style="color: #ccc;">
                © 2026 {$this->config['clinic']['name']}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * ============================================================
     * TEMPLATE EMAIL: XÁC THỰC EMAIL
     * ============================================================
     */
    private function getVerifyEmailTemplate($data)
    {
        $verifyLink = $data['verifyLink'];
        $clinicName = $this->config['clinic']['name'];
        $expireTime = $data['expireTime'] ?? 24;
        
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác thực Email</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; padding: 30px; }
        .header { text-align: center; color: #007bff; margin-bottom: 30px; }
        .btn { background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
        .footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✉️ Xác Thực Địa Chỉ Email</h1>
        </div>
        
        <p>Chào bạn,</p>
        <p>Cảm ơn bạn đã đăng ký tài khoản tại $clinicName.</p>
        <p>Vui lòng nhấn nút bên dưới để xác thực email của bạn:</p>
        
        <div style="text-align: center;">
            <a href="$verifyLink" class="btn">Xác Thực Email</a>
        </div>
        
        <p>Link này có hiệu lực trong $expireTime giờ.</p>
        <p>Nếu bạn không yêu cầu, vui lòng bỏ qua email này.</p>
        
        <div class="footer">
            <p>Các câu hỏi? Liên hệ: {$this->config['clinic']['email']}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * ============================================================
     * TEMPLATE EMAIL: RESET MẬT KHẨU
     * ============================================================
     */
    private function getPasswordResetTemplate($data)
    {
        $resetLink = $data['resetLink'];
        $clinicName = $this->config['clinic']['name'];
        
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại Mật khẩu</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; padding: 30px; }
        .header { color: #dc3545; margin-bottom: 30px; }
        .btn { background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Đặt Lại Mật Khẩu</h1>
        </div>
        
        <p>Chào bạn,</p>
        <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu tài khoản $clinicName của bạn.</p>
        
        <div style="text-align: center;">
            <a href="$resetLink" class="btn">Đặt Lại Mật Khẩu</a>
        </div>
        
        <div class="warning">
            <strong>⚠️ Lưu ý bảo mật:</strong>
            <ul>
                <li>Link này chỉ có hiệu lực trong 1 giờ</li>
                <li>Nếu bạn không yêu cầu, hãy bỏ qua email này</li>
                <li>Không cung cấp link này cho người khác</li>
            </ul>
        </div>
        
        <div style="text-align: center; color: #999; font-size: 12px; margin-top: 30px;">
            <p>© 2026 $clinicName. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * ============================================================
     * HÀM GỬI EMAIL XÁC NHẬN ĐẶT LỊCH (gửi ngay sau khi đặt)
     * ============================================================
     *
     * @param array $data:
     *               - 'to': Email người nhận
     *               - 'hoTen': Tên bệnh nhân
     *               - 'maLichHen': Mã lịch hẹn
     *               - 'thoiGianHen': Chuỗi 'd/m/Y H:i'
     *               - 'tenBacSi': (optional) Tên bác sĩ
     *               - 'ghiChu': (optional) Ghi chú
     * @return array
     */
    public function sendBookingConfirmation($data)
    {
        try {
            if (empty($data['to'])) {
                return $this->errorResponse('Email người nhận không được để trống');
            }

            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();
            $this->mail->addAddress($data['to'], $data['hoTen'] ?? 'Khách hàng');
            $this->mail->addReplyTo($this->config['clinic']['email'], $this->config['clinic']['name']);

            $this->mail->Subject = 'Xác nhận đặt lịch hẹn - DERMASOFT';
            $this->mail->isHTML(true);
            $this->mail->Body = $this->getBookingConfirmationTemplate($data);
            $this->mail->AltBody =
                "Xin chào " . ($data['hoTen'] ?? 'Khách hàng') . ",\n\n"
                . "Bạn đã đặt lịch thành công.\n"
                . "Mã lịch hẹn: #" . ($data['maLichHen'] ?? '') . "\n"
                . "Thời gian: " . ($data['thoiGianHen'] ?? '') . "\n"
                . (!empty($data['tenBacSi']) ? "Bác sĩ: " . $data['tenBacSi'] . "\n" : '')
                . "Phòng khám sẽ liên hệ xác nhận trong 30 phút.";

            if (!$this->mail->send()) {
                return $this->errorResponse('Lỗi gửi email: ' . $this->mail->ErrorInfo);
            }

            $this->addLog([
                'type'      => 'BOOKING_CONFIRMATION',
                'to'        => $data['to'],
                'status'    => 'SUCCESS',
                'timestamp' => date('Y-m-d H:i:s'),
                'maLichHen' => $data['maLichHen'] ?? null,
            ]);

            return [
                'success'   => true,
                'message'   => 'Email xác nhận đặt lịch gửi thành công',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            return $this->errorResponse('Exception: ' . $e->getMessage());
        }
    }

    /**
     * ============================================================
     * HÀM GỬI EMAIL NHẮC LỊCH (trước 1 tiếng)
     * ============================================================
     *
     * @param array $data:
     *               - 'to', 'hoTen', 'maLichHen', 'thoiGianHen'
     *               - 'tenBacSi' (optional)
     *               - 'phutConLai' (int, mặc định 60)
     * @return array
     */
    public function sendBookingReminder($data)
    {
        try {
            if (empty($data['to'])) {
                return $this->errorResponse('Email người nhận không được để trống');
            }

            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();
            $this->mail->addAddress($data['to'], $data['hoTen'] ?? 'Khách hàng');
            $this->mail->addReplyTo($this->config['clinic']['email'], $this->config['clinic']['name']);

            $this->mail->Subject = 'Nhắc lịch hẹn sắp tới - DERMASOFT';
            $this->mail->isHTML(true);
            $this->mail->Body = $this->getBookingReminderTemplate($data);
            $this->mail->AltBody =
                "Xin chào " . ($data['hoTen'] ?? 'Khách hàng') . ",\n\n"
                . "Bạn có lịch hẹn vào " . ($data['thoiGianHen'] ?? '') . " (còn khoảng "
                . ($data['phutConLai'] ?? 60) . " phút).\n"
                . (!empty($data['tenBacSi']) ? "Bác sĩ: " . $data['tenBacSi'] . "\n" : '')
                . "Mã lịch hẹn: #" . ($data['maLichHen'] ?? '') . "\n\n"
                . "Vui lòng đến đúng giờ. Cảm ơn bạn!";

            if (!$this->mail->send()) {
                return $this->errorResponse('Lỗi gửi email: ' . $this->mail->ErrorInfo);
            }

            $this->addLog([
                'type'      => 'BOOKING_REMINDER',
                'to'        => $data['to'],
                'status'    => 'SUCCESS',
                'timestamp' => date('Y-m-d H:i:s'),
                'maLichHen' => $data['maLichHen'] ?? null,
            ]);

            return [
                'success'   => true,
                'message'   => 'Email nhắc lịch hẹn gửi thành công',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            return $this->errorResponse('Exception: ' . $e->getMessage());
        }
    }

    /**
     * Template HTML: Xác nhận đặt lịch
     */
    private function getBookingConfirmationTemplate($data)
    {
        $hoTen       = htmlspecialchars($data['hoTen'] ?? 'Khách hàng');
        $maLichHen   = htmlspecialchars((string)($data['maLichHen'] ?? ''));
        $thoiGianHen = htmlspecialchars($data['thoiGianHen'] ?? '');
        $tenBacSi    = !empty($data['tenBacSi']) ? htmlspecialchars($data['tenBacSi']) : '';
        $ghiChu      = !empty($data['ghiChu']) ? htmlspecialchars($data['ghiChu']) : '';

        // Ưu tiên thông tin phòng khám từ DB (truyền qua $data['clinic']), fallback về config
        $clinic = is_array($data['clinic'] ?? null) ? $data['clinic'] : [];
        $clinicName  = htmlspecialchars($clinic['name']    ?? $this->config['clinic']['name']    ?? 'DERMASOFT');
        $clinicAddr  = htmlspecialchars($clinic['address'] ?? $this->config['clinic']['address'] ?? '');
        $clinicPhone = htmlspecialchars($clinic['phone']   ?? $this->config['clinic']['phone']   ?? '');

        $bacSiRow = $tenBacSi !== ''
            ? "<tr><td style='padding:8px 0;color:#666;'>Bác sĩ:</td><td style='padding:8px 0;font-weight:bold;'>$tenBacSi</td></tr>"
            : '';
        $ghiChuRow = $ghiChu !== ''
            ? "<tr><td style='padding:8px 0;color:#666;vertical-align:top;'>Ghi chú:</td><td style='padding:8px 0;'>$ghiChu</td></tr>"
            : '';
        $clinicAddrRow = $clinicAddr !== ''
            ? "<p style='margin:4px 0;color:#555;'><strong>Địa chỉ:</strong> $clinicAddr</p>"
            : '';
        $clinicPhoneRow = $clinicPhone !== ''
            ? "<p style='margin:4px 0;color:#555;'><strong>Hotline:</strong> $clinicPhone</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác nhận đặt lịch hẹn</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <div style="text-align:center;border-bottom:3px solid #198754;padding-bottom:20px;margin-bottom:20px;">
            <h1 style="color:#333;margin:0;">&#10004; Đặt Lịch Thành Công</h1>
            <div style="color:#198754;font-size:14px;margin-top:5px;">$clinicName</div>
        </div>
        <p>Xin chào <strong>$hoTen</strong>,</p>
        <p>Lịch hẹn của bạn đã được tiếp nhận. Phòng khám sẽ liên hệ xác nhận trong vòng <strong>30 phút</strong>.</p>
        <div style="background:#e7f5ec;border-left:4px solid #198754;padding:16px;border-radius:4px;margin:20px 0;">
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:8px 0;color:#666;width:35%;">Mã lịch hẹn:</td><td style="padding:8px 0;font-weight:bold;color:#198754;">#$maLichHen</td></tr>
                <tr><td style="padding:8px 0;color:#666;">Thời gian:</td><td style="padding:8px 0;font-weight:bold;font-size:16px;">$thoiGianHen</td></tr>
                $bacSiRow
                $ghiChuRow
            </table>
        </div>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px;color:#856404;margin:15px 0;">
            <strong>&#9888; Lưu ý:</strong> Hệ thống sẽ gửi email nhắc bạn <strong>trước 1 tiếng</strong> giờ hẹn. Vui lòng đến đúng giờ.
        </div>
        <div style="margin-top:20px;padding-top:15px;border-top:1px solid #ddd;">
            $clinicAddrRow
            $clinicPhoneRow
        </div>
        <div style="text-align:center;color:#999;font-size:12px;border-top:1px solid #ddd;padding-top:20px;margin-top:20px;">
            <p>&#169; 2026 $clinicName. All rights reserved.</p>
            <p>Email này được gửi tự động. Vui lòng không trả lời trực tiếp.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template HTML: Nhắc lịch trước 1 tiếng
     */
    private function getBookingReminderTemplate($data)
    {
        $hoTen       = htmlspecialchars($data['hoTen'] ?? 'Khách hàng');
        $maLichHen   = htmlspecialchars((string)($data['maLichHen'] ?? ''));
        $thoiGianHen = htmlspecialchars($data['thoiGianHen'] ?? '');
        $tenBacSi    = !empty($data['tenBacSi']) ? htmlspecialchars($data['tenBacSi']) : '';
        $phutConLai  = (int)($data['phutConLai'] ?? 60);

        // Ưu tiên thông tin phòng khám từ DB (truyền qua $data['clinic']), fallback về config
        $clinic = is_array($data['clinic'] ?? null) ? $data['clinic'] : [];
        $clinicName  = htmlspecialchars($clinic['name']    ?? $this->config['clinic']['name']    ?? 'DERMASOFT');
        $clinicAddr  = htmlspecialchars($clinic['address'] ?? $this->config['clinic']['address'] ?? '');
        $clinicPhone = htmlspecialchars($clinic['phone']   ?? $this->config['clinic']['phone']   ?? '');

        $bacSiRow = $tenBacSi !== ''
            ? "<tr><td style='padding:8px 0;color:#666;'>Bác sĩ:</td><td style='padding:8px 0;font-weight:bold;'>$tenBacSi</td></tr>"
            : '';
        $clinicAddrRow = $clinicAddr !== ''
            ? "<p style='margin:4px 0;color:#555;'><strong>Địa chỉ:</strong> $clinicAddr</p>"
            : '';
        $clinicPhoneRow = $clinicPhone !== ''
            ? "<p style='margin:4px 0;color:#555;'><strong>Hotline:</strong> $clinicPhone</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhắc lịch hẹn</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <div style="text-align:center;border-bottom:3px solid #fd7e14;padding-bottom:20px;margin-bottom:20px;">
            <h1 style="color:#333;margin:0;">&#9200; Nhắc Lịch Hẹn</h1>
            <div style="color:#fd7e14;font-size:14px;margin-top:5px;">$clinicName</div>
        </div>
        <p>Xin chào <strong>$hoTen</strong>,</p>
        <p>Đây là email nhắc bạn về lịch hẹn sắp diễn ra trong <strong>khoảng $phutConLai phút nữa</strong>.</p>
        <div style="background:#fff4ec;border-left:4px solid #fd7e14;padding:16px;border-radius:4px;margin:20px 0;">
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:8px 0;color:#666;width:35%;">Mã lịch hẹn:</td><td style="padding:8px 0;font-weight:bold;color:#fd7e14;">#$maLichHen</td></tr>
                <tr><td style="padding:8px 0;color:#666;">Thời gian:</td><td style="padding:8px 0;font-weight:bold;font-size:16px;">$thoiGianHen</td></tr>
                $bacSiRow
            </table>
        </div>
        <p>Vui lòng có mặt tại phòng khám đúng giờ. Nếu không thể đến, xin vui lòng liên hệ hủy lịch trước.</p>
        <div style="margin-top:20px;padding-top:15px;border-top:1px solid #ddd;">
            $clinicAddrRow
            $clinicPhoneRow
        </div>
        <div style="text-align:center;color:#999;font-size:12px;border-top:1px solid #ddd;padding-top:20px;margin-top:20px;">
            <p>&#169; 2026 $clinicName. All rights reserved.</p>
            <p>Email này được gửi tự động. Vui lòng không trả lời trực tiếp.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * ============================================================
     * HÀM GỬI EMAIL PHÂN CÔNG CA LÀM VIỆC (cho bác sĩ & lễ tân)
     * ============================================================
     *
     * @param array $data:
     *               - 'to'          : Email nhân viên
     *               - 'hoTen'       : Tên nhân viên
     *               - 'vaiTro'      : 'Bác sĩ' | 'Lễ tân' | ...
     *               - 'tenCa'       : Tên ca (ví dụ "Ca sáng")
     *               - 'gioBatDau'   : 'HH:MM'
     *               - 'gioKetThuc'  : 'HH:MM'
     *               - 'ngayLamViec' : 'd/m/Y'
     *               - 'thuTrongTuan': (optional) ví dụ 'Thứ Hai'
     *               - 'loai'        : 'MOI' | 'SUA' | 'XOA' (mặc định 'MOI')
     *               - 'caCu'        : (optional, khi 'SUA') ['tenCa','gioBatDau','gioKetThuc','ngayLamViec']
     *               - 'clinic'      : (optional) override thông tin phòng khám
     * @return array
     */
    public function sendShiftAssignment($data)
    {
        try {
            if (empty($data['to'])) {
                return $this->errorResponse('Email người nhận không được để trống');
            }

            $loai = strtoupper((string)($data['loai'] ?? 'MOI'));
            $loai = in_array($loai, ['MOI', 'SUA', 'XOA'], true) ? $loai : 'MOI';

            switch ($loai) {
                case 'SUA':
                    $subject = 'Cập nhật ca làm việc - DERMASOFT';
                    $logType = 'SHIFT_UPDATE';
                    $altIntro = 'Ca làm việc của bạn đã được cập nhật.';
                    break;
                case 'XOA':
                    $subject = 'Hủy ca làm việc - DERMASOFT';
                    $logType = 'SHIFT_CANCEL';
                    $altIntro = 'Ca làm việc của bạn đã bị hủy.';
                    break;
                default:
                    $subject = 'Phân công ca làm việc mới - DERMASOFT';
                    $logType = 'SHIFT_ASSIGN';
                    $altIntro = 'Bạn vừa được phân công một ca làm việc mới.';
            }

            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();
            $this->mail->addAddress($data['to'], $data['hoTen'] ?? 'Nhân viên');
            $this->mail->addReplyTo($this->config['clinic']['email'], $this->config['clinic']['name']);

            $this->mail->Subject = $subject;
            $this->mail->isHTML(true);
            $this->mail->Body = $this->getShiftAssignmentTemplate($data, $loai);
            $this->mail->AltBody =
                "Xin chào " . ($data['hoTen'] ?? 'Nhân viên') . ",\n\n"
                . $altIntro . "\n"
                . "Ngày: " . ($data['ngayLamViec'] ?? '') . "\n"
                . "Ca: " . ($data['tenCa'] ?? '') . " (" . ($data['gioBatDau'] ?? '') . " - " . ($data['gioKetThuc'] ?? '') . ")\n"
                . "Vui lòng đăng nhập hệ thống để xem chi tiết.";

            if (!$this->mail->send()) {
                return $this->errorResponse('Lỗi gửi email: ' . $this->mail->ErrorInfo);
            }

            $this->addLog([
                'type'        => $logType,
                'to'          => $data['to'],
                'status'      => 'SUCCESS',
                'timestamp'   => date('Y-m-d H:i:s'),
                'ngayLamViec' => $data['ngayLamViec'] ?? null,
                'tenCa'       => $data['tenCa'] ?? null,
            ]);

            return [
                'success'   => true,
                'message'   => 'Email phân công ca gửi thành công',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            return $this->errorResponse('Exception: ' . $e->getMessage());
        }
    }

    /**
     * Template HTML: Phân công / cập nhật / hủy ca làm việc
     */
    private function getShiftAssignmentTemplate($data, $loai = 'MOI')
    {
        $hoTen        = htmlspecialchars($data['hoTen']        ?? 'Nhân viên');
        $vaiTro       = htmlspecialchars($data['vaiTro']       ?? 'Nhân viên');
        $tenCa        = htmlspecialchars($data['tenCa']        ?? '');
        $gioBatDau    = htmlspecialchars($data['gioBatDau']    ?? '');
        $gioKetThuc   = htmlspecialchars($data['gioKetThuc']   ?? '');
        $ngayLamViec  = htmlspecialchars($data['ngayLamViec']  ?? '');
        $thuTrongTuan = htmlspecialchars($data['thuTrongTuan'] ?? '');

        // Tone màu + nhãn theo loại
        switch ($loai) {
            case 'SUA':
                $color   = '#0d6efd';
                $title   = '&#9998; Cập Nhật Ca Làm Việc';
                $intro   = 'Ca làm việc của bạn đã được <strong>cập nhật</strong>. Vui lòng kiểm tra thông tin mới phía dưới.';
                $bgSoft  = '#e7f1ff';
                break;
            case 'XOA':
                $color   = '#dc3545';
                $title   = '&#10006; Hủy Ca Làm Việc';
                $intro   = 'Ca làm việc dưới đây đã được <strong>hủy</strong>. Bạn không cần đến làm vào thời gian này.';
                $bgSoft  = '#fdecec';
                break;
            default:
                $color   = '#198754';
                $title   = '&#10004; Phân Công Ca Mới';
                $intro   = 'Bạn vừa được phân công một <strong>ca làm việc mới</strong>. Vui lòng có mặt đúng giờ.';
                $bgSoft  = '#e7f5ec';
        }

        // Thông tin phòng khám
        $clinic = is_array($data['clinic'] ?? null) ? $data['clinic'] : [];
        $clinicName  = htmlspecialchars($clinic['name']    ?? $this->config['clinic']['name']    ?? 'DERMASOFT');
        $clinicAddr  = htmlspecialchars($clinic['address'] ?? $this->config['clinic']['address'] ?? '');
        $clinicPhone = htmlspecialchars($clinic['phone']   ?? $this->config['clinic']['phone']   ?? '');

        $ngayLabel = $thuTrongTuan !== '' ? "$thuTrongTuan, $ngayLamViec" : $ngayLamViec;
        $gioRange  = ($gioBatDau !== '' || $gioKetThuc !== '')
            ? "$gioBatDau - $gioKetThuc"
            : '';

        // Khối hiển thị ca cũ (chỉ khi SUA và có dữ liệu)
        $caCuBlock = '';
        if ($loai === 'SUA' && !empty($data['caCu']) && is_array($data['caCu'])) {
            $cuTen   = htmlspecialchars($data['caCu']['tenCa']        ?? '');
            $cuBD    = htmlspecialchars($data['caCu']['gioBatDau']    ?? '');
            $cuKT    = htmlspecialchars($data['caCu']['gioKetThuc']   ?? '');
            $cuNgay  = htmlspecialchars($data['caCu']['ngayLamViec']  ?? '');
            $cuGio   = ($cuBD !== '' || $cuKT !== '') ? "$cuBD - $cuKT" : '';
            $caCuBlock = <<<HTML
        <div style="background:#f1f3f5;border-left:4px solid #adb5bd;padding:12px 16px;border-radius:4px;margin:10px 0;">
            <div style="color:#666;font-size:13px;margin-bottom:6px;"><strong>Thông tin ca cũ:</strong></div>
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr><td style="padding:4px 0;color:#666;width:35%;">Ngày:</td><td style="padding:4px 0;">$cuNgay</td></tr>
                <tr><td style="padding:4px 0;color:#666;">Ca:</td><td style="padding:4px 0;">$cuTen</td></tr>
                <tr><td style="padding:4px 0;color:#666;">Giờ:</td><td style="padding:4px 0;">$cuGio</td></tr>
            </table>
        </div>
HTML;
        }

        $clinicAddrRow = $clinicAddr !== ''
            ? "<p style='margin:4px 0;color:#555;'><strong>Địa chỉ:</strong> $clinicAddr</p>"
            : '';
        $clinicPhoneRow = $clinicPhone !== ''
            ? "<p style='margin:4px 0;color:#555;'><strong>Hotline:</strong> $clinicPhone</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phân công ca làm việc</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <div style="text-align:center;border-bottom:3px solid $color;padding-bottom:20px;margin-bottom:20px;">
            <h1 style="color:#333;margin:0;font-size:22px;">$title</h1>
            <div style="color:$color;font-size:14px;margin-top:5px;">$clinicName</div>
        </div>
        <p>Xin chào <strong>$hoTen</strong> ($vaiTro),</p>
        <p>$intro</p>
        $caCuBlock
        <div style="background:$bgSoft;border-left:4px solid $color;padding:16px;border-radius:4px;margin:20px 0;">
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:8px 0;color:#666;width:35%;">Ngày làm việc:</td><td style="padding:8px 0;font-weight:bold;font-size:16px;">$ngayLabel</td></tr>
                <tr><td style="padding:8px 0;color:#666;">Ca:</td><td style="padding:8px 0;font-weight:bold;color:$color;">$tenCa</td></tr>
                <tr><td style="padding:8px 0;color:#666;">Khung giờ:</td><td style="padding:8px 0;font-weight:bold;">$gioRange</td></tr>
            </table>
        </div>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px;color:#856404;margin:15px 0;font-size:14px;">
            <strong>&#9888; Lưu ý:</strong> Vui lòng đăng nhập hệ thống để xem lịch làm việc đầy đủ. Nếu có thắc mắc, liên hệ quản lý phòng khám.
        </div>
        <div style="margin-top:20px;padding-top:15px;border-top:1px solid #ddd;">
            $clinicAddrRow
            $clinicPhoneRow
        </div>
        <div style="text-align:center;color:#999;font-size:12px;border-top:1px solid #ddd;padding-top:20px;margin-top:20px;">
            <p>&#169; 2026 $clinicName. All rights reserved.</p>
            <p>Email này được gửi tự động. Vui lòng không trả lời trực tiếp.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * ============================================================
     * HỖ TRỢ: Lỗi & Logging
     * ============================================================
     */
    
    /**
     * Hàm tạo response lỗi
     */
    private function errorResponse($message)
    {
        return [
            'success'   => false,
            'message'   => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Ghi log email
     */
    private function addLog($entry)
    {
        $this->log[] = $entry;
    }
    
    /**
     * Lấy tất cả logs
     */
    public function getLogs()
    {
        return $this->log;
    }
    
    /**
     * Xóa email recipients
     */
    public function clearRecipients()
    {
        $this->mail->clearAddresses();
        $this->mail->clearCCs();
        $this->mail->clearBCCs();
    }
}

?>
