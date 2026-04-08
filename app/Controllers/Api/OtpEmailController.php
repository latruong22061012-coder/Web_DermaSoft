<?php
/**
 * ============================================================
 * 7 API ENDPOINTS CHO OTP + EMAIL XÁC THỰC
 * ============================================================
 * 
 * Các endpoints mới cho Phase 2 - Email Service Integration
 * 
 * Tất cả endpoints sử dụng EmailService để gửi email
 * Sử dụng Database stored procedures từ Phase 1
 * 
 * Ngày tạo: 23/03/2026
 * Phiên bản: 1.0 Production
 * 
 * ============================================================
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Models\User;
use App\Services\EmailService;

class OtpEmailController extends ApiController
{
    /** @var \EmailService */
    private $emailService;

    /** @var \SmsService */
    private $smsService;

    public function __construct()
    {
        parent::__construct();
        // Lazy loading — khởi tạo khi cần
    }

    /**
     * Lấy SmsService instance (lazy loading)
     */
    private function getSmsService()
    {
        if (!$this->smsService) {
            try {
                $sms_config_path  = __DIR__ . '/../../Config/sms_config.php';
                $sms_service_path = __DIR__ . '/../../Services/SmsService.php';

                if (!file_exists($sms_config_path) || !file_exists($sms_service_path)) {
                    return null;
                }

                require_once $sms_config_path;
                require_once $sms_service_path;

                if (!isset($SMS_CONFIG) || empty($SMS_CONFIG)) {
                    return null;
                }

                $provider = $SMS_CONFIG['provider'] ?? 'esms';
                $providerCfg = $SMS_CONFIG[$provider] ?? [];

                // Nếu API key chưa điền → không khởi tạo
                $keyField = ($provider === 'esms') ? 'api_key'
                          : (($provider === 'speedsms') ? 'access_token' : 'account_sid');
                if (empty($providerCfg[$keyField])) {
                    return null;
                }

                $this->smsService = new \SmsService($SMS_CONFIG);
            } catch (\Exception $e) {
                error_log('Lỗi khởi tạo SmsService: ' . $e->getMessage());
                return null;
            }
        }
        return $this->smsService;
    }
    
    /**
     * Lấy EmailService instance (lazy loading)
     */
    private function getEmailService()
    {
        if (!$this->emailService) {
            try {
                // __DIR__ = app/Controllers/Api → cần ../../ để lên app/
                $email_config_path = __DIR__ . '/../../Config/email_config.php';
                if (!file_exists($email_config_path)) {
                    error_log("Không tìm thấy email_config.php tại: $email_config_path");
                    return null;
                }
                require_once $email_config_path;

                $email_service_path = __DIR__ . '/../../Services/EmailService.php';
                if (!file_exists($email_service_path)) {
                    error_log("Không tìm thấy EmailService.php tại: $email_service_path");
                    return null;
                }
                require_once $email_service_path;

                // email_config.php định nghĩa $EMAIL_CONFIG (biến, không phải hằng)
                if (!isset($EMAIL_CONFIG) || empty($EMAIL_CONFIG)) {
                    error_log("\$EMAIL_CONFIG không được định nghĩa trong email_config.php");
                    return null;
                }

                $this->emailService = new \EmailService($EMAIL_CONFIG);
            } catch (\Exception $e) {
                error_log("Lỗi khởi tạo EmailService: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine());
                return null;
            }
        }

        return $this->emailService;
    }
    
    // ============================================================
    // ENDPOINT 1: POST /api/auth/check-phone
    // ============================================================
    /**
     * Kiểm tra số điện thoại đã tồn tại chưa
     * 
     * Yêu cầu: {sodienthoai}
     * Phản hồi:
     * {
     *   "success": true,
     *   "exists": true/false,
     *   "message": "Số điện thoại đã tồn tại" | "Số điện thoại chưa được đăng ký"
     * }
     */
    public function checkPhone(): void
    {
        Auth::startSession();
        $data = $this->getJSON();
        
        // Kiểm tra dữ liệu
        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10|maxlen:15'
        ]);
        
        if (!empty($errors)) {
            $this->error('Số điện thoại không hợp lệ', $errors, 400);
            return;
        }
        
        try {
            // Gọi Database để kiểm tra
            $user = User::findByPhone($data['sodienthoai']);
            
            if ($user) {
                $this->success([
                    'exists' => true,
                    'hoTen' => $user['HoTen'] ?? null,
                ],
                'Số điện thoại đã tồn tại trong hệ thống');
            } else {
                $this->success([
                    'exists' => false,
                ],
                'Số điện thoại chưa được đăng ký');
            }
            
            $this->logAccess("Check phone: {$data['sodienthoai']}");
            
        } catch (\Throwable $e) {
            error_log("OtpEmailController::checkPhone() Exception: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine());
            $this->error('Lỗi kiểm tra số điện thoại: ' . $e->getMessage(), null, 500);
        }
    }
    
    // ============================================================
    // ENDPOINT 2: POST /api/auth/send-otp-login
    // ============================================================
    /**
     * Gửi OTP qua email để đăng nhập (sử dụng số điện thoại)
     * 
     * Yêu cầu: {sodienthoai}
     * Phản hồi:
     * {
     *   "success": true,
     *   "message": "Email OTP gửi thành công",
     *   "expires_in": 300,
     *   "phone_masked": "***1234"
     * }
     */
    public function sendOtpLogin(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10|maxlen:15'
        ]);

        if (!empty($errors)) {
            $this->error('Số điện thoại không hợp lệ', $errors, 400);
            return;
        }

        try {
            $user = User::findByPhone($data['sodienthoai']);

            if (!$user) {
                $this->success(['exists' => false], 'Kiểm tra số điện thoại của bạn');
                return;
            }

            if ($user['TrangThaiTK'] != 1) {
                $this->error('Tài khoản không hoạt động', null, 403);
                return;
            }

            $userEmail = $user['Email'] ?? null;
            if (!$userEmail) {
                $this->error('Tài khoản chưa có email. Vui lòng liên hệ quản trị viên', null, 400);
                return;
            }

            $phone = $data['sodienthoai'];

            // Xoá OTP cũ chưa dùng của số này (tránh lỗi rate limit)
            \App\Core\Database::execute(
                "DELETE FROM XacThucOTP WHERE SoDienThoai = ?",
                [$phone]
            );

            // Gọi SP_GuiOTP_NguoiDung — SP tự tạo + lưu OTP, trả về MaOTP
            $spResult = \App\Core\Database::fetchOne(
                "EXEC SP_GuiOTP_NguoiDung @SoDienThoai = ?",
                [$phone]
            );

            if (!$spResult || empty($spResult['MaOTP'])) {
                $this->error('Không thể tạo mã OTP. Vui lòng thử lại', null, 500);
                return;
            }

            $otp = $spResult['MaOTP'];

            // Gửi OTP qua Email
            $emailSent    = false;
            $emailService = $this->getEmailService();
            if ($emailService) {
                $emailResult = $emailService->sendOtpEmail([
                    'to'    => $userEmail,
                    'otp'   => $otp,
                    'hoTen' => $user['HoTen'],
                ]);
                if ($emailResult['success']) {
                    $emailSent = true;
                } else {
                    error_log("Lỗi gửi email OTP: " . $emailResult['message']);
                }
            }

            if (!$emailSent) {
                // Dev mode: ghi OTP vào log (chỉ dùng khi test)
                error_log("[DEV] OTP for {$phone}: {$otp}");
            }

            // Lưu session để xác thực OTP sau
            $_SESSION['otp_login_' . $phone] = [
                'timestamp'  => time(),
                'user_id'    => $user['MaNguoiDung'],
                'email'      => $userEmail,
                'expires_at' => time() + 300,
            ];

            // Xây dựng response
            $responseData = [
                'expires_in'   => 300,
                'email_masked' => $this->maskEmail($userEmail),
            ];

            if ($emailSent) {
                $msg = 'Mã OTP đã gửi đến email ' . $this->maskEmail($userEmail);
            } else {
                $responseData['dev_otp'] = $otp;
                $msg = 'Mã OTP đã tạo (dev mode — chưa có email server)';
            }

            $this->success($responseData, $msg);

        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Rate limit từ SP → 429
            if (strpos($msg, 'quá nhiều lần') !== false) {
                http_response_code(429);
                echo json_encode([
                    'status'  => 429,
                    'message' => 'Bạn đã yêu cầu OTP quá nhiều lần. Vui lòng thử lại sau 1 phút.',
                    'data'    => null,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            error_log("sendOtpLogin error: " . $msg);
            $this->error('Lỗi gửi OTP: ' . $msg, null, 500);
        }
    }

    
    // ============================================================
    // ENDPOINT 3: POST /api/auth/login-with-otp
    // ============================================================
    /**
     * Đăng nhập dùng OTP (thay thế mật khẩu) - Sử dụng số điện thoại
     * 
     * Yêu cầu: {sodienthoai, otp}
     * Phản hồi:
     * {
     *   "success": true,
     *   "token": "...",
     *   "user": {...},
     *   "message": "Đăng nhập thành công"
     * }
     */
    public function loginWithOtp(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10|maxlen:15',
            'otp'         => 'required|len:6'
        ]);

        if (!empty($errors)) {
            $this->error('Số điện thoại hoặc OTP không hợp lệ', $errors, 400);
            return;
        }

        try {
            $phone = $data['sodienthoai'];
            $otp   = $data['otp'];

            // Kiểm tra session OTP còn hiệu lực
            $sessionKey = 'otp_login_' . $phone;
            if (!isset($_SESSION[$sessionKey])) {
                $this->error('Phiên OTP đã hết hạn, vui lòng gửi lại mã', null, 401);
                return;
            }
            $otpSession = $_SESSION[$sessionKey];
            if (time() > $otpSession['expires_at']) {
                unset($_SESSION[$sessionKey]);
                $this->error('OTP đã hết hạn, vui lòng gửi lại mã', null, 401);
                return;
            }

            // Xác thực OTP qua SP — gọi thẳng bằng SĐT (không qua email lookup)
            try {
                $spResult = \App\Core\Database::fetchOne(
                    "EXEC SP_XacThucOTP_NguoiDung @SoDienThoai = ?, @MaOTP = ?",
                    [$phone, $otp]
                );
            } catch (\Throwable $spEx) {
                $msg = $spEx->getMessage();
                if (strpos($msg, 'không đúng') !== false || strpos($msg, 'Còn') !== false) {
                    $this->error($msg, null, 401);
                } elseif (strpos($msg, 'hết hạn') !== false) {
                    unset($_SESSION[$sessionKey]);
                    $this->error('OTP đã hết hạn, vui lòng gửi lại mã', null, 401);
                } elseif (strpos($msg, 'không tồn tại') !== false || strpos($msg, 'đã được xác thực') !== false) {
                    $this->error('OTP không hợp lệ hoặc đã được dùng. Vui lòng yêu cầu mã mới', null, 401);
                } else {
                    $this->error('Lỗi xác thực OTP: ' . $msg, null, 500);
                }
                return;
            }

            // Lấy thông tin user
            $user = User::findByPhone($phone);
            if (!$user || $user['TrangThaiTK'] != 1) {
                $this->error('Tài khoản không tồn tại hoặc đã bị khóa', null, 403);
                return;
            }

            // Lưu session đăng nhập
            $_SESSION['authenticated_user'] = $user;
            $_SESSION['login_time']         = time();

            // Xóa session OTP
            unset($_SESSION[$sessionKey]);

            $token = Auth::generateToken($user['MaNguoiDung']);

            $this->success([
                'token' => $token,
                'user'  => [
                    'id'          => $user['MaNguoiDung'],
                    'hoTen'       => $user['HoTen'],
                    'email'       => $user['Email'],
                    'sodienthoai' => $user['SoDienThoai'],
                    'maVaiTro'    => $user['MaVaiTro'],
                ],
            ], 'Đăng nhập thành công');

        } catch (\Exception $e) {
            error_log("Lỗi loginWithOtp: " . $e->getMessage());
            $this->error('Lỗi đăng nhập: ' . $e->getMessage(), null, 500);
        }
    }
    
    // ============================================================
    // ENDPOINT 4: POST /api/auth/register-phone
    // ============================================================
    /**
     * Đăng ký tài khoản mới với số điện thoại
     * 
     * Yêu cầu: {hoTen, sodienthoai, email, matkhau}
     * Phản hồi:
     * {
     *   "success": true,
     *   "user_id": 123,
     *   "verify_token": "...",
     *   "message": "Đăng ký thành công. Vui lòng xác thực email"
     * }
     */
    public function registerPhone(): void
    {
        Auth::startSession();
        $data = $this->getJSON();
        
        // Kiểm tra dữ liệu
        $errors = $this->validate($data, [
            'hoTen'       => 'required|minlen:3',
            'sodienthoai' => 'required|minlen:10|maxlen:15',
            'email'       => 'required|email',
            'matkhau'     => 'required|minlen:6'
        ]);
        
        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }
        
        try {
            // Kiểm tra số điện thoại/email đãm tồn tại
            if (User::findByPhone($data['sodienthoai'])) {
                $this->error('Số điện thoại đã được sử dụng', null, 409);
                return;
            }
            
            if (User::findByEmail($data['email'])) {
                $this->error('Email đã được sử dụng', null, 409);
                return;
            }
            
            // Lấy MaVaiTro cho Bệnh Nhân (tạo nếu chưa có)
            $maVaiTro = $this->getOrCreateBenhNhanRole();
            if (!$maVaiTro) {
                $this->error('Không thể xác định vai trò người dùng', null, 500);
                return;
            }
            
            // Tạo username từ số điện thoại
            $username = 'user_' . substr($data['sodienthoai'], -6);
            
            // Gọi SP_TaoTaiKhoanNguoiDung
            $userId = User::createUser([
                'HoTen'       => $data['hoTen'],
                'SoDienThoai' => $data['sodienthoai'],
                'Email'       => $data['email'],
                'TenDangNhap' => $username,
                'MatKhau'     => password_hash($data['matkhau'], PASSWORD_BCRYPT),
                'MaVaiTro'    => $maVaiTro,
                'TrangThaiTK' => 1,   // Kích hoạt luôn (không cần verify email)
            ]);
            
            if (!$userId) {
                $this->error('Không thể tạo tài khoản', null, 500);
                return;
            }
            
            // Tạo email verification token
            $emailToken = bin2hex(random_bytes(32));
            
            // Lưu token vào database
            $this->saveEmailTokenToDatabase($userId, $emailToken);
            
            // Gửi email xác thực
            $verifyLink = BASE_URL . '/verify-email?token=' . $emailToken;
            
            $emailService = $this->getEmailService();
            if ($emailService) {
                $emailResult = $emailService->sendVerifyEmailLink([
                    'to'         => $data['email'],
                    'hoTen'      => $data['hoTen'],
                    'verifyLink' => $verifyLink,
                    'expireTime' => 24,  // 24 giờ
                ]);
                
                if (!$emailResult['success']) {
                    error_log("Lỗi gửi email verify: " . $emailResult['message']);
                    // Vẫn tạo user thành công nhưng cảnh báo
                }
            } else {
                error_log("EmailService không khả dụng");
                // Vẫn tạo user thành công nhưng cảnh báo
            }
            
            $this->logAccess("Register Phone - Phone: {$data['sodienthoai']}");
            
            $this->success([
                'user_id'      => $userId,
                'username'     => $username,
                'message'      => 'Tài khoản đã tạo. Vui lòng kiểm tra email để xác thực',
                'verify_token' => $emailToken,
            ], 'Đăng ký thành công', 201);
            
        } catch (\Exception $e) {
            error_log("Lỗi tạo tài khoản: " . $e->getMessage());
            $this->error('Lỗi đăng ký: ' . $e->getMessage(), null, 500);
        }
    }
    
    // ============================================================
    // ENDPOINT 5: POST /api/auth/forgot-phone
    // ============================================================
    /**
     * Quên số điện thoại / Lấy lại số
     * 
     * Yêu cầu: {email}
     * Phản hồi:
     * {
     *   "success": true,
     *   "message": "Số điện thoại đã gửi email"
     * }
     */
    public function forgotPhone(): void
    {
        Auth::startSession();
        $data = $this->getJSON();
        
        $errors = $this->validate($data, [
            'email' => 'required|email'
        ]);
        
        if (!empty($errors)) {
            $this->error('Email không hợp lệ', $errors, 400);
            return;
        }
        
        try {
            $user = User::findByEmail($data['email']);
            
            if (!$user) {
                // Không tiết lộ email có tồn tại hay không (bảo mật)
                $this->success([], 'Nếu email tồn tại, thông tin số điện thoại sẽ được gửi');
                return;
            }
            
            // Gửi email chứa số điện thoại (masked)
            $maskedPhone = substr($user['SoDienThoai'], 0, 3) . '****' . substr($user['SoDienThoai'], -2);

            $emailService = $this->getEmailService();
            if ($emailService) {
                $emailResult = $emailService->sendPhoneInfoEmail([
                    'to'          => $data['email'],
                    'hoTen'       => $user['HoTen'] ?? 'Khách hàng',
                    'maskedPhone' => $maskedPhone,
                ]);

                if (!$emailResult['success']) {
                    error_log("Lỗi gửi email forgot phone [{$data['email']}]: " . $emailResult['message']);
                }
            } else {
                error_log("EmailService không khả dụng - forgot phone: {$data['email']}");
            }

            $this->logAccess("Forgot Phone - Email: {$data['email']}");

            $this->success([], 'Thông tin số điện thoại đã gửi. Kiểm tra email của bạn');
            
        } catch (\Exception $e) {
            error_log("Lỗi lấy số điện thoại: " . $e->getMessage());
            $this->error('Lỗi lấy thông tin', null, 500);
        }
    }
    
    // ============================================================
    // ENDPOINT 6: POST /api/auth/update-phone
    // ============================================================
    /**
     * Cập nhật số điện thoại + Email
     * Cần authenticate trước
     * 
     * Yêu cầu: {sodienthoai_moi, email_moi, otp_confirm}
     * Phản hồi:
     * {
     *   "success": true,
     *   "message": "Thông tin đã cập nhật"
     * }
     */
    public function updatePhone(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        
        if (!$user) {
            return;
        }
        
        $data = $this->getJSON();
        
        $errors = $this->validate($data, [
            'sodienthoai_moi' => 'required|minlen:10',
            'email_moi'       => 'required|email',
            'otp_confirm'     => 'required|len:6'
        ]);
        
        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }
        
        try {
            // Kiểm tra OTP xác thực
            if (!$this->verifyOtpFromDatabase($user['Email'], $data['otp_confirm'])) {
                $this->error('OTP không đúng. Vui lòng thử lại', null, 401);
                return;
            }
            
            // Cập nhật thông tin (gọi SP_CapNhatThongTinNguoiDung)
            $updateResult = User::updateInfo($user['MaNguoiDung'], [
                'SoDienThoai'       => $data['sodienthoai_moi'],
                'Email'             => $data['email_moi'],
                'NgayUpdateSoDienThoai' => date('Y-m-d H:i:s'),
            ]);
            
            if (!$updateResult) {
                $this->error('Không thể cập nhật thông tin', null, 500);
                return;
            }
            
            // Gửi email xác nhận thay đổi
            $this->emailService->sendOtpEmail([
                'to'     => $data['email_moi'],
                'otp'    => 'CONFIRMED',  // Chỉ là xác nhận
                'hoTen'  => $user['HoTen'],
            ]);
            
            $this->logAccess("Update Phone - UserID: {$user['MaNguoiDung']}");
            
            $this->success([], 'Thông tin đã cập nhật thành công');
            
        } catch (\Exception $e) {
            error_log("Lỗi cập nhật: " . $e->getMessage());
            $this->error('Lỗi cập nhật thông tin: ' . $e->getMessage(), null, 500);
        }
    }
    
    // ============================================================
    // ENDPOINT 7: POST /api/auth/verify-email-token
    // ============================================================
    /**
     * Xác thực email bằng token (từ link trong email)
     * 
     * Yêu cầu: {token}
     * Phản hồi:
     * {
     *   "success": true,
     *   "user_id": 123,
     *   "message": "Email đã xác thực"
     * }
     */
    public function verifyEmailToken(): void
    {
        Auth::startSession();
        $data = $this->getJSON();
        
        if (empty($data['token'])) {
            $this->error('Token không được cung cấp', null, 400);
            return;
        }
        
        try {
            // Kiểm tra token hợp lệ
            $result = $this->verifyEmailTokenFromDatabase($data['token']);
            
            if (!$result['valid']) {
                $this->error($result['message'], null, 401);
                return;
            }
            
            // Cập nhật trạng thái email verified
            $userId = $result['user_id'];
            
            User::updateInfo($userId, [
                'EmailVerifiedAt' => date('Y-m-d H:i:s'),
                'TrangThaiTK'     => 1,  // Kích hoạt tài khoản
            ]);
            
            $this->logAccess("Verify Email Token - UserID: $userId");
            
            $this->success([
                'user_id'  => $userId,
                'verified' => true,
            ], 'Email đã xác thực. Bạn có thể đăng nhập ngay');
            
        } catch (\Exception $e) {
            error_log("Lỗi xác thực email: " . $e->getMessage());
            $this->error('Lỗi xác thực email: ' . $e->getMessage(), null, 500);
        }
    }
    
    // ============================================================
    // HELPER FUNCTIONS
    // ============================================================
    
    /**
     * Lấy hoặc tạo vai trò Bệnh Nhân trong database
     * Return MaVaiTro của Nh vai trò Bệnh Nhân
     */
    private function getOrCreateBenhNhanRole(): ?int
    {
        try {
            // Kiểm tra đã có vai trò Bệnh Nhân chưa
            $row = \App\Core\Database::fetchOne("SELECT MaVaiTro FROM VaiTro WHERE TenVaiTro = N'Bệnh Nhân'", []);
            if ($row) {
                return (int)$row['MaVaiTro'];
            }

            // Tạo vai trò mới
            \App\Core\Database::execute("INSERT INTO VaiTro (TenVaiTro) VALUES (N'Bệnh Nhân')", []);

            // Lấy ID vừa tạo
            $newRow = \App\Core\Database::fetchOne("SELECT MAX(MaVaiTro) as id FROM VaiTro", []);
            return $newRow ? (int)$newRow['id'] : null;

        } catch (\Throwable $e) {
            error_log("getOrCreateBenhNhanRole error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy Database adapter để sử dụng trong các helper
     */
    private function getDatabase(): object
    {
        return new class {
            public function execute(string $sql, array $params = []): int
            {
                return \App\Core\Database::execute($sql, $params);
            }

            public function query(string $sql, array $params = []): array
            {
                return \App\Core\Database::fetchAll($sql, $params);
            }
        };
    }

    /**
     * Tạo OTP 6 chữ số ngẫu nhiên
     */
    private function generateOTP(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Lưu OTP vào database (gọi Stored Procedure)
     */
    private function saveOtpToDatabase(int $userId, string $otp, string $email): void
    {
        // Gọi SP_GuiOTP_NguoiDung qua database
        $db = $this->getDatabase();
        $db->execute("EXEC SP_GuiOTP_NguoiDung @SoDienThoai = ?", [$userId]);
    }
    
    /**
     * Xác thực OTP từ database (gọi Stored Procedure)
     */
    private function verifyOtpFromDatabase(string $email, string $otp): bool
    {
        // Gọi SP_XacThucOTP_NguoiDung qua database
        $user = User::findByEmail($email);
        if (!$user) return false;
        
        $db = $this->getDatabase();
        $result = $db->query("EXEC SP_XacThucOTP_NguoiDung @SoDienThoai = ?, @MaOTP = ?", 
            [$user['SoDienThoai'], $otp]);
        
        return !empty($result);
    }
    
    /**
     * Lưu email verification token vào database
     */
    private function saveEmailTokenToDatabase(int $userId, string $token): void
    {
        $db = $this->getDatabase();
        $expiry = date('Y-m-d H:i:s', time() + 24 * 60 * 60);  // 24 giờ
        
        $db->execute("UPDATE NguoiDung SET EmailConfirmToken = ?, EmailConfirmTokenExpiry = ? WHERE MaNguoiDung = ?",
            [$token, $expiry, $userId]);
    }
    
    /**
     * Xác thực email token từ database
     */
    private function verifyEmailTokenFromDatabase(string $token): array
    {
        $db = $this->getDatabase();
        $result = $db->query("SELECT MaNguoiDung FROM NguoiDung WHERE EmailConfirmToken = ? AND EmailConfirmTokenExpiry > GETDATE()",
            [$token]);
        
        if ($result && count($result) > 0) {
            return [
                'valid'   => true,
                'user_id' => $result[0]['MaNguoiDung'],
            ];
        }
        
        return [
            'valid'   => false,
            'message' => 'Token không hợp lệ hoặc đã hết hạn',
        ];
    }
    
    // ============================================================
    // ENDPOINT 8: POST /api/auth/send-otp-phone-reset
    // ============================================================
    /**
     * Gửi OTP về email để xác minh trước khi đổi số điện thoại
     * KHÔNG cần đăng nhập — dành cho người mất điện thoại
     *
     * Yêu cầu: { email }
     * Phản hồi:
     * {
     *   "success": true,
     *   "data": { "email_masked": "la****g@gmail.com", "expires_in": 300 },
     *   "message": "Mã OTP đã gửi về email"
     * }
     */
    public function sendOtpPhoneReset(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, ['email' => 'required|email']);
        if (!empty($errors)) {
            $this->error('Email không hợp lệ', $errors, 400);
            return;
        }

        $email = trim($data['email']);

        try {
            $user = User::findByEmail($email);

            // Không tiết lộ email có tồn tại hay không (bảo mật)
            if (!$user) {
                $this->success(
                    ['email_masked' => $this->maskEmail($email), 'expires_in' => 300],
                    'Nếu email tồn tại, mã OTP sẽ được gửi trong vài giây'
                );
                return;
            }

            // Rate limit: tối đa 1 lần/phút
            $sessionKey = 'phone_reset_otp_' . md5($email);
            if (isset($_SESSION[$sessionKey])) {
                $remaining = $_SESSION[$sessionKey]['expires'] - time();
                if ($remaining > 240) { // Còn trên 4 phút = vừa gửi < 1 phút trước
                    $this->error('Vui lòng chờ ' . ceil($remaining - 240) . ' giây trước khi gửi lại', null, 429);
                    return;
                }
            }

            // Tạo OTP 6 chữ số
            $otp = $this->generateOTP();

            // Lưu vào SESSION (5 phút)
            $_SESSION[$sessionKey] = [
                'otp'      => $otp,
                'user_id'  => $user['MaNguoiDung'],
                'expires'  => time() + 300,
                'attempts' => 0,
            ];

            // Gửi OTP qua email
            $emailSent    = false;
            $emailService = $this->getEmailService();
            if ($emailService) {
                $emailResult = $emailService->sendOtpEmail([
                    'to'    => $email,
                    'otp'   => $otp,
                    'hoTen' => $user['HoTen'] ?? 'Khách hàng',
                ]);
                if ($emailResult['success']) {
                    $emailSent = true;
                } else {
                    error_log("Lỗi gửi OTP phone reset [{$email}]: " . $emailResult['message']);
                }
            }

            if (!$emailSent) {
                error_log("[DEV] Phone reset OTP for {$email}: {$otp}");
            }

            $responseData = [
                'email_masked' => $this->maskEmail($email),
                'expires_in'   => 300,
            ];
            if (!$emailSent) {
                $responseData['dev_otp'] = $otp;
            }

            $this->logAccess("SendOtpPhoneReset - Email: {$email}");
            $this->success($responseData, 'Mã OTP đã gửi về email ' . $this->maskEmail($email));

        } catch (\Exception $e) {
            error_log("Lỗi sendOtpPhoneReset: " . $e->getMessage());
            $this->error('Lỗi gửi OTP. Vui lòng thử lại', null, 500);
        }
    }

    // ============================================================
    // ENDPOINT 9: POST /api/auth/reset-phone-with-otp
    // ============================================================
    /**
     * Cập nhật số điện thoại mới sau khi xác minh OTP qua email
     * KHÔNG cần đăng nhập
     *
     * Yêu cầu: { email, otp, phone_moi }
     * Phản hồi:
     * {
     *   "success": true,
     *   "message": "Số điện thoại đã cập nhật thành công"
     * }
     */
    public function resetPhoneWithOtp(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'email'     => 'required|email',
            'otp'       => 'required|len:6',
            'phone_moi' => 'required|minlen:10',
        ]);
        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        $email    = trim($data['email']);
        $otp      = trim($data['otp']);
        $phoneMoi = preg_replace('/\D/', '', trim($data['phone_moi']));

        // Validate số điện thoại VN cơ bản (9-11 chữ số, bắt đầu 0 hoặc 84)
        if (!preg_match('/^(0|84)\d{9,10}$/', $phoneMoi)) {
            $this->error('Số điện thoại không hợp lệ (phải là số Việt Nam, 10-11 số)', null, 400);
            return;
        }

        try {
            // Kiểm tra OTP từ SESSION
            $sessionKey = 'phone_reset_otp_' . md5($email);

            if (!isset($_SESSION[$sessionKey])) {
                $this->error('Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới', null, 401);
                return;
            }

            $session = $_SESSION[$sessionKey];

            // Kiểm tra hết hạn
            if (time() > $session['expires']) {
                unset($_SESSION[$sessionKey]);
                $this->error('Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới', null, 401);
                return;
            }

            // Giới hạn số lần thử (tối đa 5)
            if ($session['attempts'] >= 5) {
                unset($_SESSION[$sessionKey]);
                $this->error('Quá nhiều lần thử sai. Vui lòng yêu cầu mã OTP mới', null, 429);
                return;
            }

            // Kiểm tra OTP
            if ($session['otp'] !== $otp) {
                $_SESSION[$sessionKey]['attempts']++;
                $remaining = 5 - $_SESSION[$sessionKey]['attempts'];
                $this->error("Mã OTP không đúng. Còn {$remaining} lần thử", null, 401);
                return;
            }

            // OTP hợp lệ — kiểm tra số điện thoại mới chưa được dùng
            $existingUser = User::findByPhone($phoneMoi);
            if ($existingUser && $existingUser['MaNguoiDung'] != $session['user_id']) {
                $this->error('Số điện thoại này đã được sử dụng bởi tài khoản khác', null, 409);
                return;
            }

            // Cập nhật số điện thoại
            $updated = User::updatePhone((int)$session['user_id'], $phoneMoi);
            if ($updated === false || $updated < 0) {
                $this->error('Không thể cập nhật số điện thoại. Vui lòng thử lại', null, 500);
                return;
            }

            // Xóa SESSION OTP
            unset($_SESSION[$sessionKey]);

            $this->logAccess("ResetPhoneWithOtp - UserID: {$session['user_id']} NewPhone: {$phoneMoi}");
            $this->success([], 'Số điện thoại đã cập nhật thành công. Bạn có thể đăng nhập với số mới');

        } catch (\Exception $e) {
            error_log("Lỗi resetPhoneWithOtp: " . $e->getMessage());
            $this->error('Lỗi cập nhật số điện thoại. Vui lòng thử lại', null, 500);
        }
    }

    /**
     * Che giấu email cho bảo mật
     */
    private function maskEmail(string $email): string
    {
        list($user, $domain) = explode('@', $email);
        $maskedUser = substr($user, 0, 2) . '****' . substr($user, -1);
        return $maskedUser . '@' . $domain;
    }
}

?>
