<?php
/**
 * PhieuKham Model - Phiếu khám
 */

namespace App\Models;

use App\Core\Model;

class PhieuKham extends Model
{
    protected static string $table = 'PhieuKham';
    protected static string $primaryKey = 'MaPhieuKham';

    /**
     * Lấy tất cả phiếu khám
     */
    public static function getAllConsultations(int $limit = 100, int $offset = 0): array
    {
        return self::all($limit, $offset);
    }

    /**
     * Lấy phiếu khám theo ID
     */
    public static function getById(int $id): array|false
    {
        return self::findById($id);
    }

    /**
     * Lấy phiếu khám của bệnh nhân
     */
    public static function getByPatientId(int $patientId): array
    {
        return self::findAllBy('MaBenhNhan', $patientId);
    }

    /**
     * Lấy phiếu khám của bác sĩ
     */
    public static function getByDoctorId(int $doctorId): array
    {
        return self::findAllBy('MaNguoiDung', $doctorId);
    }

    /**
     * Lấy phiếu khám theo lịch hẹn
     */
    public static function getByAppointmentId(int $appointmentId): array|false
    {
        return self::findBy('MaLichHen', $appointmentId);
    }

    /**
     * Tạo phiếu khám mới
     */
    public static function createConsultation(array $data): int|false
    {
        return self::create([
            'MaBenhNhan' => $data['MaBenhNhan'] ?? 0,
            'MaNguoiDung' => $data['MaNguoiDung'] ?? 0,
            'MaLichHen' => $data['MaLichHen'] ?? null,
            'NgayKham' => $data['NgayKham'] ?? 'GETDATE()',
            'TrieuChung' => $data['TrieuChung'] ?? '',
            'ChanDoan' => $data['ChanDoan'] ?? '',
            'NgayTaiKham' => $data['NgayTaiKham'] ?? null,
            'TrangThai' => 0,
            'GhiChu' => $data['GhiChu'] ?? ''
        ]);
    }

    /**
     * Cập nhật phiếu khám
     */
    public static function updateConsultation(int $id, array $data): int
    {
        return self::update($id, $data);
    }

    /**
     * Cập nhật kết quả khám (Windows App gọi)
     */
    public static function updateResults(int $id, array $data): int
    {
        $updates = [];
        if (isset($data['TrieuChung'])) $updates['TrieuChung'] = $data['TrieuChung'];
        if (isset($data['ChanDoan'])) $updates['ChanDoan'] = $data['ChanDoan'];
        if (isset($data['NgayHen_TaiKham'])) $updates['NgayTaiKham'] = $data['NgayHen_TaiKham'];
        
        $updates['TrangThai'] = 1;
        
        return self::update($id, $updates);
    }

    /**
     * Lấy dịch vụ của phiếu khám
     */
    public static function getServices(int $id): array
    {
        $sql = "SELECT ctdv.*, dv.TenDichVu, dv.DonGia 
                FROM ChiTietDichVu ctdv 
                JOIN DichVu dv ON ctdv.MaDichVu = dv.MaDichVu 
                WHERE ctdv.MaPhieuKham = ?";
        return self::query($sql, [$id]);
    }

    /**
     * Lấy thuốc của phiếu khám
     */
    public static function getMedicines(int $id): array
    {
        $sql = "SELECT ctdt.*, t.TenThuoc, t.DonViTinh, t.DonGia,
                       (ctdt.SoLuong * t.DonGia) AS ThanhTien
                FROM ChiTietDonThuoc ctdt 
                JOIN Thuoc t ON ctdt.MaThuoc = t.MaThuoc 
                WHERE ctdt.MaPhieuKham = ?";
        return self::query($sql, [$id]);
    }

    /**
     * Thêm dịch vụ vào phiếu khám
     */
    public static function addService(int $id, array $service): bool
    {
        global $db;
        if (!$db) return false;

        $sql = "INSERT INTO ChiTietDichVu (MaPhieuKham, MaDichVu, SoLuong, ThanhTien) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $id,
            $service['ma_dichvu'] ?? 0,
            $service['soluong'] ?? 1,
            $service['thanhtien'] ?? 0
        ]);
    }
}
