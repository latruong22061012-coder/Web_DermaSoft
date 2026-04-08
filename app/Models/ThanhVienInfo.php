<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ThanhVienInfo extends Model
{
    protected static string $table = 'ThanhVienInfo';
    protected static string $primaryKey = 'MaThanhVien';

    /**
     * Lấy tất cả thành viên
     */
    public static function all($limit = 20, $offset = 0): array
    {
        return parent::all($limit, $offset);
    }

    /**
     * Đếm tổng thành viên
     */
    public static function count(): int
    {
        return parent::count();
    }

    /**
     * Lấy thành viên theo ID
     */
    public static function getById($id): array|false
    {
        return parent::findById((int)$id);
    }

    /**
     * Lấy thành viên theo bệnh nhân ID
     */
    public static function getByPatientId($patientId): array|false
    {
        return parent::findBy('MaBenhNhan', (int)$patientId);
    }

    /**
     * Kiểm tra thành viên tồn tại
     */
    public static function exists($id): bool
    {
        return parent::exists((int)$id);
    }

    /**
     * Cập nhật thành viên
     */
    public static function update(int $id, array $data): int
    {
        return parent::update($id, $data);
    }

    /**
     * Xóa thành viên
     */
    public static function delete(int $id): int
    {
        return parent::delete($id);
    }

    /**
     * Lấy top thành viên theo điểm
     */
    public static function getTopPoints($limit = 10): array
    {
        $sql = "SELECT * FROM ThanhVienInfo ORDER BY DiemThuong DESC LIMIT ?";
        return parent::query($sql, [$limit]);
    }
}
