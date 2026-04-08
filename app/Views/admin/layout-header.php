<?php
/**
 * Admin Layout Template
 * Được include bởi tất cả các trang admin
 * Biến cần truyền: $currentUser, $pageTitle, $activePage
 */
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
$userName = htmlspecialchars($currentUser['HoTen'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$userInitial = function_exists('mb_substr') ? mb_substr($userName, 0, 1, 'UTF-8') : substr($userName, 0, 1);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Quản trị', ENT_QUOTES, 'UTF-8') ?> | DarmaSoft Admin</title>

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

    <!-- Overlay mobile -->
    <div class="admin-overlay" id="sidebarOverlay"></div>

    <!-- ═══ SIDEBAR ═══ -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <img src="<?= $baseUrl ?>public/assets/images/logo.png" alt="DarmaSoft">
            <span>DarmaSoft</span>
        </div>

        <nav class="admin-sidebar-nav">
            <div class="admin-nav-section">
                <div class="admin-nav-section-title">Tổng quan</div>
                <a href="<?= $baseUrl ?>index.php?route=admin/dashboard"
                   class="admin-nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="admin-nav-section">
                <div class="admin-nav-section-title">Quản lý</div>
                <a href="<?= $baseUrl ?>index.php?route=admin/benh-nhan"
                   class="admin-nav-item <?= ($activePage ?? '') === 'benh-nhan' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Bệnh nhân</span>
                </a>
                <a href="<?= $baseUrl ?>index.php?route=admin/thanh-vien"
                   class="admin-nav-item <?= ($activePage ?? '') === 'thanh-vien' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Thành viên</span>
                </a>
                <a href="<?= $baseUrl ?>index.php?route=admin/hang-thanh-vien"
                   class="admin-nav-item <?= ($activePage ?? '') === 'hang-thanh-vien' ? 'active' : '' ?>">
                    <i class="bi bi-gem"></i>
                    <span>Hạng thành viên</span>
                </a>
                <a href="<?= $baseUrl ?>index.php?route=admin/danh-gia"
                   class="admin-nav-item <?= ($activePage ?? '') === 'danh-gia' ? 'active' : '' ?>">
                    <i class="bi bi-star"></i>
                    <span>Đánh giá</span>
                </a>
            </div>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-user-block">
                <div class="admin-user-avatar"><?= $userInitial ?></div>
                <div class="admin-user-info">
                    <div class="admin-user-name"><?= $userName ?></div>
                    <div class="admin-user-role">Quản trị viên</div>
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
                <a href="<?= $baseUrl ?>index.php?route=home" class="btn btn-outline-secondary btn-sm rounded-pill px-3" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Xem Website
                </a>
                <a href="<?= $baseUrl ?>index.php?route=logout" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-left me-1"></i>Đăng xuất
                </a>
            </div>
        </header>

        <div class="admin-content">
            <!-- Toast container -->
            <div class="admin-toast-container" id="toastContainer"></div>
