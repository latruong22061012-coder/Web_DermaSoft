<?php
/**
 * AuthController - API Endpoints cho xác thực
 * POST /api/login - Đăng nhập
 * POST /api/logout - Đăng xuất
 * GET /api/me - Lấy thông tin user hiện tại
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Models\User;

class AuthController extends ApiController
{
    /**
     * POST /api/login
     * Đăng nhập người dùng
     * Yêu cầu: {username, password}
     */
    public function login(): void
    {
        Auth::startSession();

        // Rate limit: tối đa 5 lần login sai trong 5 phút
        $this->checkRateLimit('login', 5, 300);

        // Lấy dữ liệu từ request
        $data = $this->getJSON();

        // Validate
        $errors = $this->validate($data, [
            'username' => 'required|minlen:3',
            'password' => 'required|minlen:6'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
        }

        // Kiểm tra xác thực
        if (!Auth::login($data['username'], $data['password'])) {
            $this->error('Username hoặc password không đúng', null, 401);
        }

        // Kiểm tra tài khoản có hoạt động không
        if (!Auth::isAccountActive()) {
            $this->error('Tài khoản không hoạt động', null, 403);
        }

        $user = Auth::getCurrentUser();
        
        // Nếu cần, tạo token cho Windows App
        $token = Auth::generateToken($user['MaNhanVien']);

        $this->logAccess("Login - Username: {$data['username']}");

        $this->success([
            'user' => [
                'id' => $user['MaNhanVien'],
                'name' => $user['HoTen'],
                'username' => $user['TenDangNhap'],
                'email' => $user['Email'],
                'phone' => $user['SoDienThoai'],
                'role_id' => $user['MaVaiTro']
            ],
            'token' => $token,
            'need_password_change' => Auth::needsPasswordChange()
        ], 'Đăng nhập thành công');
    }

    /**
     * POST /api/logout
     * Đăng xuất người dùng
     */
    public function logout(): void
    {
        Auth::startSession();
        
        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Chưa đăng nhập');
        }

        $user = Auth::getCurrentUser();
        $this->logAccess("Logout - Username: {$user['TenDangNhap']}");

        Auth::logout();
        $this->success(null, 'Đăng xuất thành công');
    }

    /**
     * GET /api/me
     * Lấy thông tin người dùng hiện tại
     */
    public function getCurrentUser(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Chưa đăng nhập');
        }

        $user = Auth::getCurrentUser();

        // Không trả về mật khẩu
        unset($user['MatKhau']);

        $this->success($user, 'Lấy thông tin thành công');
    }

    /**
     * POST /api/change-password
     * Đổi mật khẩu
     * Yêu cầu: {old_password, new_password}
     */
    public function changePassword(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'old_password' => 'required|minlen:6',
            'new_password' => 'required|minlen:6'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
        }

        // Kiểm tra mật khẩu cũ
        $userModel = User::findById($user['MaNhanVien']);
        if (!password_verify($data['old_password'], $userModel['MatKhau'])) {
            $this->error('Mật khẩu cũ không đúng', null, 401);
        }

        // Cập nhật mật khẩu
        if (User::updatePassword($user['MaNhanVien'], $data['new_password'])) {
            $this->logAccess("Change password - User ID: {$user['MaNhanVien']}");
            $this->success(null, 'Đổi mật khẩu thành công');
        } else {
            $this->internalError('Không thể đổi mật khẩu');
        }
    }

    /**
     * POST /api/verify-token
     * Xác minh token (cho Windows App)
     * Yêu cầu: {token}
     */
    public function verifyToken(): void
    {
        Auth::startSession();

        $data = $this->getJSON();
        $token = $data['token'] ?? null;

        if (!$token) {
            $this->error('Token không được cung cấp', null, 400);
        }

        $userId = Auth::verifyToken($token);

        if (!$userId) {
            $this->unauthorized('Token không hợp lệ hoặc hết hạn');
        }

        $user = User::findById($userId);
        if (!$user || $user['TrangThaiTK'] != 1) {
            $this->error('Tài khoản không hoạt động', null, 403);
        }

        $this->success([
            'user_id' => $userId,
            'name' => $user['HoTen'],
            'valid' => true
        ], 'Token hợp lệ');
    }

    /**
     * POST /api/auth/register
     * Đăng ký tài khoản mới
     * Yêu cầu: {hoten, sodienthoai, email, tendangnhap, matkhau}
     */
    public function register(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        // Kiểm tra dữ liệu
        $errors = $this->validate($data, [
            'hoten' => 'required|minlen:3',
            'sodienthoai' => 'required|minlen:10',
            'email' => 'required|email',
            'tendangnhap' => 'required|minlen:3',
            'matkhau' => 'required|minlen:6'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        // Kiểm tra username đã tồn tại chưa
        if (User::findByUsername($data['tendangnhap'])) {
            $this->error('Username đã được sử dụng', null, 409);
            return;
        }

        // Tạo user mới
        $result = User::createUser([
            'HoTen' => $data['hoten'],
            'TenDangNhap' => $data['tendangnhap'],
            'MatKhau' => $data['matkhau'],
            'Email' => $data['email'],
            'SoDienThoai' => $data['sodienthoai'],
            'MaVaiTro' => 4,  // Mặc định là bệnh nhân
            'TrangThaiTK' => 1  // Kích hoạt
        ]);

        if (!$result) {
            $this->internalError('Không thể tạo tài khoản');
            return;
        }

        $this->logAccess("Register - Username: {$data['tendangnhap']}");
        $this->success(['user_id' => $result], 'Đăng ký thành công', 201);
    }

    /**
     * POST /api/auth/forgot-password
     * Quên mật khẩu - Gửi link reset qua email
     * Yêu cầu: {email}
     */
    public function forgotPassword(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        // Kiểm tra email
        $errors = $this->validate($data, [
            'email' => 'required|email'
        ]);

        if (!empty($errors)) {
            $this->error('Email không hợp lệ', $errors, 400);
            return;
        }

        // TODO: Kiểm tra email có tồn tại trong DB
        // Sau này gửi email reset password qua dịch vụ email
        
        $this->logAccess("Forgot password - Email: {$data['email']}");
        $this->success(null, 'Nếu email tồn tại, link reset sẽ được gửi');
    }

    /**
     * POST /api/auth/send-otp
     * Gửi OTP qua SMS để xác thực
     * Yêu cầu: {sodienthoai}
     * Phản hồi: {success, otp_expiry_seconds, message}
     */
    public function sendOTP(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10'
        ]);

        if (!empty($errors)) {
            $this->error('Số điện thoại không hợp lệ', $errors, 400);
            return;
        }

        $phone = $data['sodienthoai'];

        try {
            // Tạo OTP mới
            $otpModel = new \App\Models\XacThucOTP();
            $otp = $otpModel->generateOTP($phone, 'login');

            // TODO: Gửi OTP qua SMS (Twilio, Viettel, v.v.)
            // Với mục đích test, in OTP ra logs
            error_log("🔐 OTP FOR $phone: $otp");
            
            // Trên thực tế, sẽ gửi SMS ở đây
            // $this->sendSMS($phone, "Mã OTP của bạn: $otp. Hết hạn sau 5 phút");

            $this->success([
                'otp_expiry_seconds' => 300,  // 5 phút
                'phone_masked' => substr($phone, 0, 3) . ' **** ' . substr($phone, -3)
            ], 'OTP đã được gửi. Kiểm tra tin nhắn của bạn');

        } catch (\Exception $e) {
            $this->error('Không thể gửi OTP', null, 500);
        }
    }

    /**
     * POST /api/auth/verify-otp
     * Xác thực OTP
     * Yêu cầu: {sodienthoai, otp}
     * Phản hồi: {success, token, message}
     */
    public function verifyOTP(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10',
            'otp' => 'required|len:6'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        try {
            $otpModel = new \App\Models\XacThucOTP();
            $result = $otpModel->verifyOTP($data['sodienthoai'], $data['otp']);

            if (!$result['valid']) {
                $this->error('OTP không đúng hoặc đã hết hạn', null, 401);
                return;
            }

            // Tạo temporary token để proceed sang bước tiếp theo
            // Token này chỉ dùng để verify OTP, chưa đăng nhập đầy đủ
            $tempToken = bin2hex(random_bytes(32));
            $_SESSION['otp_verified_' . $data['sodienthoai']] = [
                'timestamp' => time(),
                'token' => $tempToken,
                'expires_at' => time() + 600  // 10 phút
            ];

            $this->logAccess("OTP Verified - Phone: {$data['sodienthoai']}");

            $this->success([
                'verified' => true,
                'temp_token' => $tempToken,
                'next_step' => 'login'  // Hoặc 'register' tùy use case
            ], 'OTP xác thực thành công');

        } catch (\Exception $e) {
            $this->error('Lỗi xác thực OTP: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/auth/login-with-otp
     * Đăng nhập sau khi OTP đã được xác thực
     * Yêu cầu: {sodienthoai, otp, temp_token}
     * Phản hồi: {user, token}
     */
    public function loginWithOTP(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10',
            'otp' => 'required|len:6',
            'temp_token' => 'required'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        try {
            // Verify OTP one more time
            $otpModel = new \App\Models\XacThucOTP();
            $result = $otpModel->verifyOTP($data['sodienthoai'], $data['otp']);

            if (!$result['valid']) {
                $this->error('OTP không đúng', null, 401);
                return;
            }

            // Tìm user bằng phone
            $userModel = new User();
            $user = $userModel->findByPhone($data['sodienthoai']);

            if (!$user) {
                $this->error('Số điện thoại chưa được đăng ký', null, 404);
                return;
            }

            // Kiểm tra tài khoản có hoạt động
            if ($user['TrangThaiTK'] != 1) {
                $this->error('Tài khoản không hoạt động', null, 403);
                return;
            }

            // Setup session
            $_SESSION['user_id'] = $user['MaNhanVien'];
            $_SESSION['username'] = $user['TenDangNhap'];
            $_SESSION['phone'] = $data['sodienthoai'];

            // Tạo token
            $token = Auth::generateToken($user['MaNhanVien']);

            $this->logAccess("OTP Login - Phone: {$data['sodienthoai']}, Username: {$user['TenDangNhap']}");

            $this->success([
                'user' => [
                    'id' => $user['MaNhanVien'],
                    'name' => $user['HoTen'],
                    'username' => $user['TenDangNhap'],
                    'email' => $user['Email'],
                    'phone' => $user['SoDienThoai'],
                    'role_id' => $user['MaVaiTro']
                ],
                'token' => $token
            ], 'Đăng nhập thành công');

        } catch (\Exception $e) {
            $this->error('Lỗi đăng nhập: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/auth/register-with-otp
     * Đăng ký sau khi OTP đã được xác thực
     * Yêu cầu: {sodienthoai, otp, hoten, email, tendangnhap, matkhau}
     * Phản hồi: {success, user_id}
     */
    public function registerWithOTP(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10',
            'otp' => 'required|len:6',
            'hoten' => 'required|minlen:3',
            'email' => 'required|email',
            'tendangnhap' => 'required|minlen:3',
            'matkhau' => 'required|minlen:6'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        try {
            // Verify OTP
            $otpModel = new \App\Models\XacThucOTP();
            $result = $otpModel->verifyOTP($data['sodienthoai'], $data['otp']);

            if (!$result['valid']) {
                $this->error('OTP không đúng', null, 401);
                return;
            }

            // Kiểm tra username đã tồn tại
            $userModel = new User();
            if ($userModel->findByUsername($data['tendangnhap'])) {
                $this->error('Username đã được sử dụng', null, 409);
                return;
            }

            // Tạo user mới
            $userId = $userModel->createUser([
                'HoTen' => $data['hoten'],
                'TenDangNhap' => $data['tendangnhap'],
                'MatKhau' => $data['matkhau'],
                'Email' => $data['email'],
                'SoDienThoai' => $data['sodienthoai'],
                'MaVaiTro' => 4,  // Mặc định là bệnh nhân
                'TrangThaiTK' => 1  // Kích hoạt
            ]);

            if (!$userId) {
                $this->internalError('Không thể tạo tài khoản');
                return;
            }

            $this->logAccess("OTP Register - Phone: {$data['sodienthoai']}, Username: {$data['tendangnhap']}");

            $this->success([
                'user_id' => $userId,
                'message' => 'Đăng ký thành công. Vui lòng đăng nhập.'
            ], 'Tài khoản đã được tạo', 201);

        } catch (\Exception $e) {
            $this->error('Lỗi đăng ký: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/auth/reset-password-with-otp
     * Đặt lại mật khẩu sau OTP xác thực
     * Yêu cầu: {sodienthoai, otp, new_password}
     * Phản hồi: {success, message}
     */
    public function resetPasswordWithOTP(): void
    {
        Auth::startSession();
        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'sodienthoai' => 'required|minlen:10',
            'otp' => 'required|len:6',
            'new_password' => 'required|minlen:6'
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        try {
            // Verify OTP
            $otpModel = new \App\Models\XacThucOTP();
            $result = $otpModel->verifyOTP($data['sodienthoai'], $data['otp']);

            if (!$result['valid']) {
                $this->error('OTP không đúng', null, 401);
                return;
            }

            // Tìm user
            $userModel = new User();
            $user = $userModel->findByPhone($data['sodienthoai']);

            if (!$user) {
                $this->error('Số điện thoại không tồn tại', null, 404);
                return;
            }

            // Update password (User model sẽ hash nó)
            $userModel->updatePassword($user['MaNhanVien'], $data['new_password']);

            $this->logAccess("Password Reset - Phone: {$data['sodienthoai']}");

            $this->success(null, 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập lại');

        } catch (\Exception $e) {
            $this->error('Lỗi cập nhật mật khẩu: ' . $e->getMessage(), null, 500);
        }
    }
}
