<?php
$tenPK = htmlspecialchars($phongKham['tenPhongKham'] ?? 'DarmaSoft Clinic', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Controllers\ApiController::generateCsrfToken()) ?>">
    <title>Đăng nhập | <?= $tenPK ?></title>

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
                <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>index.php?route=home" class="btn btn-outline-primary rounded-pill px-4">Trang chủ</a>
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
                                    <div class="auth-eyebrow">Member Access</div>
                                    <h1 class="auth-title h2">Đăng nhập tài khoản</h1>
                                    <div class="auth-separator"></div>
                                    <p class="auth-hint">Chào mừng bạn quay lại <?= $tenPK ?>. Vui lòng xác thực số điện thoại để tiếp tục.</p>

                                    <div class="auth-steps mb-4">
                                        <div class="auth-step active" id="stepDot1">
                                            <span class="auth-step-num">1</span>
                                            <span class="auth-step-label">Số Điện Thoại</span>
                                        </div>
                                        <div class="auth-step-line"></div>
                                        <div class="auth-step" id="stepDot2">
                                            <span class="auth-step-num">2</span>
                                            <span class="auth-step-label">Xác thực OTP</span>
                                        </div>
                                    </div>

                                    <!-- ═══ LOGIN FORM ═══ -->
                                    <div id="stepPhone">
                                        <form id="formStep1" novalidate>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="loginPhone">Số Điện Thoại</label>
                                                <div class="input-group">
                                                    <span class="input-group-text auth-input-prefix">
                                                        <i class="bi bi-flag-fill me-1 text-danger" style="font-size:.75rem"></i>+84
                                                    </span>
                                                    <input type="tel" class="form-control" id="loginPhone" placeholder="09xxxxxxxxx" required inputmode="numeric">
                                                </div>
                                                <div class="invalid-feedback d-block d-none" id="phoneFeedback">Số điện thoại không hợp lệ.</div>
                                            </div>
                                            <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnSendOtp">
                                                <i class="bi bi-send me-2"></i>Đăng nhập
                                            </button>
                                        </form>
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <span class="text-muted small">Bạn không nhận được mã?</span>
                                            <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>index.php?route=forgot-password" class="auth-alt-link">Khôi phục tài khoản</a>
                                        </div>
                                    </div>

                                    <!-- ═══ REGISTER FORM (ẩn sẽ hiện khi số điện thoại chưa đăng ký) ═══ -->
                                    <div id="stepRegister" class="d-none">
                                        <h2 class="h4 mb-3">Đăng ký tài khoản</h2>
                                        <p class="text-muted mb-4">Số điện thoại này chưa được đăng ký. Vui lòng hoàn tất đăng ký bên dưới.</p>
                                        <form id="formRegister" novalidate>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regName">Họ và tên <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="regName" placeholder="Nguyễn Văn A" required>
                                                <div class="invalid-feedback d-block d-none" id="nameFeedback">Vui lòng nhập họ và tên.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regPhone">Số Điện Thoại</label>
                                                <div class="input-group">
                                                    <span class="input-group-text auth-input-prefix">
                                                        <i class="bi bi-flag-fill me-1 text-danger" style="font-size:.75rem"></i>+84
                                                    </span>
                                                    <input type="tel" class="form-control" id="regPhone" placeholder="09xxxxxxxxx" required inputmode="numeric" readonly>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regEmail">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="regEmail" placeholder="you@example.com" required>
                                                <div class="invalid-feedback d-block d-none" id="emailFeedback">Email không hợp lệ.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regPassword">Mật khẩu <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" id="regPassword" placeholder="Nhập mật khẩu (tối thiểu 6 ký tự)" required>
                                                <div class="invalid-feedback d-block d-none" id="passwordFeedback">Mật khẩu phải từ 6 ký tự trở lên.</div>
                                            </div>
                                            <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnRegister">
                                                <i class="bi bi-check-lg me-2"></i>Hoàn tất đăng ký
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-link p-0 text-muted small mt-3 d-flex align-items-center gap-1" id="btnBackToLogin">
                                            <i class="bi bi-arrow-left"></i>Quay lại đăng nhập
                                        </button>
                                    </div>

                                    <div id="stepOtp" class="d-none">
                                        <div class="otp-info-badge mb-4" id="otpInfoBadge">
                                            <i class="bi bi-phone-fill me-2" id="otpInfoIcon"></i>
                                            <span id="otpInfoText">Mã OTP đã gửi đến <strong id="otpPhoneDisplay"></strong></span>
                                        </div>

                                        <form id="formStep2" novalidate>
                                            <label class="form-label fw-medium text-muted mb-2 d-block">Nhập mã 6 chữ số</label>
                                            <div class="otp-inputs mb-2">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" autocomplete="one-time-code" aria-label="OTP chữ số 1">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 2">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 3">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 4">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 5">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 6">
                                            </div>
                                            <p class="text-danger small d-none mb-3" id="otpError">Vui lòng nhập đầy đủ mã OTP.</p>

                                            <div class="otp-resend-row mb-4">
                                                <span id="otpCountdownText" class="text-muted small"></span>
                                                <button type="button" class="btn btn-link p-0 auth-alt-link small d-none" id="btnResendOtp">
                                                    <i class="bi bi-arrow-clockwise me-1"></i>Gửi lại mã
                                                </button>
                                            </div>

                                            <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnConfirmOtp">
                                                <i class="bi bi-shield-check me-2"></i>Xác nhận đăng nhập
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-link p-0 text-muted small mt-3 d-flex align-items-center gap-1" id="btnBackToPhone">
                                            <i class="bi bi-arrow-left"></i>Thay đổi số điện thoại
                                        </button>
                                    </div>


                                </div>
                            </div>
                            <div class="col-lg-5 auth-brand-box">
                                <div class="h-100 d-flex flex-column justify-content-center p-4 p-md-5 auth-brand-content">
                                    <img src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/images/logo.png" alt="<?= $tenPK ?>" class="mb-4 auth-brand-logo">
                                    <h2 class="font-heading h4 mb-3">Nâng tầm vẻ đẹp chuẩn y khoa</h2>
                                    <ul class="list-unstyled auth-brand-list mb-0">
                                        <li><i class="bi bi-shield-check text-primary me-2"></i>Bác sĩ chuyên khoa đồng hành 1:1</li>
                                        <li><i class="bi bi-stars text-primary me-2"></i>Phác đồ cá nhân hóa theo làn da</li>
                                        <li><i class="bi bi-heart-pulse text-primary me-2"></i>Công nghệ chuẩn FDA, an toàn</li>
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
    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/js/otp-api-handler.js"></script>
    <script>
    (function () {
        'use strict';

        var stepPhone = document.getElementById('stepPhone');
        var stepOtp = document.getElementById('stepOtp');
        var stepRegister = document.getElementById('stepRegister');
        var formStep1 = document.getElementById('formStep1');
        var formStep2 = document.getElementById('formStep2');
        var formRegister = document.getElementById('formRegister');
        var otpDigits = document.querySelectorAll('.otp-digit');
        var countdownTimer = null;

        function maskPhone(phone) {
            if (!phone) return '';
            var clean = phone.replace(/\D/g, '');
            return clean.slice(0, 3) + ' **** ' + clean.slice(-3);
        }

        function startCountdown(seconds) {
            clearInterval(countdownTimer);
            var remaining = seconds;
            var countdownEl = document.getElementById('otpCountdownText');
            var btnResend = document.getElementById('btnResendOtp');
            btnResend.classList.add('d-none');

            function tick() {
                if (remaining > 0) {
                    countdownEl.innerHTML = 'Gửi lại mã sau <span class="otp-countdown-num">' + remaining + 's</span>';
                } else {
                    countdownEl.innerHTML = '';
                    btnResend.classList.remove('d-none');
                    clearInterval(countdownTimer);
                }
                remaining--;
            }
            tick();
            countdownTimer = setInterval(tick, 1000);
        }

        // ────── OTP Digit Navigation ──────
        otpDigits.forEach(function (input, idx) {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(-1);
                this.classList.remove('otp-digit-error');
                if (this.value && idx < otpDigits.length - 1) {
                    otpDigits[idx + 1].focus();
                }
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !this.value && idx > 0) {
                    otpDigits[idx - 1].focus();
                }
            });

            input.addEventListener('paste', function (e) {
                e.preventDefault();
                var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach(function (char, i) {
                    if (otpDigits[idx + i]) otpDigits[idx + i].value = char;
                });
                var nextIdx = Math.min(idx + pasted.length, otpDigits.length - 1);
                otpDigits[nextIdx].focus();
            });
        });

        // ────── STEP 1: Check Phone & Send OTP ──────
        formStep1.addEventListener('submit', async function (e) {
            e.preventDefault();

            var phoneInput = document.getElementById('loginPhone');
            var phoneVal = phoneInput.value.trim();
            var phoneFeedback = document.getElementById('phoneFeedback');

            if (!isValidPhone(phoneVal)) {
                phoneInput.classList.add('is-invalid');
                phoneInput.classList.remove('is-valid');
                phoneFeedback.classList.remove('d-none');
                return;
            }

            phoneInput.classList.remove('is-invalid');
            phoneInput.classList.add('is-valid');
            phoneFeedback.classList.add('d-none');

            var btn = document.getElementById('btnSendOtp');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang kiểm tra...';

            // 🔄 Check if phone exists
            const checkResult = await checkPhone(phoneVal);
            
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-2"></i>Đăng nhập';

            if (checkResult.success && checkResult.data && checkResult.data.exists) {
                // ✅ Phone exists - Send OTP
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang gửi...';
                btn.disabled = true;

                const result = await sendOtpLogin(phoneVal);
                
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send me-2"></i>Đăng nhập';

                if (result.success) {
                    var infoIcon = document.getElementById('otpInfoIcon');
                    var infoText = document.getElementById('otpInfoText');
                    if (result.data && result.data.email_masked) {
                        infoIcon.className = 'bi bi-envelope-fill me-2 text-primary';
                        infoText.innerHTML = 'Mã OTP đã gửi đến email <strong>' + result.data.email_masked + '</strong>';
                    } else {
                        infoIcon.className = 'bi bi-phone-fill me-2';
                        infoText.innerHTML = 'Mã OTP đã tạo cho <strong>' + maskPhone(phoneVal) + '</strong>';
                    }

                    stepPhone.classList.add('d-none');
                    stepOtp.classList.remove('d-none');
                    otpDigits[0].focus();

                    sessionStorage.setItem('login_phone', phoneVal);
                    startCountdown(result.expiresIn || 300);

                    document.getElementById('stepDot1').classList.remove('active');
                    document.getElementById('stepDot1').classList.add('done');
                    document.getElementById('stepDot2').classList.add('active');

                    // Dev mode: tự điền OTP
                    if (result.data && result.data.dev_otp) {
                        var devOtp = String(result.data.dev_otp);
                        otpDigits.forEach(function(input, i) {
                            input.value = devOtp[i] || '';
                        });
                        showAlert('Dev Mode', 'OTP chưa có SMS/Email — mã đã tự điền: <strong>' + devOtp + '</strong>', 'warning');
                    }
                } else {
                    showAlert('Lỗi', result.message || 'Không thể gửi OTP', 'danger');
                }
            } else {
                // ❌ Phone does not exist - Show register form
                document.getElementById('regPhone').value = phoneVal;
                stepPhone.classList.add('d-none');
                stepRegister.classList.remove('d-none');
                document.getElementById('regEmail').focus();
            }
        });

        // ────── REGISTER FORM ──────
        formRegister.addEventListener('submit', async function (e) {
            e.preventDefault();

            var nameVal = document.getElementById('regName').value.trim();
            var phoneVal = document.getElementById('regPhone').value.trim();
            var emailVal = document.getElementById('regEmail').value.trim();
            var passwordVal = document.getElementById('regPassword').value.trim();

            // Validate tên
            if (!nameVal || nameVal.length < 3) {
                document.getElementById('regName').classList.add('is-invalid');
                document.getElementById('nameFeedback').classList.remove('d-none');
                return;
            }

            // Validate email
            if (!isValidEmail(emailVal)) {
                document.getElementById('regEmail').classList.add('is-invalid');
                document.getElementById('emailFeedback').classList.remove('d-none');
                return;
            }

            // Validate mật khẩu
            if (!passwordVal || passwordVal.length < 6) {
                document.getElementById('regPassword').classList.add('is-invalid');
                document.getElementById('passwordFeedback').classList.remove('d-none');
                return;
            }

            // Clear errors
            document.getElementById('regName').classList.remove('is-invalid');
            document.getElementById('regEmail').classList.remove('is-invalid');
            document.getElementById('regPassword').classList.remove('is-invalid');
            document.getElementById('nameFeedback').classList.add('d-none');
            document.getElementById('emailFeedback').classList.add('d-none');
            document.getElementById('passwordFeedback').classList.add('d-none');

            var btn = document.getElementById('btnRegister');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang đăng ký...';

            try {
                // 🔄 Gọi API registerPhone với đầy đủ thông tin
                const result = await registerPhone(nameVal, phoneVal, emailVal, passwordVal);
                
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Hoàn tất đăng ký';

                if (result.success) {
                    // ✅ Registration successful
                    showAlert('Thành công', 'Bạn vừa đăng ký tài khoản thành công. Email xác nhận đã được gửi đến ' + emailVal, 'success');
                    
                    // Wait then return to login
                    setTimeout(function() {
                        resetToLogin();
                    }, 2000);
                } else {
                    showAlert('Lỗi', result.message || 'Không thể đăng ký', 'danger');
                }
            } catch (error) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Hoàn tất đăng ký';
                showAlert('Lỗi', 'Lỗi kết nối: ' + error.message, 'danger');
            }
        });

        // ────── BACK TO LOGIN ──────
        document.getElementById('btnBackToLogin').addEventListener('click', function () {
            resetToLogin();
        });

        function resetToLogin() {
            stepRegister.classList.add('d-none');
            stepPhone.classList.remove('d-none');
            document.getElementById('loginPhone').value = '';
            document.getElementById('regName').value = '';
            document.getElementById('regEmail').value = '';
            document.getElementById('regPassword').value = '';
            document.getElementById('regPhone').value = '';
            document.getElementById('regName').classList.remove('is-invalid', 'is-valid');
            document.getElementById('regEmail').classList.remove('is-invalid', 'is-valid');
            document.getElementById('regPassword').classList.remove('is-invalid', 'is-valid');
            document.getElementById('nameFeedback').classList.add('d-none');
            document.getElementById('emailFeedback').classList.add('d-none');
            document.getElementById('passwordFeedback').classList.add('d-none');
            document.getElementById('loginPhone').focus();
        }

        // ────── RESEND OTP ──────
        document.getElementById('btnResendOtp').addEventListener('click', async function () {
            var phoneVal = sessionStorage.getItem('login_phone');
            if (!phoneVal) {
                showAlert('Lỗi', 'Vui lòng nhập lại số điện thoại', 'danger');
                return;
            }

            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang gửi...';

            const result = await sendOtpLogin(phoneVal);
            
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Gửi lại mã';
            
            if (result.success) {
                otpDigits.forEach(function (d) { d.value = ''; d.classList.remove('otp-digit-error'); });
                document.getElementById('otpError').classList.add('d-none');
                startCountdown(result.expiresIn || 300);
                showAlert('Thành công', 'Mã OTP mới đã được gửi', 'success');
            } else {
                showAlert('Lỗi', result.message || 'Lỗi gửi lại mã', 'danger');
            }
        });

        // ────── BACK TO PHONE OTP ──────
        document.getElementById('btnBackToPhone').addEventListener('click', function () {
            clearInterval(countdownTimer);
            stepOtp.classList.add('d-none');
            stepPhone.classList.remove('d-none');
            document.getElementById('stepDot1').classList.add('active');
            document.getElementById('stepDot1').classList.remove('done');
            document.getElementById('stepDot2').classList.remove('active');
            otpDigits.forEach(function (d) { d.value = ''; d.classList.remove('otp-digit-error'); });
            document.getElementById('otpError').classList.add('d-none');
            document.getElementById('loginPhone').value = '';
            document.getElementById('loginPhone').focus();
        });

        // ────── VERIFY OTP & LOGIN ──────
        formStep2.addEventListener('submit', async function (e) {
            e.preventDefault();
            var code = Array.from(otpDigits).map(function (d) { return d.value; }).join('');
            var phoneVal = sessionStorage.getItem('login_phone');

            if (!phoneVal) {
                showAlert('Lỗi', 'Vui lòng bắt đầu lại quá trình đăng nhập', 'danger');
                return;
            }

            if (code.length < 6) {
                otpDigits.forEach(function (d) { if (!d.value) d.classList.add('otp-digit-error'); });
                document.getElementById('otpError').classList.remove('d-none');
                return;
            }

            otpDigits.forEach(function (d) { d.classList.remove('otp-digit-error'); });
            document.getElementById('otpError').classList.add('d-none');

            var btn = document.getElementById('btnConfirmOtp');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang xác thực...';

            const result = await loginWithOtp(phoneVal, code);
            
            if (result.success) {
                // ✅ Login thành công - chuyển hướng theo vai trò
                var roleId = result.user ? parseInt(result.user.maVaiTro || result.user.role_id) : null;
                if (roleId === 1) {
                    window.location.href = 'index.php?route=admin/dashboard';
                } else if (roleId === 2) {
                    window.location.href = 'index.php?route=bacsi/dashboard';
                } else if (roleId === 3) {
                    window.location.href = 'index.php?route=letan/dashboard';
                } else {
                    window.location.href = 'index.php?route=profile';
                }
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-shield-check me-2"></i>Xác nhận đăng nhập';
                
                if (result.message.includes('OTP') || result.message.includes('otp')) {
                    otpDigits.forEach(function(d) { d.classList.add('otp-digit-error'); });
                }
                
                document.getElementById('otpError').classList.remove('d-none');
                showAlert('Lỗi', result.message || 'Xác thực thất bại', 'danger');
            }
        });
    })();

    // ════════════════════════════════════════════════════════════
    // HELPER FUNCTIONS - Validation & Utilities
    // ════════════════════════════════════════════════════════════

    /**
     * Validate Vietnamese phone number
     * Accepts: 09xxxxxxxx, 08xxxxxxxx, 07xxxxxxxx, etc.
     */
    function isValidPhone(phone) {
        var clean = phone.replace(/\D/g, '');
        // Vietnamese phone: 10 digits starting with 0
        return /^0[0-9]{9}$/.test(clean);
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    function showAlert(title, message, type) {
        // Xóa thông báo cũ (nếu có)
        var existing = document.querySelectorAll('.auth-toast-alert');
        existing.forEach(function(el) { el.remove(); });

        var icons = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-triangle-fill', warning: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };
        var icon = icons[type] || 'bi-info-circle-fill';

        var alertEl = document.createElement('div');
        alertEl.className = 'auth-toast-alert alert alert-' + type + ' alert-dismissible d-flex align-items-start gap-2 shadow-sm fade show';
        alertEl.setAttribute('role', 'alert');
        alertEl.innerHTML = '<i class="bi ' + icon + ' flex-shrink-0 mt-1"></i>'
            + '<div><strong>' + title + '</strong><br><span class="small">' + message + '</span></div>'
            + '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>';

        // Chèn thẳng vào trong card-body, phía trên form đang hiển thị
        var cardBody = document.querySelector('.card-body');
        if (cardBody) {
            cardBody.insertAdjacentElement('afterbegin', alertEl);
            cardBody.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Tự đóng sau 5 giây
        setTimeout(function() {
            if (alertEl && alertEl.parentNode) {
                var bs = bootstrap.Alert.getOrCreateInstance(alertEl);
                if (bs) bs.close();
            }
        }, 5000);
    }
    </script>
</body>
</html>

