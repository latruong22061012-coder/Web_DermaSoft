<?php
$tenPK = htmlspecialchars($phongKham['tenPhongKham'] ?? 'DarmaSoft Clinic', ENT_QUOTES, 'UTF-8');
$currentUsername = htmlspecialchars($currentUser['TenDangNhap'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Controllers\ApiController::generateCsrfToken()) ?>">
    <title>Đổi Thông Tin Đăng Nhập | <?= $tenPK ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <link href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/css/style.css">
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/css/auth.css">
</head>
<body class="auth-page">
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm" id="mainNav">
        <div class="container-fluid px-4 px-lg-5">
            <a class="navbar-brand logo-wrapper" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>index.php?route=home">
                <img src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/images/logo.png" alt="<?= $tenPK ?>" class="img-fluid logo-img">
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>index.php?route=logout" class="btn btn-outline-danger rounded-pill px-4">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <main class="auth-main">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="card auth-card">
                        <div class="row g-0">
                            <div class="col-lg-7">
                                <div class="card-body p-md-5">
                                    <div class="auth-eyebrow">First Login Security</div>
                                    <h1 class="auth-title h2">Cập nhật thông tin đăng nhập</h1>
                                    <div class="auth-separator"></div>
                                    <p class="auth-hint">Tài khoản mới cần đổi tên đăng nhập và mật khẩu trước khi tiếp tục sử dụng hệ thống.</p>

                                    <div id="changeFeedback" class="alert d-none mb-4" role="alert"></div>

                                    <form id="changeCredentialsForm" novalidate>
                                        <div class="mb-3">
                                            <label class="form-label fw-medium text-muted" for="currentUsername">Tên đăng nhập hiện tại</label>
                                            <input type="text" class="form-control" id="currentUsername" value="<?= $currentUsername ?>" readonly>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-medium text-muted" for="currentPassword">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="currentPassword" minlength="6" required>
                                            <div class="invalid-feedback">Vui lòng nhập mật khẩu hiện tại.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-medium text-muted" for="newUsername">Tên đăng nhập mới <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="newUsername" minlength="3" maxlength="30" required>
                                            <div class="form-text">Chỉ gồm chữ, số và các ký tự . _ -</div>
                                            <div class="invalid-feedback">Tên đăng nhập mới không hợp lệ.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-medium text-muted" for="newPassword">Mật khẩu mới <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="newPassword" minlength="6" required>
                                            <div class="invalid-feedback">Mật khẩu mới tối thiểu 6 ký tự.</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-medium text-muted" for="confirmPassword">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirmPassword" minlength="6" required>
                                            <div class="invalid-feedback">Xác nhận mật khẩu không khớp.</div>
                                        </div>

                                        <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="changeSubmitBtn">
                                            <i class="bi bi-shield-lock me-2"></i>Xác nhận cập nhật
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="col-lg-5 auth-brand-box">
                                <div class="h-100 d-flex flex-column justify-content-center p-4 p-md-5 auth-brand-content">
                                    <img src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/images/logo.png" alt="<?= $tenPK ?>" class="mb-4 auth-brand-logo">
                                    <h2 class="font-heading h4 mb-3">Bảo mật tài khoản nhân sự</h2>
                                    <ul class="list-unstyled auth-brand-list mb-0">
                                        <li><i class="bi bi-person-badge text-primary me-2"></i>Mỗi nhân sự dùng tên đăng nhập riêng</li>
                                        <li><i class="bi bi-key text-primary me-2"></i>Mật khẩu mới được mã hóa an toàn</li>
                                        <li><i class="bi bi-shield-check text-primary me-2"></i>Hoàn tất cập nhật để truy cập dashboard</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/js/csrf.js"></script>
    <script>
    (function () {
        var path = window.location.pathname;
        if (path.startsWith('/DarmaSoft')) {
            window._API_BASE_PATH = '/DarmaSoft';
        } else {
            window._API_BASE_PATH = '';
        }
    })();

    (function () {
        'use strict';

        var form = document.getElementById('changeCredentialsForm');
        var feedback = document.getElementById('changeFeedback');
        var btn = document.getElementById('changeSubmitBtn');

        var currentPasswordEl = document.getElementById('currentPassword');
        var newUsernameEl = document.getElementById('newUsername');
        var newPasswordEl = document.getElementById('newPassword');
        var confirmPasswordEl = document.getElementById('confirmPassword');

        function showFeedback(type, message) {
            feedback.className = 'alert alert-' + type + ' mb-4';
            feedback.textContent = message;
            feedback.classList.remove('d-none');
        }

        function validateUsername(value) {
            return /^[A-Za-z0-9._-]{3,30}$/.test(value || '');
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            var currentPassword = currentPasswordEl.value.trim();
            var newUsername = newUsernameEl.value.trim();
            var newPassword = newPasswordEl.value.trim();
            var confirmPassword = confirmPasswordEl.value.trim();

            var valid = true;

            if (!currentPassword || currentPassword.length < 6) {
                currentPasswordEl.classList.add('is-invalid');
                valid = false;
            } else {
                currentPasswordEl.classList.remove('is-invalid');
            }

            if (!validateUsername(newUsername)) {
                newUsernameEl.classList.add('is-invalid');
                valid = false;
            } else {
                newUsernameEl.classList.remove('is-invalid');
            }

            if (!newPassword || newPassword.length < 6) {
                newPasswordEl.classList.add('is-invalid');
                valid = false;
            } else {
                newPasswordEl.classList.remove('is-invalid');
            }

            if (!confirmPassword || confirmPassword !== newPassword) {
                confirmPasswordEl.classList.add('is-invalid');
                valid = false;
            } else {
                confirmPasswordEl.classList.remove('is-invalid');
            }

            if (!valid) {
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang cập nhật...';

            try {
                var apiBase = (window._API_BASE_PATH || '').replace(/\/$/, '');
                var response = await fetch(apiBase + '/api/auth/first-login-update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_username: newUsername,
                        new_password: newPassword
                    })
                });

                var result = await response.json();
                if (!response.ok) {
                    showFeedback('danger', result.message || 'Không thể cập nhật thông tin đăng nhập.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-shield-lock me-2"></i>Xác nhận cập nhật';
                    return;
                }

                showFeedback('success', result.message || 'Đổi thông tin đăng nhập thành công. Đang chuyển trang...');
                var redirectRoute = (result.data && result.data.redirect_route) ? result.data.redirect_route : 'profile';
                setTimeout(function () {
                    window.location.href = 'index.php?route=' + redirectRoute;
                }, 1200);

            } catch (error) {
                showFeedback('danger', 'Lỗi kết nối. Vui lòng thử lại.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-shield-lock me-2"></i>Xác nhận cập nhật';
            }
        });
    })();
    </script>
</body>
</html>
