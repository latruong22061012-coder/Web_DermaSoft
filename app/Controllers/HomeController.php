<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Models\ThongTinPhongKham;

class HomeController extends Controller
{
    public function index(): void
    {
        Auth::startSession();
        $isLoggedIn = Auth::isAuthenticated();
        $currentUser = $isLoggedIn ? Auth::getCurrentUser() : null;
        $isAdmin = $currentUser && (int)($currentUser['MaVaiTro'] ?? 0) === 1;

        $phongKhamModel = new ThongTinPhongKham();
        $phongKham = $phongKhamModel->getAllInfo() ?? [];

        $this->render('home', [
            'isLoggedIn' => $isLoggedIn,
            'currentUser' => $currentUser,
            'phongKham' => $phongKham,
            'isAdmin' => $isAdmin,
        ]);
    }
}
