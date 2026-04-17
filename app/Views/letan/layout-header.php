<?php
/**
 * LeTan Layout Header
 * Biến cần truyền: $currentUser, $pageTitle, $activePage
 */
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
$userName = htmlspecialchars($currentUser['HoTen'] ?? 'Lễ Tân', ENT_QUOTES, 'UTF-8');
$userInitial = function_exists('mb_substr') ? mb_substr($userName, 0, 1, 'UTF-8') : substr($userName, 0, 1);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Controllers\ApiController::generateCsrfToken()) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Lễ Tân', ENT_QUOTES, 'UTF-8') ?> | DarmaSoft</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <link href="<?= $baseUrl ?>public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>public/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/style.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/admin.css">
</head>
<body>
<div class="admin-wrapper">

    <div class="admin-overlay" id="sidebarOverlay"></div>

    <!-- ═══ SIDEBAR ═══ -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <img src="<?= $baseUrl ?>public/assets/images/logo.png" alt="DarmaSoft">
            <span>DermaSoft</span>
        </div>

        <nav class="admin-sidebar-nav">
            <div class="admin-nav-section">
                <div class="admin-nav-section-title">Tổng quan</div>
                <a href="<?= $baseUrl ?>index.php?route=letan/dashboard"
                   class="admin-nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="admin-nav-section">
                <div class="admin-nav-section-title">Công việc</div>
                <a href="<?= $baseUrl ?>index.php?route=letan/lich-hen"
                   class="admin-nav-item <?= ($activePage ?? '') === 'lich-hen' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-event"></i>
                    <span>Lịch hẹn</span>
                </a>
                <a href="<?= $baseUrl ?>index.php?route=letan/lich-lam-viec"
                   class="admin-nav-item <?= ($activePage ?? '') === 'lich-lam-viec' ? 'active' : '' ?>">
                    <i class="bi bi-calendar3"></i>
                    <span>Lịch làm việc</span>
                </a>
            </div>

            <div class="admin-nav-section">
                <div class="admin-nav-section-title">Tài chính</div>
                <a href="<?= $baseUrl ?>index.php?route=letan/luong"
                   class="admin-nav-item <?= ($activePage ?? '') === 'luong' ? 'active' : '' ?>">
                    <i class="bi bi-wallet2"></i>
                    <span>Bảng lương</span>
                </a>
            </div>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-user-block">
                <div class="admin-user-avatar"><?= $userInitial ?></div>
                <div class="admin-user-info">
                    <div class="admin-user-name"><?= $userName ?></div>
                    <div class="admin-user-role">Lễ Tân</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- ═══ MAIN ═══ -->
    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-header-left">
                <button class="admin-toggle-btn" id="sidebarToggle" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="admin-header-title"><?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="admin-header-right">
                <a href="<?= $baseUrl ?>index.php?route=profile" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-person me-1"></i>Hồ sơ
                </a>
                <a href="<?= $baseUrl ?>index.php?route=logout" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                </a>
            </div>
        </header>

        <div class="admin-content">
            <div class="admin-toast-container" id="toastContainer"></div>
