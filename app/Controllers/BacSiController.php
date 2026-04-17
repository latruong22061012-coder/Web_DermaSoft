<?php

declare(strict_types=1);

use App\Core\Auth;

class BacSiController extends Controller
{
    /**
     * Kiểm tra quyền Bác Sĩ (MaVaiTro = 2)
     */
    private function requireBacSi(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            header('Location: index.php?route=login');
            exit;
        }

        if (!Auth::hasRole(2)) {
            http_response_code(403);
            echo '403 - Bạn không có quyền truy cập trang này.';
            exit;
        }
    }

    public function dashboard(): void
    {
        $this->requireBacSi();
        $user = Auth::getCurrentUser();
        $this->render('bacsi/dashboard', ['currentUser' => $user, 'pageTitle' => 'Tổng quan Bác Sĩ']);
    }

    public function lichLamViec(): void
    {
        $this->requireBacSi();
        $user = Auth::getCurrentUser();
        $this->render('bacsi/lich-lam-viec', ['currentUser' => $user, 'pageTitle' => 'Lịch làm việc']);
    }

    public function benhNhan(): void
    {
        $this->requireBacSi();
        $user = Auth::getCurrentUser();
        $this->render('bacsi/benh-nhan', ['currentUser' => $user, 'pageTitle' => 'Bệnh nhân của tôi']);
    }

    public function luong(): void
    {
        $this->requireBacSi();
        $user = Auth::getCurrentUser();
        $this->render('bacsi/luong', ['currentUser' => $user, 'pageTitle' => 'Bảng lương']);
    }
}
