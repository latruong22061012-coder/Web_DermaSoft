<?php
/**
 * VaiTro Model - Vai trò/Quyền hạn
 */

namespace App\Models;

use App\Core\Model;

class VaiTro extends Model
{
    protected static string $table = 'VaiTro';
    protected static string $primaryKey = 'MaVaiTro';

    /**
     * Lấy tất cả các vai trò
     */
    public static function getAllRoles(): array
    {
        return self::all(100);
    }

    /**
     * Lấy vai trò theo ID
     */
    public static function getById(int $id): array|false
    {
        return self::findById($id);
    }

    /**
     * Lấy vai trò theo tên
     */
    public static function getByName(string $name): array|false
    {
        return self::findBy('TenVaiTro', $name);
    }
}
