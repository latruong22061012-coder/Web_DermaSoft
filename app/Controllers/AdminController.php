<?php

declare(strict_types=1);

use App\Core\Auth;

class AdminController extends Controller
{
    /**
     * Kiểm tra quyền Admin (MaVaiTro = 1)
     */
    private function requireAdmin(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            header('Location: index.php?route=login');
            exit;
        }

        if (!Auth::hasRole(1)) {
            http_response_code(403);
            echo '403 - Bạn không có quyền truy cập trang quản trị.';
            exit;
        }
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        $user = Auth::getCurrentUser();
        $this->render('admin/dashboard', ['currentUser' => $user, 'pageTitle' => 'Tổng quan']);
    }

    public function benhNhan(): void
    {
        $this->requireAdmin();
        $user = Auth::getCurrentUser();
        $this->render('admin/benh-nhan', ['currentUser' => $user, 'pageTitle' => 'Quản lý Bệnh nhân']);
    }

    public function thanhVien(): void
    {
        $this->requireAdmin();
        $user = Auth::getCurrentUser();
        $this->render('admin/thanh-vien', ['currentUser' => $user, 'pageTitle' => 'Quản lý Thành viên']);
    }

    public function hangThanhVien(): void
    {
        $this->requireAdmin();
        $user = Auth::getCurrentUser();
        $this->render('admin/hang-thanh-vien', ['currentUser' => $user, 'pageTitle' => 'Hạng thành viên']);
    }

    public function danhGia(): void
    {
        $this->requireAdmin();
        $user = Auth::getCurrentUser();
        $this->render('admin/danh-gia', ['currentUser' => $user, 'pageTitle' => 'Quản lý Đánh giá']);
    }
}
