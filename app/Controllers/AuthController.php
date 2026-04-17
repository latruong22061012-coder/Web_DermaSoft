<?php

declare(strict_types=1);

use App\Models\ThongTinPhongKham;

class AuthController extends Controller
{
    private function getPhongKhamData(): array
    {
        $model = new ThongTinPhongKham();
        return $model->getAllInfo() ?? [];
    }

    public function login(): void
    {
        // Nếu đã đăng nhập → chuyển hướng theo vai trò
        \App\Core\Auth::startSession();
        if (\App\Core\Auth::isAuthenticated()) {
            if (\App\Core\Auth::hasRole(1)) {
                header('Location: index.php?route=admin/dashboard');
                exit;
            } elseif (\App\Core\Auth::hasRole(2)) {
                header('Location: index.php?route=bacsi/dashboard');
                exit;
            } elseif (\App\Core\Auth::hasRole(3)) {
                header('Location: index.php?route=letan/dashboard');
                exit;
            } else {
                header('Location: index.php?route=profile');
                exit;
            }
        }
        $this->render('login', ['phongKham' => $this->getPhongKhamData()]);
    }

    public function register(): void
    {
        $this->render('register', ['phongKham' => $this->getPhongKhamData()]);
    }

    public function forgotPassword(): void
    {
        $this->render('forgot-password', ['phongKham' => $this->getPhongKhamData()]);
    }
}
