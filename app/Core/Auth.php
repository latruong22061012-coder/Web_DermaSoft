<?php
/**
 * Auth Class - Xác thực và quản lý session
 */

namespace App\Core;

use App\Models\User;

class Auth
{
    private const SESSION_KEY = 'authenticated_user';
    private const SESSION_LIFETIME = 3600; // 1 hour

    /**
     * Đăng nhập người dùng
     */
    public static function login(string $username, string $password): bool
    {
        $user = User::authenticate($username, $password);
        
        if (!$user) {
            return false;
        }

        // Chống session fixation: tạo session ID mới sau khi login
        session_regenerate_id(true);

        // Lưu vào session
        $_SESSION[self::SESSION_KEY] = $user;
        $_SESSION['login_time'] = time();
        
        return true;
    }

    /**
     * Kiểm tra người dùng đã đăng nhập chưa
     */
    public static function isAuthenticated(): bool
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        // Kiểm tra session hết hạn
        if (isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > self::SESSION_LIFETIME) {
                self::logout();
                return false;
            }
        }

        return true;
    }

    /**
     * Lấy thông tin người dùng hiện tại
     */
    public static function getCurrentUser(): array|null
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Lấy ID người dùng hiện tại
     */
    public static function getCurrentUserId(): int|null
    {
        $user = self::getCurrentUser();
        return $user ? (int)($user['MaNguoiDung'] ?? null) : null;
    }

    /**
     * Kiểm tra quyền (vai trò)
     */
    public static function hasRole(int $roleId): bool
    {
        $user = self::getCurrentUser();
        return $user && (int)$user['MaVaiTro'] === $roleId;
    }

    /**
     * Đăng xuất
     */
    public static function logout(): void
    {
        self::startSession();
        // Xóa tất cả session data trước khi destroy
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /**
     * Kiểm tra tài khoản có hoạt động không
     */
    public static function isAccountActive(): bool
    {
        $user = self::getCurrentUser();
        return $user && $user['TrangThaiTK'] == 1;
    }

    /**
     * Cần đổi mật khẩu không
     */
    public static function needsPasswordChange(): bool
    {
        $user = self::getCurrentUser();
        return $user && $user['DoiMatKhau'] == 1;
    }

    /**
     * Bắt đầu session (gọi ở đầu app)
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Tạo token cho Windows App (optional JWT hoặc session ID)
     */
    public static function generateToken(int $userId): string
    {
        // Simple session-based token
        $token = bin2hex(random_bytes(32));
        $_SESSION['tokens'][$userId] = [
            'token' => $token,
            'created_at' => time(),
            'expires_at' => time() + 86400  // 24 hours
        ];
        return $token;
    }

    /**
     * Xác minh token từ Windows App
     */
    public static function verifyToken(string $token): int|false
    {
        if (!isset($_SESSION['tokens'])) {
            return false;
        }

        foreach ($_SESSION['tokens'] as $userId => $data) {
            if ($data['token'] === $token && time() < $data['expires_at']) {
                return $userId;
            }
        }

        return false;
    }
}
