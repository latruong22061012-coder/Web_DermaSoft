<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class HangThanhVien extends Model
{
    protected static string $table = 'HangThanhVien';
    protected static string $primaryKey = 'MaHang';

    /**
     * Lấy tất cả hạng thành viên
     */
    public static function all($limit = null, $offset = null): array
    {
        return parent::all($limit, $offset);
    }

    /**
     * Lấy thông tin một hạng theo ID
     */
    public static function getById($maHang): array|false
    {
        return self::findById($maHang);
    }

    /**
     * Lấy hạng phù hợp dựa trên điểm tích lũy
     * @param int $diemTichLuy - Điểm hiện tại của khách hàng
     * @return array|false - Thông tin hạng phù hợp
     */
    public static function getHangByDiem($diemTichLuy): array|false
    {
        $sql = "SELECT * FROM HangThanhVien 
                WHERE DiemToiThieu <= ? 
                ORDER BY DiemToiThieu DESC 
                LIMIT 1";
        $result = self::queryOne($sql, [$diemTichLuy]);
        return $result ?: false;
    }
}
