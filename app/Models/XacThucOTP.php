<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use Exception;

class XacThucOTP
{
    private $db;
    private const OTP_EXPIRY_MINUTES = 5;  // OTP hết hạn sau 5 phút
    private const OTP_LENGTH = 6;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Tạo và lưu OTP mới
     */
    public function generateOTP($soDienThoai, $useCase = 'login'): string
    {
        // Xóa OTP cũ chưa xác thực
        $this->deleteExpiredOTP();

        // Tạo OTP ngẫu nhiên 6 chữ số
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Tính thời gian hết hạn
        $expiryTime = date('Y-m-d H:i:s', strtotime('+' . self::OTP_EXPIRY_MINUTES . ' minutes'));

        $query = "INSERT INTO XacThucOTP (SoDienThoai, MaOTP, TrangThai, NgayTao, NgayHetHan) 
                  VALUES (:SoDienThoai, :MaOTP, 0, GETDATE(), :NgayHetHan)";

        try {
            $this->db->query($query, [
                ':SoDienThoai' => $soDienThoai,
                ':MaOTP' => $otp,
                ':NgayHetHan' => $expiryTime
            ]);
            return $otp;
        } catch (Exception $e) {
            error_log("Error generating OTP: " . $e->getMessage());
            throw new Exception("Failed to generate OTP");
        }
    }

    /**
     * Xác thực OTP
     */
    public function verifyOTP($soDienThoai, $otp): array
    {
        $query = "SELECT * FROM XacThucOTP 
                  WHERE SoDienThoai = :SoDienThoai 
                  AND MaOTP = :MaOTP
                  AND TrangThai IN (0, 1)
                  ORDER BY NgayTao DESC
                  LIMIT 1";

        $result = $this->db->query($query, [
            ':SoDienThoai' => $soDienThoai,
            ':MaOTP' => $otp
        ])->fetch();

        if (!$result) {
            return [
                'success' => false,
                'message' => 'OTP không tồn tại hoặc không hợp lệ',
                'code' => 'OTP_NOT_FOUND'
            ];
        }

        // Kiểm tra OTP đã hết hạn chưa
        $expiryTime = strtotime($result['NgayHetHan']);
        if ($expiryTime < time()) {
            // Đánh dấu OTP là hết hạn
            $updateQuery = "UPDATE XacThucOTP SET TrangThai = 2 WHERE MaXacThuc = :MaXacThuc";
            $this->db->query($updateQuery, [':MaXacThuc' => $result['MaXacThuc']]);

            return [
                'success' => false,
                'message' => 'OTP đã hết hạn',
                'code' => 'OTP_EXPIRED'
            ];
        }

        // Cập nhật trạng thái OTP thành đã xác thực
        $updateQuery = "UPDATE XacThucOTP SET TrangThai = 1 WHERE MaXacThuc = :MaXacThuc";

        try {
            $this->db->query($updateQuery, [':MaXacThuc' => $result['MaXacThuc']]);

            return [
                'success' => true,
                'message' => 'Xác thực OTP thành công',
                'code' => 'OTP_VERIFIED',
                'maBenhNhan' => $result['MaBenhNhan']
            ];
        } catch (Exception $e) {
            error_log("Error verifying OTP: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi khi xác thực OTP',
                'code' => 'OTP_VERIFY_ERROR'
            ];
        }
    }

    /**
     * Kiểm tra OTP có hiệu lực không (không cần xác nhận OTP)
     */
    public function isOTPValid($soDienThoai, $otp): bool
    {
        $query = "SELECT * FROM XacThucOTP 
                  WHERE SoDienThoai = :SoDienThoai 
                  AND MaOTP = :MaOTP
                  AND TrangThai != 2
                  ORDER BY NgayTao DESC
                  LIMIT 1";

        $result = $this->db->query($query, [
            ':SoDienThoai' => $soDienThoai,
            ':MaOTP' => $otp
        ])->fetch();

        if (!$result) {
            return false;
        }

        // Kiểm tra OTP đã hết hạn chưa
        $expiryTime = strtotime($result['NgayHetHan']);
        return $expiryTime >= time();
    }

