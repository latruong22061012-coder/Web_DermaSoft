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
