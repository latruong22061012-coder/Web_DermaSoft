<?php
/**
 * DichVu Model - Dịch vụ y tế
 */

namespace App\Models;

use App\Core\Model;

class DichVu extends Model
{
    protected static string $table = 'DichVu';
    protected static string $primaryKey = 'MaDichVu';

    /**
     * Lấy tất cả dịch vụ
     */
    public static function getAllServices(): array
    {
        return self::all(100);
    }

    /**
     * Lấy dịch vụ theo ID
     */
    public static function getById(int $id): array|false
    {
        return self::findById($id);
    }

    /**
     * Lấy dịch vụ theo tên
     */
    public static function getByName(string $name): array|false
    {
        return self::findBy('TenDichVu', $name);
    }

    /**
     * Lấy dịch vụ trong khoảng giá
     */
    public static function getByPriceRange(float $min, float $max): array
    {
        $sql = "SELECT * FROM DichVu WHERE DonGia BETWEEN ? AND ? ORDER BY DonGia ASC";
        return self::query($sql, [$min, $max]);
    }

    /**
     * Tạo dịch vụ mới
     */
    public static function createService(string $name, float $price): int|false
    {
        return self::create([
            'TenDichVu' => $name,
            'DonGia' => $price
        ]);
    }
}
