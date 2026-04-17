<?php
/**
 * HoaDon Model - Hóa đơn
 */

namespace App\Models;

use App\Core\Model;

class HoaDon extends Model
{
    protected static string $table = 'HoaDon';
    protected static string $primaryKey = 'MaHoaDon';

    /**
     * Lấy tất cả hóa đơn
     */
    public static function getAllInvoices(int $limit = 100, int $offset = 0): array
    {
        return self::all($limit, $offset);
    }

    /**
     * Lấy hóa đơn theo ID
     */
    public static function getById(int $id): array|false
    {
        return self::findById($id);
    }

    /**
     * Lấy hóa đơn theo phiếu khám
     */
    public static function getByConsultationId(int $consultationId): array|false
    {
        return self::findBy('MaPhieuKham', $consultationId);
    }

    /**
     * Lấy hóa đơn theo bệnh nhân
     */
    public static function getByPatientId(int $patientId): array
    {
        $sql = "SELECT * FROM HoaDon WHERE MaBenhNhan = ? ORDER BY MaHoaDon DESC";
        return self::query($sql, [$patientId]);
    }

    /**
     * Lấy hóa đơn chưa thanh toán (TrangThai = 0)
     */
    public static function getUnpaidInvoices(): array
    {
        $sql = "SELECT * FROM HoaDon WHERE TrangThai = 0 ORDER BY MaHoaDon DESC";
        return self::query($sql);
    }

    /**
     * Lấy hóa đơn được thanh toán (TrangThai = 1)
     */
    public static function getPaidInvoices(): array
    {
        $sql = "SELECT * FROM HoaDon WHERE TrangThai = 1 ORDER BY MaHoaDon DESC";
        return self::query($sql);
    }

    /**
     * Tạo hóa đơn mới
     */
    public static function createInvoice(array $data): int|false
    {
        return self::create([
            'MaPhieuKham' => $data['MaPhieuKham'] ?? 0,
            'TongTien' => $data['TongTien'] ?? 0,
            'GiamGia' => $data['GiamGia'] ?? 0,
            'TienKhachTra' => $data['TienKhachTra'] ?? 0,
            'TienThua' => $data['TienThua'] ?? 0,
            'TongTienDichVu' => $data['TongTienDichVu'] ?? 0,
            'TongThuoc' => $data['TongThuoc'] ?? 0,
            'PhuongThucThanhToan' => $data['PhuongThucThanhToan'] ?? '',
            'NgayThanhToan' => null,
            'TrangThai' => 0,  // Mặc định chưa thanh toán
            'NgayTao' => 'GETDATE()'
        ]);
    }

    /**
     * Cập nhật hóa đơn
     */
    public static function updateInvoice(int $id, array $data): int
    {
        return self::update($id, $data);
    }

    /**
     * Cập nhật trạng thái thanh toán
     */
    public static function updatePaymentStatus(int $id, array $data): int
    {
        $updates = [];
        if (isset($data['SoTienTra'])) $updates['TienKhachTra'] = $data['SoTienTra'];
        if (isset($data['TienThua'])) $updates['TienThua'] = $data['TienThua'];
        if (isset($data['PhuongThuc'])) $updates['PhuongThucThanhToan'] = $data['PhuongThuc'];
        if (isset($data['NgayThanhToan'])) $updates['NgayThanhToan'] = $data['NgayThanhToan'];
        
        // Đánh dấu là đã thanh toán
        $updates['TrangThai'] = 1;
        
        return self::update($id, $updates);
    }

    /**
     * Tính tổng doanh thu
     */
    public static function getTotalRevenue(?string $fromDate = null, ?string $toDate = null): float
    {
        $sql = "SELECT COALESCE(SUM(TongTien), 0) as total FROM HoaDon WHERE TrangThai = 1";
        
        if ($fromDate && $toDate) {
            $sql .= " AND NgayThanhToan BETWEEN ? AND ?";
            $result = self::queryOne($sql, [$fromDate, $toDate]);
        } else {
            $result = self::queryOne($sql);
        }
        
        return $result['total'] ?? 0;
    }

    /**
     * Tính doanh thu theo ngày
     */
    public static function getDailyRevenue(string $date): float
    {
        $sql = "SELECT COALESCE(SUM(TongTien), 0) as total FROM HoaDon WHERE TrangThai = 1 AND CAST(NgayThanhToan AS DATE) = ?";
        $result = self::queryOne($sql, [$date]);
        return $result['total'] ?? 0;
    }
}
