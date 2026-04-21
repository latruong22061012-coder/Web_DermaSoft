<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class DanhGia
{
    public function __construct()
    {
        // Sử dụng Database static methods từ App\Core\Database
    }

    /**
     * Tạo đánh giá mới
     */
    public function create($maPhieuKham, $maBenhNhan, $diemDanh, $nhanXet = null): bool
    {
        if ($diemDanh < 1 || $diemDanh > 5) {
            error_log("Invalid rating score: $diemDanh");
            return false;
        }

        $query = "INSERT INTO DanhGia (MaPhieuKham, MaBenhNhan, DiemDanh, NhanXet, NgayDanhGia) 
                  VALUES (:MaPhieuKham, :MaBenhNhan, :DiemDanh, :NhanXet, GETDATE())";

        try {
            Database::query($query, [
                ':MaPhieuKham' => $maPhieuKham,
                ':MaBenhNhan' => $maBenhNhan,
                ':DiemDanh' => $diemDanh,
                ':NhanXet' => $nhanXet
            ]);

            // Cập nhật tỷ lệ hài lòng trong ThanhVienInfo
            $avg = Database::fetchOne(
                "SELECT AVG(CAST(DiemDanh AS FLOAT)) AS AvgScore FROM DanhGia WHERE MaBenhNhan = ?",
                [$maBenhNhan]
            );
            if ($avg && $avg['AvgScore'] !== null) {
                $tyLe = round((float)$avg['AvgScore'] / 5 * 100, 1);
                Database::query(
                    "UPDATE ThanhVienInfo SET TyLeHaiLong = ? WHERE MaBenhNhan = ?",
                    [$tyLe, $maBenhNhan]
                );
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error creating rating: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy tất cả đánh giá của một phiếu khám
     */
    public function getByPhieuKham($maPhieuKham): ?array
    {
        $query = "SELECT dg.*, bn.HoTen, bn.SoDienThoai
                  FROM DanhGia dg
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  WHERE dg.MaPhieuKham = :MaPhieuKham
                  ORDER BY dg.NgayDanhGia DESC";

        $result = Database::fetchOne($query, [':MaPhieuKham' => $maPhieuKham]);
        return $result ?: null;
    }

    /**
     * Lấy tất cả đánh giá của một khách hàng
     */
    public function getByBenhNhan($maBenhNhan, $page = 1, $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT dg.*, pk.NgayKham, pk.ChanDoan, nd.HoTen as BacSiTen
                  FROM DanhGia dg
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NguoiDung nd ON pk.MaNguoiDung = nd.MaNguoiDung
                  WHERE dg.MaBenhNhan = :MaBenhNhan
                  ORDER BY dg.NgayDanhGia DESC
                  OFFSET :Offset ROWS FETCH NEXT :Limit ROWS ONLY";

        return Database::fetchAll($query, [
            ':MaBenhNhan' => $maBenhNhan,
            ':Offset' => $offset,
            ':Limit' => $limit
        ]);
    }

    /**
     * Lấy đánh giá theo ID
     */
    public function getById($maDanhGia): ?array
    {
        $query = "SELECT dg.*, bn.HoTen, bn.SoDienThoai, pk.NgayKham, nd.HoTen as BacSiTen
                  FROM DanhGia dg
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NguoiDung nd ON pk.MaNguoiDung = nd.MaNguoiDung
                  WHERE dg.MaDanhGia = :MaDanhGia";

        $result = Database::fetchOne($query, [':MaDanhGia' => $maDanhGia]);
        return $result ?: null;
    }

    /**
     * Cập nhật đánh giá
     */
    public function update($maDanhGia, $diemDanh = null, $nhanXet = null): bool
    {
        $updates = [];
        $params = [':MaDanhGia' => $maDanhGia];

        if ($diemDanh !== null) {
            if ($diemDanh < 1 || $diemDanh > 5) {
                return false;
            }
            $updates[] = "DiemDanh = :DiemDanh";
            $params[':DiemDanh'] = $diemDanh;
        }

        if ($nhanXet !== null) {
            $updates[] = "NhanXet = :NhanXet";
            $params[':NhanXet'] = $nhanXet;
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE DanhGia SET " . implode(", ", $updates) . " WHERE MaDanhGia = :MaDanhGia";

        try {
            Database::query($query, $params);
            return true;
        } catch (\Exception $e) {
            error_log("Error updating rating: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Xóa đánh giá
     */
    public function delete($maDanhGia): bool
    {
        try {
            $query = "DELETE FROM DanhGia WHERE MaDanhGia = :MaDanhGia";
            Database::query($query, [':MaDanhGia' => $maDanhGia]);
            return true;
        } catch (\Exception $e) {
            error_log("Error deleting rating: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy điểm đánh giá trung bình của một bác sĩ
     */
    public function getAverageByBacSi($maNguoiDung): float
    {
        $query = "SELECT AVG(CAST(DiemDanh AS FLOAT)) as AvgScore
                  FROM DanhGia dg
                  JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  WHERE pk.MaNguoiDung = :MaNguoiDung";

        $result = Database::fetchOne($query, [':MaNguoiDung' => $maNguoiDung]);
        return $result ? (float)$result['AvgScore'] : 0.0;
    }

    /**
     * Lấy đánh giá của bác sĩ (với phân trang)
     */
    public function getByBacSi($maNguoiDung, $page = 1, $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT dg.*, bn.HoTen, pk.NgayKham, pk.ChanDoan
                  FROM DanhGia dg
                  JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  WHERE pk.MaNguoiDung = :MaNguoiDung
                  ORDER BY dg.NgayDanhGia DESC
                  OFFSET :Offset ROWS FETCH NEXT :Limit ROWS ONLY";

        return Database::fetchAll($query, [
            ':MaNguoiDung' => $maNguoiDung,
            ':Offset' => $offset,
            ':Limit' => $limit
        ]);
    }

    /**
     * Kiểm tra khách hàng đã đánh giá phiếu khám này chưa
     */
    public function hasRated($maPhieuKham, $maBenhNhan): bool
    {
        $query = "SELECT COUNT(*) as Count FROM DanhGia 
                  WHERE MaPhieuKham = :MaPhieuKham 
                  AND MaBenhNhan = :MaBenhNhan";

        $result = Database::fetchOne($query, [
            ':MaPhieuKham' => $maPhieuKham,
            ':MaBenhNhan' => $maBenhNhan
        ]);

        return ((int)($result['Count'] ?? 0)) > 0;
    }

    /**
     * Lấy tất cả đánh giá (dùng cho admin/dashboard)
     */
    public function getAll($page = 1, $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT dg.*, bn.HoTen as KhachHang, nd.HoTen as BacSi, pk.ChanDoan
                  FROM DanhGia dg
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NguoiDung nd ON pk.MaNguoiDung = nd.MaNguoiDung
                  ORDER BY dg.NgayDanhGia DESC
                  OFFSET :Offset ROWS FETCH NEXT :Limit ROWS ONLY";

        return Database::fetchAll($query, [
            ':Offset' => $offset,
            ':Limit' => $limit
        ]);
    }

    /**
     * Lấy thống kê đánh giá
     */
    public function getStatistics(): array
    {
        $query = "SELECT 
                    COUNT(*) as TotalRatings,
                    AVG(CAST(DiemDanh AS FLOAT)) as AvgScore,
                    SUM(CASE WHEN DiemDanh = 5 THEN 1 ELSE 0 END) as FiveStars,
                    SUM(CASE WHEN DiemDanh = 4 THEN 1 ELSE 0 END) as FourStars,
                    SUM(CASE WHEN DiemDanh = 3 THEN 1 ELSE 0 END) as ThreeStars,
                    SUM(CASE WHEN DiemDanh = 2 THEN 1 ELSE 0 END) as TwoStars,
                    SUM(CASE WHEN DiemDanh = 1 THEN 1 ELSE 0 END) as OneStar
                  FROM DanhGia";

        return Database::fetchOne($query) ?: [];
    }

    /**
     * Lấy top đánh giá gần nhất
     */
    public function getLatestRatings($limit = 5): array
    {
        $query = "SELECT TOP :Limit dg.*, bn.HoTen, nd.HoTen as BacSiTen
                  FROM DanhGia dg
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NguoiDung nd ON pk.MaNguoiDung = nd.MaNguoiDung
                  ORDER BY dg.NgayDanhGia DESC";

        return Database::fetchAll($query, [':Limit' => $limit]);
    }
}
