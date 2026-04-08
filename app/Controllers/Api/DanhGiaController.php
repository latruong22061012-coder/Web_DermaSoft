<?php
/**
 * DanhGiaController - API Endpoints cho Đánh giá dịch vụ
 * POST /api/danhgia         - Tạo đánh giá mới
 * GET  /api/danhgia/check/{maPhieuKham} - Kiểm tra đã đánh giá chưa
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;

class DanhGiaController extends ApiController
{
    /**
     * POST /api/danhgia
     * Tạo đánh giá mới cho phiếu khám
     */
    public function create(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $data = $this->getJSON();
        $maPhieuKham = (int)($data['maPhieuKham'] ?? 0);
        $diemDanh    = (int)($data['diemDanh'] ?? 0);
        $nhanXet     = isset($data['nhanXet']) ? trim($data['nhanXet']) : null;

        if ($maPhieuKham < 1) {
            $this->error('Mã phiếu khám không hợp lệ');
            return;
        }
        if ($diemDanh < 1 || $diemDanh > 5) {
            $this->error('Điểm đánh giá phải từ 1 đến 5');
            return;
        }

        $phone = $user['SoDienThoai'] ?? '';
        $benhNhan = Database::fetchOne(
            "SELECT MaBenhNhan FROM BenhNhan WHERE SoDienThoai = ? AND IsDeleted = 0",
            [$phone]
        );
        if (!$benhNhan) {
            $this->error('Không tìm thấy hồ sơ bệnh nhân');
            return;
        }
        $maBenhNhan = (int)$benhNhan['MaBenhNhan'];

        // Kiểm tra phiếu khám tồn tại và thuộc về bệnh nhân
        $pk = Database::fetchOne(
            "SELECT MaPhieuKham, TrangThai FROM PhieuKham WHERE MaPhieuKham = ? AND MaBenhNhan = ? AND IsDeleted = 0",
            [$maPhieuKham, $maBenhNhan]
        );
        if (!$pk) {
            $this->error('Phiếu khám không tồn tại hoặc không thuộc về bạn');
            return;
        }
        if ((int)$pk['TrangThai'] !== 1) {
            $this->error('Chỉ có thể đánh giá phiếu khám đã hoàn thành');
            return;
        }

        // Kiểm tra đã đánh giá chưa
        $existing = Database::fetchOne(
            "SELECT MaDanhGia FROM DanhGia WHERE MaPhieuKham = ? AND MaBenhNhan = ?",
            [$maPhieuKham, $maBenhNhan]
        );
        if ($existing) {
            $this->error('Bạn đã đánh giá phiếu khám này rồi');
            return;
        }

        // Tạo đánh giá
        Database::query(
            "INSERT INTO DanhGia (MaPhieuKham, MaBenhNhan, DiemDanh, NhanXet, NgayDanhGia) VALUES (?, ?, ?, ?, GETDATE())",
            [$maPhieuKham, $maBenhNhan, $diemDanh, $nhanXet]
        );

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

        $this->success(null, 'Đánh giá đã được gửi thành công', 201);
    }

    /**
     * GET /api/danhgia/check/{maPhieuKham}
     * Kiểm tra bệnh nhân đã đánh giá phiếu khám chưa
     */
    public function check(): void
    {
        Auth::startSession();
        $user = $this->requireAuth();
        if (!$user) return;

        $maPhieuKham = (int)$this->getParam('id');
        if ($maPhieuKham < 1) {
            $this->error('Mã phiếu khám không hợp lệ');
            return;
        }

        $phone = $user['SoDienThoai'] ?? '';
        $benhNhan = Database::fetchOne(
            "SELECT MaBenhNhan FROM BenhNhan WHERE SoDienThoai = ? AND IsDeleted = 0",
            [$phone]
        );
        if (!$benhNhan) {
            $this->success(['hasRated' => false, 'review' => null]);
            return;
        }
        $maBenhNhan = (int)$benhNhan['MaBenhNhan'];

        $review = Database::fetchOne(
            "SELECT MaDanhGia, DiemDanh, NhanXet, NgayDanhGia FROM DanhGia WHERE MaPhieuKham = ? AND MaBenhNhan = ?",
            [$maPhieuKham, $maBenhNhan]
        );

        $this->success([
            'hasRated' => $review ? true : false,
            'review'   => $review ?: null,
        ]);
    }
}
