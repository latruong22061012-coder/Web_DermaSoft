<?php
/**
 * BenhNhan Model - Hồ sơ bệnh nhân
 */

namespace App\Models;

use App\Core\Model;

class BenhNhan extends Model
{
    protected static string $table = 'BenhNhan';
    protected static string $primaryKey = 'MaBenhNhan';

    /**
     * Lấy tất cả bệnh nhân
     */
    public static function getAllPatients(int $limit = 100, int $offset = 0): array
    {
        return self::all($limit, $offset);
    }

    /**
     * Lấy bệnh nhân theo ID
     */
    public static function getById(int $id): array|false
    {
        return self::findById($id);
    }

    /**
     * Lấy bệnh nhân theo số điện thoại
     */
    public static function getByPhone(string $phone): array|false
    {
        return self::findBy('SoDienThoai', $phone);
    }

    /**
     * Tạo bệnh nhân mới
     */
    public static function register(array $data): int|false
    {
        return self::create([
            'HoTen' => $data['HoTen'] ?? '',
            'NgaySinh' => $data['NgaySinh'] ?? null,
            'GioiTinh' => $data['GioiTinh'] ?? null,
            'SoDienThoai' => $data['SoDienThoai'] ?? '',
            'TienSuBenhLy' => $data['TienSuBenhLy'] ?? ''
        ]);
    }

    /**
     * Cập nhật thông tin bệnh nhân
     */
    public static function updateInfo(int $id, array $data): int
    {
        return self::update($id, $data);
    }

    /**
     * Lấy bệnh nhân theo tên
     */
    public static function getByName(string $name): array
    {
        $sql = "SELECT * FROM BenhNhan WHERE HoTen LIKE ? ORDER BY MaBenhNhan DESC";
        return self::query($sql, ['%' . $name . '%']);
    }
}
