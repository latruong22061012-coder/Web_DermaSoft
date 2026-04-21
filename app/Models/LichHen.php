<?php
/**
 * LichHen Model - Lịch hẹn
 * TrangThai theo DB constraint (BETWEEN 0 AND 3):
 *   0 = Chờ xác nhận  (website booking mới)
 *   1 = Đã xác nhận   (lễ tân xác nhận)
 *   2 = Hoàn thành
 *   3 = Hủy
 */

namespace App\Models;

use App\Core\Model;

class LichHen extends Model
{
    protected static string $table = 'LichHen';
    protected static string $primaryKey = 'MaLichHen';

    /**
     * Lấy tất cả lịch hẹn
     */
    public static function getAllAppointments(int $limit = 100, int $offset = 0): array
    {
        return self::all($limit, $offset);
    }

    /**
     * Lấy lịch hẹn theo ID
     */
    public static function getById(int $id): array|false
    {
        return self::findById($id);
    }

    /**
     * Lấy lịch hẹn của bệnh nhân
     */
    public static function getByPatientId(int $patientId): array
    {
        return self::findAllBy('MaBenhNhan', $patientId);
    }

    /**
     * Lấy lịch hẹn của bác sĩ
     */
    public static function getByDoctorId(int $doctorId): array
    {
        return self::findAllBy('MaNguoiDung', $doctorId);
    }

    /**
     * Lấy lịch hẹn theo trạng thái
     * 0=pending, 1=confirmed, 2=completed, 3=cancelled
     */
    public static function getByStatus(int $status): array
    {
        return self::findAllBy('TrangThai', $status);
    }

    /**
     * Lấy lịch hẹn chưa được xác nhận (TrangThai = 0)
     */
    public static function getPendingAppointments(): array
    {
        $sql = "SELECT * FROM LichHen WHERE TrangThai = 0 ORDER BY ThoiGianHen ASC";
        return self::query($sql);
    }

    /**
     * Tạo lịch hẹn mới
     */
    public static function createAppointment(array $data): int|false
    {
        return self::create([
            'MaBenhNhan' => $data['MaBenhNhan'] ?? 0,
            'MaNguoiDung' => $data['MaNguoiDung'] ?? null,
            'ThoiGianHen' => $data['ThoiGianHen'] ?? null,
            'TrangThai' => 0,  // Mặc định là pending (Chờ xác nhận)
            'GhiChu' => $data['GhiChu'] ?? ''
        ]);
    }

    /**
     * Cập nhật trạng thái lịch hẹn
     */
    public static function updateStatus(int $id, int $status): int
    {
        return self::update($id, ['TrangThai' => $status]);
    }

    /**
     * Cập nhật lịch hẹn
     */
    public static function updateAppointment(int $id, array $data): int
    {
        return self::update($id, $data);
    }

    /**
     * Hủy lịch hẹn (TrangThai = 3 theo DB constraint CHK_TrangThaiLich)
     */
    public static function cancel(int $id): int
    {
        return self::updateStatus($id, 3);
    }

    /**
     * Xác nhận lịch hẹn (TrangThai = 1) - Windows App gọi
     */
    public static function confirm(int $id): int
    {
        return self::updateStatus($id, 1);
    }
}