    /**
     * Lấy lịch sử OTP của một số điện thoại
     */
    public function getOTPHistory($soDienThoai, $limit = 10): array
    {
        $query = "SELECT TOP :Limit * FROM XacThucOTP 
                  WHERE SoDienThoai = :SoDienThoai 
                  ORDER BY NgayTao DESC";

        return $this->db->query($query, [
            ':SoDienThoai' => $soDienThoai,
            ':Limit' => $limit
        ])->fetchAll();
    }

    /**
     * Xóa OTP cũ (hết hạn hoặc đã xác thực)
     */
    public function deleteExpiredOTP(): int
    {
        $query = "DELETE FROM XacThucOTP 
                  WHERE NgayHetHan < GETDATE() 
                  OR (TrangThai = 1 AND NgayTao < DATEADD(HOUR, -1, GETDATE()))";

        try {
            return $this->db->query($query)->rowCount();
        } catch (Exception $e) {
            error_log("Error deleting expired OTP: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Kiểm tra số điện thoại đã đăng ký chưa (qua OTP đã xác thực)
     */
    public function isPhoneNumberVerified($soDienThoai): bool
    {
        $query = "SELECT MaBenhNhan FROM XacThucOTP 
                  WHERE SoDienThoai = :SoDienThoai 
                  AND TrangThai = 1 
                  AND MaBenhNhan IS NOT NULL
                  LIMIT 1";

        $result = $this->db->query($query, [':SoDienThoai' => $soDienThoai])->fetch();
        return $result !== null && $result['MaBenhNhan'] !== null;
    }

    /**
     * Liên kết OTP đã xác thực với tài khoản khách hàng
     */
    public function linkToCustomer($soDienThoai, $maBenhNhan): bool
    {
        $query = "UPDATE XacThucOTP 
                  SET MaBenhNhan = :MaBenhNhan 
                  WHERE SoDienThoai = :SoDienThoai 
                  AND TrangThai = 1 
                  AND MaBenhNhan IS NULL";

        try {
            $this->db->query($query, [
                ':SoDienThoai' => $soDienThoai,
                ':MaBenhNhan' => $maBenhNhan
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Error linking OTP to customer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy OTP được xác thực gần nhất của một số điện thoại
     */
    public function getLatestVerifiedOTP($soDienThoai): ?array
    {
        $query = "SELECT TOP 1 * FROM XacThucOTP 
                  WHERE SoDienThoai = :SoDienThoai 
                  AND TrangThai = 1 
                  ORDER BY NgayTao DESC";

        $result = $this->db->query($query, [':SoDienThoai' => $soDienThoai])->fetch();
        return $result ?: null;
    }

    /**
     * Đếm số lần tạo OTP của một số điện thoai trong 1 giờ (chống spam)
     */
    public function getOTPAttemptCount($soDienThoai, $minutes = 60): int
    {
        $query = "SELECT COUNT(*) as Attempts FROM XacThucOTP 
                  WHERE SoDienThoai = :SoDienThoai 
                  AND NgayTao >= DATEADD(MINUTE, -:Minutes, GETDATE())";

        $result = $this->db->query($query, [
            ':SoDienThoai' => $soDienThoai,
            ':Minutes' => $minutes
        ])->fetch();

        return (int)($result['Attempts'] ?? 0);
    }

    /**
     * Kiểm tra số lần thử OTP - chống brute force
     */
    public function isOTPAttemptExceeded($soDienThoai, $maxAttempts = 5, $withinMinutes = 15): bool
    {
        $query = "SELECT COUNT(*) as Attempts FROM XacThucOTP 
                  WHERE SoDienThoai = :SoDienThoai 
                  AND TrangThai = 0 
                  AND NgayTao >= DATEADD(MINUTE, -:Minutes, GETDATE())";

        $result = $this->db->query($query, [
            ':SoDienThoai' => $soDienThoai,
            ':Minutes' => $withinMinutes
        ])->fetch();

        return ((int)($result['Attempts'] ?? 0)) >= $maxAttempts;
    }
}
