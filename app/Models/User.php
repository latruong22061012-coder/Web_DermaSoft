<?php
/**
 * User Model - Xác thực nhân viên/lễ tân
 */

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected static string $table = 'NguoiDung';
    protected static string $primaryKey = 'MaNguoiDung';

    /**
     * Tìm người dùng theo username
     */
    public static function findByUsername(string $username): array|false
    {
        $sql = "SELECT * FROM NguoiDung WHERE TenDangNhap = ? AND TrangThaiTK = 1";
        return self::queryOne($sql, [$username]);
    }

    /**
     * Tìm người dùng theo số điện thoại (chỉ active)
     */
    public static function findByPhone(string $phone): array|false
    {
        $sql = "SELECT * FROM NguoiDung WHERE SoDienThoai = ? AND IsDeleted = 0";
        return self::queryOne($sql, [$phone]);
    }

    /**
     * Tìm người dùng theo SĐT bao gồm cả soft-deleted (dùng cho uniqueness check)
     */
    public static function findByPhoneAll(string $phone): array|false
    {
        $sql = "SELECT * FROM NguoiDung WHERE SoDienThoai = ?";
        return self::queryOne($sql, [$phone]);
    }

    /**
     * Xác thực người dùng (kiểm tra username & password)
     */
    public static function authenticate(string $username, string $password): array|false
    {
        $user = self::findByUsername($username);
        
        if (!$user) {
            return false;
        }

        // Kiểm tra mật khẩu với nhiều phương pháp hỗ trợ
        $stored_hash = $user['MatKhau'];
        $is_valid = false;
        
        // 1. Thử password_verify (bcrypt/argon2)
        if (password_verify($password, $stored_hash)) {
            $is_valid = true;
        }
        // 2. Thử md5 (legacy)
        elseif ($stored_hash === md5($password)) {
            $is_valid = true;
        }
        // 3. Thử plain text match (testing)
        elseif ($stored_hash === $password) {
            $is_valid = true;
        }
        
        if (!$is_valid) {
            return false;
        }

        // Loại bỏ mật khẩu khỏi kết quả
        unset($user['MatKhau']);
        return $user;
    }

    /**
     * Cập nhật thông tin người dùng theo ID
     */
    public static function updateInfo(int $userId, array $data): bool
    {
        if (empty($data)) return false;

        $setClauses = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $params[] = $value;
        }
        $params[] = $userId;

        $sql = "UPDATE NguoiDung SET " . implode(', ', $setClauses) . " WHERE MaNguoiDung = ?";
        try {
            $affected = \App\Core\Database::execute($sql, $params);
            return $affected > 0;
        } catch (\Exception $e) {
            error_log('User::updateInfo Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Tạo người dùng mới
     */
    public static function createUser(array $data): int|false
    {
        $userData = [
            'HoTen' => $data['HoTen'] ?? '',
            'SoDienThoai' => $data['SoDienThoai'] ?? '',
            'Email' => $data['Email'] ?? '',
            'TenDangNhap' => $data['TenDangNhap'] ?? '',
            'MatKhau' => password_hash($data['MatKhau'] ?? '', PASSWORD_DEFAULT),
            'MaVaiTro' => $data['MaVaiTro'] ?? 1,
            'TrangThaiTK' => $data['TrangThaiTK'] ?? 1,
            'DoiMatKhau' => $data['DoiMatKhau'] ?? 1
        ];

        return parent::create($userData);
    }

    /**
     * Lấy tất cả nhân viên
     */
    public static function getAllStaff(): array
    {
        $sql = "SELECT MaNguoiDung, HoTen, SoDienThoai, Email, TenDangNhap, MaVaiTro, TrangThaiTK FROM NguoiDung ORDER BY MaNguoiDung";
        return self::query($sql);
    }

    /**
     * Lấy nhân viên theo vai trò
     */
    public static function getByRole(int $roleId): array
    {
        $sql = "SELECT MaNguoiDung, HoTen, SoDienThoai, Email, TenDangNhap, MaVaiTro, TrangThaiTK 
                FROM NguoiDung WHERE MaVaiTro = ? AND TrangThaiTK = 1 ORDER BY HoTen";
        return self::query($sql, [$roleId]);
    }

    /**
     * Cập nhật mật khẩu
     */
    public static function updatePassword(int $id, string $newPassword): int
    {
        return self::update($id, [
            'MatKhau' => password_hash($newPassword, PASSWORD_DEFAULT),
            'DoiMatKhau' => 0
        ]);
    }

    /**
     * Khóa/Mở tài khoản
     */
    public static function toggleStatus(int $id, int $status): int
    {
        return self::update($id, ['TrangThaiTK' => $status]);
    }

    /**
     * Tìm người dùng theo email
     */
    public static function findByEmail(string $email): array|false
    {
        $sql = "SELECT * FROM NguoiDung WHERE Email = ? AND IsDeleted = 0";
        return self::queryOne($sql, [$email]);
    }

    /**
     * Tìm người dùng theo email bao gồm cả soft-deleted (dùng cho uniqueness check)
     */
    public static function findByEmailAll(string $email): array|false
    {
        $sql = "SELECT * FROM NguoiDung WHERE Email = ?";
        return self::queryOne($sql, [$email]);
    }

    /**
     * Sinh username 5 ký tự ngẫu nhiên
     */
    public static function generateUsername(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $username = '';
        for ($i = 0; $i < 5; $i++) {
            $username .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $username;
    }

    /**
     * Cập nhật số điện thoại cho người dùng
     */
    public static function updatePhone(int $id, string $newPhone): int
    {
        // Lưu số cũ vào SoDienThoaiCu
        return self::update($id, [
            'SoDienThoai' => $newPhone,
            'SoDienThoaiCu' => $newPhone,
            'NgayUpdateSoDienThoai' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Lưu email verification token
     */
    public static function saveEmailToken(int $id, string $token, $expiryMinutes = 1440): bool
    {
        $sql = "UPDATE NguoiDung SET EmailConfirmToken = ?, EmailConfirmTokenExpiry = DATEADD(MINUTE, ?, GETDATE()) WHERE MaNguoiDung = ?";
        return self::query($sql, [$token, $expiryMinutes, $id]) !== false;
    }

    /**
     * Xác thực email token
     */
    public static function verifyEmailToken(string $token): array|false
    {
        $sql = "SELECT * FROM NguoiDung WHERE EmailConfirmToken = ? AND EmailConfirmTokenExpiry > GETDATE() AND TrangThaiTK = 1";
        return self::queryOne($sql, [$token]);
    }

    /**
     * Cập nhật email đã xác thực
     */
    public static function confirmEmail(int $id): bool
    {
        $sql = "UPDATE NguoiDung SET EmailVerifiedAt = GETDATE(), EmailConfirmationNeeded = 0, LastEmailVerificationAt = GETDATE(), EmailConfirmToken = NULL WHERE MaNguoiDung = ?";
        return self::query($sql, [$id]) !== false;
    }

    /**
     * Kiểm tra email cần xác thực 3 năm
     */
    public static function needsEmailConfirmation(int $id): bool
    {
        $sql = "SELECT EmailConfirmationNeeded FROM NguoiDung WHERE MaNguoiDung = ? AND EmailConfirmationNeeded = 1";
        return self::queryOne($sql, [$id]) !== false;
    }
}
