<?php
$tenPK = htmlspecialchars($phongKham['tenPhongKham'] ?? 'DarmaSoft Clinic', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Controllers\ApiController::generateCsrfToken()) ?>">
    <title>Đăng ký | <?= $tenPK ?></title>

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
                <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>index.php?route=login" class="btn btn-primary rounded-pill px-4">Đăng nhập</a>
            </div>
        </div>
    </nav>

    <main class="auth-main">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="card auth-card">
                        <div class="row g-0">

                            <!-- ── Form panel ── -->
                            <div class="col-lg-7">
                                <div class="card-body p-md-5">

                                    <!-- Step indicator -->
                                    <div class="auth-steps mb-4">
                                        <div class="auth-step active" id="stepDot1">
                                            <span class="auth-step-num">1</span>
                                            <span class="auth-step-label">Thông tin</span>
                                        </div>
                                        <div class="auth-step-line"></div>
                                        <div class="auth-step" id="stepDot2">
                                            <span class="auth-step-num">2</span>
                                            <span class="auth-step-label">Xác thực OTP</span>
                                        </div>
                                    </div>

                                    <!-- ── Step 1: Phone + Info ── -->
                                    <div id="stepPhone">
                                        <div class="auth-eyebrow">Create Account</div>
                                        <h1 class="auth-title h2">Tạo tài khoản mới</h1>
                                        <div class="auth-separator"></div>
                                        <p class="auth-hint">Đăng ký bằng số điện thoại. Mã OTP xác minh sẽ được gửi về số của bạn.</p>

                                        <form id="formStep1" novalidate>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regName">Họ và tên</label>
                                                <input type="text" class="form-control" id="regName" placeholder="Nguyễn Văn A" required>
                                                <div class="invalid-feedback">Vui lòng nhập họ và tên.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regPhone">Số điện thoại</label>
                                                <div class="input-group">
                                                    <span class="input-group-text auth-input-prefix">
                                                        <i class="bi bi-flag-fill me-1 text-danger" style="font-size:.75rem"></i>+84
                                                    </span>
                                                    <input type="tel" class="form-control" id="regPhone"
                                                           placeholder="09x xxxx xxxx" required
                                                           inputmode="numeric">
                                                </div>
                                                <div class="invalid-feedback d-block d-none" id="phoneFeedback">Số điện thoại không hợp lệ.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regEmail">Email</label>
                                                <input type="email" class="form-control" id="regEmail" placeholder="example@gmail.com" required>
                                                <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regUsername">Tên đăng nhập</label>
                                                <input type="text" class="form-control" id="regUsername" placeholder="username_của_bạn" required>
                                                <div class="invalid-feedback">Tên đăng nhập phải từ 3 ký tự trở lên.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium text-muted" for="regPassword">Mật khẩu</label>
                                                <input type="password" class="form-control" id="regPassword" placeholder="Nhập mật khẩu (tối thiểu 6 ký tự)" required>
                                                <div class="invalid-feedback">Mật khẩu phải từ 6 ký tự trở lên.</div>
                                            </div>
                                            <div class="form-check mb-4">
                                                <input class="form-check-input" type="checkbox" id="agreePolicy" required>
                                                <label class="form-check-label text-muted" for="agreePolicy">
                                                    Tôi đồng ý với <a href="#" class="auth-alt-link">chính sách bảo mật</a> và <a href="#" class="auth-alt-link">điều khoản dịch vụ</a>.
                                                </label>
                                                <div class="invalid-feedback">Vui lòng đồng ý với điều khoản.</div>
                                            </div>
                                            <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit">
                                                <i class="bi bi-send me-2"></i>Gửi mã OTP
                                            </button>
                                        </form>
                                    </div>

                                    <!-- ── Step 2: OTP verify ── -->
                                    <div id="stepOtp" class="d-none">
                                        <div class="auth-eyebrow">Xác thực OTP</div>
                                        <h1 class="auth-title h2">Nhập mã xác thực</h1>
                                        <div class="auth-separator"></div>
                                        <div class="otp-info-badge mb-4">
                                            <i class="bi bi-phone-fill me-2"></i>
                                            Mã OTP đã gửi đến <strong id="otpPhoneDisplay"></strong>
                                        </div>

                                        <form id="formStep2" novalidate>
                                            <label class="form-label fw-medium text-muted mb-2 d-block">Nhập mã 6 chữ số</label>
                                            <div class="otp-inputs mb-2" id="otpInputsGroup">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" autocomplete="one-time-code" aria-label="OTP chữ số 1">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 2">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 3">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 4">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 5">
                                                <input type="text" class="otp-digit form-control" maxlength="1" inputmode="numeric" aria-label="OTP chữ số 6">
                                            </div>
                                            <p class="text-danger small d-none mb-3" id="otpError">Mã OTP không đúng. Vui lòng thử lại.</p>

                                            <div class="otp-resend-row mb-4">
                                                <span id="otpCountdownText" class="text-muted small"></span>
                                                <button type="button" class="btn btn-link p-0 auth-alt-link small d-none" id="btnResendOtp">
                                                    <i class="bi bi-arrow-clockwise me-1"></i>Gửi lại mã
                                                </button>
                                            </div>

                                            <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnConfirmOtp">
                                                <i class="bi bi-shield-check me-2"></i>Xác nhận &amp; Đăng ký
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-link p-0 text-muted small mt-3 d-flex align-items-center gap-1" id="btnBackToPhone">
                                            <i class="bi bi-arrow-left"></i>Thay đổi số điện thoại
                                        </button>
                                    </div>

                                    <p class="text-muted mb-0 mt-4">Đã có tài khoản? <a href="index.php?route=login" class="auth-alt-link">Đăng nhập ngay</a></p>
                                </div>
                            </div>

                            <!-- ── Brand panel ── -->
                            <div class="col-lg-5 auth-brand-box">
                                <div class="h-100 d-flex flex-column justify-content-center p-4 p-md-5 auth-brand-content">
                                    <img src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/images/logo.png" alt="<?= $tenPK ?>" class="mb-4 auth-brand-logo">
                                    <h2 class="font-heading h4 mb-3">Trở thành thành viên <?= $tenPK ?></h2>
                                    <ul class="list-unstyled auth-brand-list mb-0">
                                        <li><i class="bi bi-award text-primary me-2"></i>Tích lũy điểm nâng hạng thành viên</li>
                                        <li><i class="bi bi-calendar-check text-primary me-2"></i>Ưu tiên lịch hẹn khung giờ đẹp</li>
                                        <li><i class="bi bi-gift text-primary me-2"></i>Nhận ưu đãi độc quyền mỗi tháng</li>
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

        var stepPhone     = document.getElementById('stepPhone');
        var stepOtp       = document.getElementById('stepOtp');
        var formStep1     = document.getElementById('formStep1');
        var formStep2     = document.getElementById('formStep2');
        var otpDigits     = document.querySelectorAll('.otp-digit');
        var countdownTimer = null;

        /* ── Helpers ── */
        function maskPhone(phone) {
            if (!phone) return '';
            // Hiển thị số điện thoại dạng: 09xx xxxx xxx
            const cleaned = phone.replace(/\D/g, '');
            const start = cleaned.substring(0, 4);
            const end = cleaned.substring(cleaned.length - 3);
            return start + '****' + end;
        }

        function startCountdown(seconds) {
            clearInterval(countdownTimer);
            var remaining = seconds;
            var countdownEl = document.getElementById('otpCountdownText');
            var btnResend   = document.getElementById('btnResendOtp');
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

        function showInvalid(el, msg) {
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');
            if (msg) {
                var fb = el.nextElementSibling;
                if (fb && fb.classList.contains('invalid-feedback')) fb.textContent = msg;
            }
        }
        function showValid(el) {
            el.classList.remove('is-invalid');
            el.classList.add('is-valid');
        }

        /* ── OTP digit navigation ── */
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

        /* ── Step 1 submit ── */
        formStep1.addEventListener('submit', async function (e) {
            e.preventDefault();
            var nameVal    = document.getElementById('regName').value.trim();
            var phoneVal   = document.getElementById('regPhone').value.trim();
            var emailVal   = document.getElementById('regEmail').value.trim();
            var passwordVal = document.getElementById('regPassword').value;
            var policy     = document.getElementById('agreePolicy').checked;
            var valid      = true;

            var nameInput = document.getElementById('regName');
            if (!nameVal || nameVal.length < 3) { nameInput.classList.add('is-invalid'); valid = false; }
            else { nameInput.classList.remove('is-invalid'); nameInput.classList.add('is-valid'); }

            var phoneInput    = document.getElementById('regPhone');
            var phoneFeedback = document.getElementById('phoneFeedback');
            if (!isValidPhone(phoneVal)) {
                phoneInput.classList.add('is-invalid');
                phoneFeedback.classList.remove('d-none');
                valid = false;
            } else {
                phoneInput.classList.remove('is-invalid');
                phoneInput.classList.add('is-valid');
                phoneFeedback.classList.add('d-none');
            }

            var emailInput = document.getElementById('regEmail');
            if (!isValidEmail(emailVal)) { emailInput.classList.add('is-invalid'); valid = false; }
            else { emailInput.classList.remove('is-invalid'); emailInput.classList.add('is-valid'); }

            var passwordInput = document.getElementById('regPassword');
            if (passwordVal.length < 6) { passwordInput.classList.add('is-invalid'); valid = false; }
            else { passwordInput.classList.remove('is-invalid'); passwordInput.classList.add('is-valid'); }

            var policyInput = document.getElementById('agreePolicy');
            if (!policy) { policyInput.classList.add('is-invalid'); valid = false; }
            else policyInput.classList.remove('is-invalid');

            if (!valid) return;

            // 🔄 Gọi API registerPhone
            var btn = document.querySelector('form#formStep1 button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang xử lý...';

            const result = await registerPhone(nameVal, phoneVal, emailVal, passwordVal);
            
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-2"></i>Gửi mã OTP';

            if (result.success) {
                // Lưu dữ liệu để xác thực OTP
                sessionStorage.setItem('reg_phone', phoneVal);
                sessionStorage.setItem('reg_verify_token', result.verifyToken || '');

                document.getElementById('otpPhoneDisplay').textContent = maskPhone(phoneVal);
                stepPhone.classList.add('step-exit');
                setTimeout(function () {
                    stepPhone.classList.add('d-none');
                    stepPhone.classList.remove('step-exit');
                    stepOtp.classList.remove('d-none');
                    stepOtp.classList.add('step-enter');
                    setTimeout(function () { stepOtp.classList.remove('step-enter'); }, 350);
                    otpDigits[0].focus();
                }, 180);

                document.getElementById('stepDot1').classList.remove('active');
                document.getElementById('stepDot1').classList.add('done');
                document.getElementById('stepDot2').classList.add('active');
                startCountdown(result.expiresIn || 300);
                
                showAlert('Thành công', 'Hãy nhập mã OTP được gửi tới số điện thoại của bạn', 'success');
            } else {
                showAlert('Lỗi', result.message || 'Không thể đăng ký tài khoản', 'danger');
            }
        });

        /* ── Resend OTP ── */
        document.getElementById('btnResendOtp').addEventListener('click', async function () {
            var phoneVal = sessionStorage.getItem('reg_phone');
            if (!phoneVal) {
                showAlert('Lỗi', 'Vui lòng bắt đầu lại', 'danger');
                return;
            }

            // Note: API không có endpoint gửi lại OTP, user phải khôi phục từ số điện thoại
            showAlert('Thông báo', 'Nếu bạn không nhận được mã, vui lòng kiểm tra hoặc đăng ký lại', 'info');
        });

        /* ── Back to phone ── */
        document.getElementById('btnBackToPhone').addEventListener('click', function () {
            clearInterval(countdownTimer);
            stepOtp.classList.add('d-none');
            stepPhone.classList.remove('d-none');
            document.getElementById('stepDot1').classList.add('active');
            document.getElementById('stepDot1').classList.remove('done');
            document.getElementById('stepDot2').classList.remove('active');
            otpDigits.forEach(function (d) { d.value = ''; d.classList.remove('otp-digit-error'); });
            document.getElementById('otpError').classList.add('d-none');
        });

        /* ── Step 2 submit ── */
        formStep2.addEventListener('submit', function (e) {
            e.preventDefault();
            var code = Array.from(otpDigits).map(function (d) { return d.value; }).join('');
            
            if (code.length < 6) {
                otpDigits.forEach(function (d) { if (!d.value) d.classList.add('otp-digit-error'); });
                return;
            }

            otpDigits.forEach(function (d) { d.classList.remove('otp-digit-error'); });
            document.getElementById('otpError').classList.add('d-none');

            // 🔄 Gọi API verify-otp (stored locally, just confirm)
            var btn = document.getElementById('btnConfirmOtp');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang xác thực...';

            // In real scenario, verify OTP on server
            // For now, just show success and redirect
            setTimeout(function() {
                showAlert('Thành công!', 'Tài khoản đã được tạo. Hãy đăng nhập với tài khoản của bạn', 'success');
                
                sessionStorage.removeItem('reg_phone');
                sessionStorage.removeItem('reg_verify_token');
                
                setTimeout(function() {
                    window.location.href = 'index.php?route=login';
                }, 2000);
            }, 1500);
        });

    })();
    </script>
</body>
</html>

