<?php
declare(strict_types=1);

namespace App\Models;

class DanhGia
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
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
            $this->db->query($query, [
                ':MaPhieuKham' => $maPhieuKham,
                ':MaBenhNhan' => $maBenhNhan,
                ':DiemDanh' => $diemDanh,
                ':NhanXet' => $nhanXet
            ]);

            // Cập nhật tỷ lệ hài lòng trong ThanhVienInfo
            $thanhVienModel = new ThanhVienInfo();
            $avgScore = $thanhVienModel->calculateAverageSatisfaction($maBenhNhan);
            $thanhVienModel->updateSatisfactionRate($maBenhNhan, $avgScore);

            return true;
        } catch (Exception $e) {
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

        $result = $this->db->query($query, [':MaPhieuKham' => $maPhieuKham])->fetch();
        return $result ?: null;
    }

    /**
     * Lấy tất cả đánh giá của một khách hàng
     */
    public function getByBenhNhan($maBenhNhan, $page = 1, $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT dg.*, pk.NgayKham, pk.ChanDoan, nv.HoTen as BacSiTen
                  FROM DanhGia dg
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NhanVien nv ON pk.MaBacSi = nv.MaNhanVien
                  WHERE dg.MaBenhNhan = :MaBenhNhan
                  ORDER BY dg.NgayDanhGia DESC
                  OFFSET :Offset ROWS FETCH NEXT :Limit ROWS ONLY";

        return $this->db->query($query, [
            ':MaBenhNhan' => $maBenhNhan,
            ':Offset' => $offset,
            ':Limit' => $limit
        ])->fetchAll();
    }

    /**
     * Lấy đánh giá theo ID
     */
    public function getById($maDanhGia): ?array
    {
        $query = "SELECT dg.*, bn.HoTen, bn.SoDienThoai, pk.NgayKham, nv.HoTen as BacSiTen
                  FROM DanhGia dg
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NhanVien nv ON pk.MaBacSi = nv.MaNhanVien
                  WHERE dg.MaDanhGia = :MaDanhGia";

        $result = $this->db->query($query, [':MaDanhGia' => $maDanhGia])->fetch();
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
            $this->db->query($query, $params);
            return true;
        } catch (Exception $e) {
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
            $this->db->query($query, [':MaDanhGia' => $maDanhGia]);
            return true;
        } catch (Exception $e) {
            error_log("Error deleting rating: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy điểm đánh giá trung bình của một bác sĩ
     */
    public function getAverageByBacSi($maBacSi): float
    {
        $query = "SELECT AVG(CAST(DiemDanh AS FLOAT)) as AvgScore
                  FROM DanhGia dg
                  JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  WHERE pk.MaBacSi = :MaBacSi";

        $result = $this->db->query($query, [':MaBacSi' => $maBacSi])->fetch();
        return $result ? (float)$result['AvgScore'] : 0.0;
    }

    /**
     * Lấy đánh giá của bác sĩ (với phân trang)
     */
    public function getByBacSi($maBacSi, $page = 1, $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT dg.*, bn.HoTen, pk.NgayKham, pk.ChanDoan
                  FROM DanhGia dg
                  JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  WHERE pk.MaBacSi = :MaBacSi
                  ORDER BY dg.NgayDanhGia DESC
                  OFFSET :Offset ROWS FETCH NEXT :Limit ROWS ONLY";

        return $this->db->query($query, [
            ':MaBacSi' => $maBacSi,
            ':Offset' => $offset,
            ':Limit' => $limit
        ])->fetchAll();
    }

    /**
     * Kiểm tra khách hàng đã đánh giá phiếu khám này chưa
     */
    public function hasRated($maPhieuKham, $maBenhNhan): bool
    {
        $query = "SELECT COUNT(*) as Count FROM DanhGia 
                  WHERE MaPhieuKham = :MaPhieuKham 
                  AND MaBenhNhan = :MaBenhNhan";

        $result = $this->db->query($query, [
            ':MaPhieuKham' => $maPhieuKham,
            ':MaBenhNhan' => $maBenhNhan
        ])->fetch();

        return ((int)($result['Count'] ?? 0)) > 0;
    }

    /**
     * Lấy tất cả đánh giá (dùng cho admin/dashboard)
     */
    public function getAll($page = 1, $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT dg.*, bn.HoTen as KhachHang, nv.HoTen as BacSi, pk.ChanDoan
                  FROM DanhGia dg
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NhanVien nv ON pk.MaBacSi = nv.MaNhanVien
                  ORDER BY dg.NgayDanhGia DESC
                  OFFSET :Offset ROWS FETCH NEXT :Limit ROWS ONLY";

        return $this->db->query($query, [
            ':Offset' => $offset,
            ':Limit' => $limit
        ])->fetchAll();
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

        return $this->db->query($query)->fetch() ?: [];
    }

    /**
     * Lấy top đánh giá gần nhất
     */
    public function getLatestRatings($limit = 5): array
    {
        $query = "SELECT TOP :Limit dg.*, bn.HoTen, nv.HoTen as BacSiTen
                  FROM DanhGia dg
                  LEFT JOIN BenhNhan bn ON dg.MaBenhNhan = bn.MaBenhNhan
                  LEFT JOIN PhieuKham pk ON dg.MaPhieuKham = pk.MaPhieuKham
                  LEFT JOIN NhanVien nv ON pk.MaBacSi = nv.MaNhanVien
                  ORDER BY dg.NgayDanhGia DESC";

        return $this->db->query($query, [':Limit' => $limit])->fetchAll();
    }
}
