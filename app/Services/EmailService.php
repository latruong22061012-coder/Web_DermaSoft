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
