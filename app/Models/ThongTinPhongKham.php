<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use \Exception;

class ThongTinPhongKham
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Lấy thông tin phòng khám chính (thường chỉ có 1 bản ghi)
     */
    public function getInfo(): ?array
    {
        $query = "SELECT TOP 1 * FROM ThongTinPhongKham ORDER BY MaThongTin DESC";
        $result = $this->db->query($query)->fetch();
        return $result ?: null;
    }

    /**
     * Lấy thông tin theo ID
     */
    public function getById($maThongTin): ?array
    {
        $query = "SELECT * FROM ThongTinPhongKham WHERE MaThongTin = :MaThongTin";
        $result = $this->db->query($query, [':MaThongTin' => $maThongTin])->fetch();
        return $result ?: null;
    }

    /**
     * Tạo thông tin phòng khám
     */
    public function create($tenPhongKham, $diaChi, $soDienThoai, $email = null, $website = null, $logo = null): bool
    {
        $query = "INSERT INTO ThongTinPhongKham (TenPhongKham, DiaChi, SoDienThoai, Email, Website, Logo, DatCapNhatLuc) 
                  VALUES (:TenPhongKham, :DiaChi, :SoDienThoai, :Email, :Website, :Logo, GETDATE())";

        try {
            $this->db->query($query, [
                ':TenPhongKham' => $tenPhongKham,
                ':DiaChi' => $diaChi,
                ':SoDienThoai' => $soDienThoai,
                ':Email' => $email,
                ':Website' => $website,
                ':Logo' => $logo
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Error creating clinic info: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cập nhật thông tin phòng khám
     */
    public function update($maThongTin, $tenPhongKham = null, $logo = null, $slogan = null, $diaChi = null, 
                          $soDienThoai = null, $email = null, $website = null, $gioMoCua = null, 
                          $gioDongCua = null, $lichLamViec = null, $moTa = null): bool
    {
        $updates = [];
        $params = [':MaThongTin' => $maThongTin];

        if ($tenPhongKham !== null) {
            $updates[] = "TenPhongKham = :TenPhongKham";
            $params[':TenPhongKham'] = $tenPhongKham;
        }
        if ($logo !== null) {
            $updates[] = "Logo = :Logo";
            $params[':Logo'] = $logo;
        }
        if ($slogan !== null) {
            $updates[] = "Slogan = :Slogan";
            $params[':Slogan'] = $slogan;
        }
        if ($diaChi !== null) {
            $updates[] = "DiaChi = :DiaChi";
            $params[':DiaChi'] = $diaChi;
        }
        if ($soDienThoai !== null) {
            $updates[] = "SoDienThoai = :SoDienThoai";
            $params[':SoDienThoai'] = $soDienThoai;
        }
        if ($email !== null) {
            $updates[] = "Email = :Email";
            $params[':Email'] = $email;
        }
        if ($website !== null) {
            $updates[] = "Website = :Website";
            $params[':Website'] = $website;
        }
        if ($gioMoCua !== null) {
            $updates[] = "GioMoCua = :GioMoCua";
            $params[':GioMoCua'] = $gioMoCua;
        }
        if ($gioDongCua !== null) {
            $updates[] = "GioDongCua = :GioDongCua";
            $params[':GioDongCua'] = $gioDongCua;
        }
        if ($lichLamViec !== null) {
            $updates[] = "LichLamViecHangTuan = :LichLamViec";
            $params[':LichLamViec'] = $lichLamViec;
        }
        if ($moTa !== null) {
            $updates[] = "MoTa = :MoTa";
            $params[':MoTa'] = $moTa;
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "DatCapNhatLuc = GETDATE()";

        $query = "UPDATE ThongTinPhongKham SET " . implode(", ", $updates) . " WHERE MaThongTin = :MaThongTin";

        try {
            $this->db->query($query, $params);
            return true;
        } catch (Exception $e) {
            error_log("Error updating clinic info: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cập nhật logo
     */
    public function updateLogo($maThongTin, $logoPath): bool
    {
        // Kiểm tra file tồn tại
        if (!file_exists($logoPath) && !filter_var($logoPath, FILTER_VALIDATE_URL)) {
            error_log("Logo file not found: $logoPath");
            return false;
        }

        return $this->update($maThongTin, logo: $logoPath);
    }

    /**
     * Cập nhật giờ mở/đóng cửa
     */
    public function updateOperatingHours($maThongTin, $gioMoCua, $gioDongCua): bool
    {
        // Validate time format (HH:MM:SS)
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $gioMoCua) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $gioDongCua)) {
            error_log("Invalid time format");
            return false;
        }

        return $this->update($maThongTin, gioMoCua: $gioMoCua, gioDongCua: $gioDongCua);
    }

    /**
     * Cập nhật lịch làm việc hàng tuần
     */
    public function updateWeeklySchedule($maThongTin, $schedule): bool
    {
        return $this->update($maThongTin, lichLamViec: $schedule);
    }

    /**
     * Cập nhật thông tin liên hệ
     */
    public function updateContactInfo($maThongTin, $soDienThoai, $email, $website = null): bool
    {
        // Validate phone number (Vietnam format)
        if (!preg_match('/^0\d{9,10}$/', $soDienThoai)) {
            error_log("Invalid phone number format");
            return false;
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email format");
            return false;
        }

        return $this->update($maThongTin, soDienThoai: $soDienThoai, email: $email, website: $website);
    }

    /**
     * Lấy logo URL
     */
    public function getLogoUrl(): ?string
    {
        $info = $this->getInfo();
        return $info ? $info['Logo'] : null;
    }

    /**
     * Lấy giờ mở cửa
     */
    public function getOpeningHour(): ?string
    {
        $info = $this->getInfo();
        return $info ? $info['GioMoCua'] : null;
    }

    /**
     * Lấy giờ đóng cửa
     */
    public function getClosingHour(): ?string
    {
        $info = $this->getInfo();
        return $info ? $info['GioDongCua'] : null;
    }

    /**
     * Kiểm tra phòng khám có đang mở cửa không (dựa vào giờ hiện tại)
     */
    public function isOpen(): bool
    {
        $info = $this->getInfo();
        if (!$info || !$info['GioMoCua'] || !$info['GioDongCua']) {
            return true; // Mặc định là mở
        }

        $currentTime = strtotime(date('H:i:s'));
        $openTime = strtotime($info['GioMoCua']);
        $closeTime = strtotime($info['GioDongCua']);

        return $currentTime >= $openTime && $currentTime < $closeTime;
    }

    /**
     * Lấy trạng thái mở/đóng cửa
     */
    public function getStatus(): array
    {
        $isOpen = $this->isOpen();
        return [
            'isOpen' => $isOpen,
            'status' => $isOpen ? 'Đang mở' : 'Đã đóng',
            'message' => $isOpen ? 'Phòng khám đang tiếp nhận khách hàng' : 'Phòng khám hiện đã đóng'
        ];
    }

    /**
     * Lấy tất cả thông tin (dùng cho footer, header, etc.)
     */
    public function getAllInfo(): ?array
    {
        $info = $this->getInfo();
        if (!$info) {
            return null;
        }

        return [
            'tenPhongKham' => $info['TenPhongKham'] ?? 'DramaSoft Clinic',
            'logo' => $info['Logo'],
            'slogan' => $info['Slogan'],
            'diaChi' => $info['DiaChi'],
            'soDienThoai' => $info['SoDienThoai'],
            'email' => $info['Email'],
            'website' => $info['Website'],
            'gioMoCua' => $info['GioMoCua'],
            'gioDongCua' => $info['GioDongCua'],
            'lichLamViec' => $info['LichLamViecHangTuan'],
            'moTa' => $info['MoTa'],
            'isOpen' => $this->isOpen(),
            'datCapNhatLuc' => $info['DatCapNhatLuc']
        ];
    }

    /**
     * Xóa thông tin phòng khám
     */
    public function delete($maThongTin): bool
    {
        try {
            $query = "DELETE FROM ThongTinPhongKham WHERE MaThongTin = :MaThongTin";
            $this->db->query($query, [':MaThongTin' => $maThongTin]);
            return true;
        } catch (Exception $e) {
            error_log("Error deleting clinic info: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kiểm tra phòng khám đã được cấu hình chưa
     */
    public function isConfigured(): bool
    {
        $info = $this->getInfo();
        return $info !== null && !empty($info['TenPhongKham']);
    }

    /**
     * Reset về thông tin mặc định
     */
    public function resetToDefault(): bool
    {
        $info = $this->getInfo();
        if (!$info) {
            return $this->create(
                'DramaSoft Clinic',
                '123 Đường Võ Văn Kiệt, Quận 1, TP. Hồ Chí Minh',
                '0283123456',
                'contact@dramaSoft.com',
                'dramaSoft.com',
                'images/logo/dramaSoft_logo.png'
            );
        }

        return $this->update(
            $info['MaThongTin'],
            tenPhongKham: 'DramaSoft Clinic',
            diaChi: '123 Đường Võ Văn Kiệt, Quận 1, TP. Hồ Chí Minh',
            soDienThoai: '0283123456',
            email: 'contact@dramaSoft.com',
            website: 'dramaSoft.com',
            logo: 'images/logo/dramaSoft_logo.png'
        );
    }
}
