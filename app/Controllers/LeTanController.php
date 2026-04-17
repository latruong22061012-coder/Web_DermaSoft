<?php

declare(strict_types=1);

use App\Core\Auth;

class LeTanController extends Controller
{
    /**
     * Kiểm tra quyền Lễ Tân (MaVaiTro = 3)
     */
    private function requireLeTan(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            header('Location: index.php?route=login');
            exit;
        }

        if (!Auth::hasRole(3)) {
            http_response_code(403);
            echo '403 - Bạn không có quyền truy cập trang này.';
            exit;
        }
    }

    public function dashboard(): void
    {
        $this->requireLeTan();
        $user = Auth::getCurrentUser();
        $this->render('letan/dashboard', ['currentUser' => $user, 'pageTitle' => 'Tổng quan Lễ Tân']);
    }

    public function lichHen(): void
    {
        $this->requireLeTan();
        $user = Auth::getCurrentUser();
        $this->render('letan/lich-hen', ['currentUser' => $user, 'pageTitle' => 'Quản lý Lịch hẹn']);
    }

    public function lichLamViec(): void
    {
        $this->requireLeTan();
        $user = Auth::getCurrentUser();
        $this->render('letan/lich-lam-viec', ['currentUser' => $user, 'pageTitle' => 'Lịch làm việc']);
    }

    public function luong(): void
    {
        $this->requireLeTan();
        $user = Auth::getCurrentUser();
        $this->render('letan/luong', ['currentUser' => $user, 'pageTitle' => 'Bảng lương']);
    }
}
