<?php
$tenPK = htmlspecialchars($phongKham['tenPhongKham'] ?? 'DarmaSoft Clinic', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Controllers\ApiController::generateCsrfToken()) ?>">
    <title>Khôi phục tài khoản | <?= $tenPK ?></title>

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
                <div class="col-lg-8 col-xl-7">
                    <div class="card auth-card">
                        <div class="card-body p-4 p-md-5">
                            <div class="auth-eyebrow">Lấy Lại Quyền Truy Cập</div>
                            <h1 class="auth-title h2">Khôi phục tài khoản</h1>
                            <div class="auth-separator"></div>
                            <p class="auth-hint">Chọn tùy chọn phù hợp với tình huống của bạn.</p>

                            <!-- Tab toggle -->
                            <div class="d-flex gap-2 mb-4" id="modeTabs">
                                <button type="button" class="btn btn-primary rounded-pill flex-fill" id="tabLookup" onclick="switchMode('lookup')">
                                    <i class="bi bi-search me-1"></i>Tìm lại số điện thoại
                                </button>
                                <button type="button" class="btn btn-outline-primary rounded-pill flex-fill" id="tabReset" onclick="switchMode('reset')">
                                    <i class="bi bi-telephone-x me-1"></i>Đổi số điện thoại
                                </button>
                            </div>

                            <!-- ─── MODE A: Tìm lại số điện thoại ─── -->
                            <div id="modeLookup">
                                <p class="text-muted small mb-3">Nhập email đã đăng ký. Chúng tôi sẽ gửi thông tin số điện thoại về email của bạn.</p>
                                <form id="formLookup" novalidate>
                                    <div class="mb-4">
                                        <label class="form-label fw-medium text-muted" for="lookupEmail">Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text auth-input-prefix">
                                                <i class="bi bi-envelope me-1 text-primary"></i>
                                            </span>
                                            <input type="email" class="form-control" id="lookupEmail" placeholder="you@example.com" required>
                                        </div>
                                        <div class="invalid-feedback d-block d-none" id="lookupEmailFeedback">Email không hợp lệ.</div>
                                    </div>
                                    <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnLookup">
                                        <i class="bi bi-send me-2"></i>Gửi thông tin
                                    </button>
                                </form>
                                <div id="lookupResult" class="mt-4"></div>
                            </div>

                            <!-- ─── MODE B: Đổi số điện thoại (3 bước) ─── -->
                            <div id="modeReset" class="d-none">

                                <!-- Step indicator -->
                                <div class="d-flex align-items-center mb-4" id="stepIndicator">
                                    <div class="step-dot active" id="dot1">1</div>
                                    <div class="step-line" id="line1"></div>
                                    <div class="step-dot" id="dot2">2</div>
                                    <div class="step-line" id="line2"></div>
                                    <div class="step-dot" id="dot3">3</div>
                                </div>

                                <!-- Bước 1: Nhập email -->
                                <div id="stepEmail">
                                    <p class="text-muted small mb-3">Nhập email đã đăng ký. Chúng tôi sẽ gửi mã OTP để xác minh danh tính.</p>
                                    <form id="formResetEmail" novalidate>
                                        <div class="mb-4">
                                            <label class="form-label fw-medium text-muted" for="resetEmail">Email đã đăng ký</label>
                                            <div class="input-group">
                                                <span class="input-group-text auth-input-prefix">
                                                    <i class="bi bi-envelope me-1 text-primary"></i>
                                                </span>
                                                <input type="email" class="form-control" id="resetEmail" placeholder="you@example.com" required>
                                            </div>
                                            <div class="invalid-feedback d-block d-none" id="resetEmailFeedback">Email không hợp lệ.</div>
                                        </div>
                                        <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnSendOtpReset">
                                            <i class="bi bi-send me-2"></i>Gửi mã xác minh
                                        </button>
                                    </form>
                                </div>

                                <!-- Bước 2: Nhập OTP -->
                                <div id="stepOtp" class="d-none">
                                    <div class="otp-info-badge mb-4">
                                        <i class="bi bi-envelope-fill me-2 text-primary"></i>
                                        <span>Mã OTP đã gửi đến <strong id="otpEmailDisplay"></strong></span>
                                    </div>
                                    <form id="formVerifyOtp" novalidate>
                                        <div class="mb-4">
                                            <label class="form-label fw-medium text-muted text-center d-block">Nhập mã OTP 6 chữ số</label>
                                            <div class="otp-inputs d-flex justify-content-center gap-2" id="otpResetInputs">
                                                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                            </div>
                                            <div class="text-center mt-2">
                                                <small class="text-muted">Mã hết hạn sau: <span id="otpCountdown" class="fw-bold text-danger">5:00</span></small>
                                            </div>
                                            <div class="invalid-feedback d-block d-none text-center" id="otpFeedback">OTP không đúng hoặc đã hết hạn.</div>
                                        </div>
                                        <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnVerifyOtp">
                                            <i class="bi bi-shield-check me-2"></i>Xác minh OTP
                                        </button>
                                        <div class="text-center mt-3">
                                            <button type="button" class="btn btn-link text-muted p-0 small" id="btnResendOtp">
                                                Chưa nhận được mã? <span class="text-primary">Gửi lại</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Bước 3: Nhập số điện thoại mới -->
                                <div id="stepPhone" class="d-none">
                                    <div class="alert alert-success py-2 mb-3">
                                        <i class="bi bi-check-circle-fill me-2"></i>Xác minh thành công! Bây giờ hãy nhập số điện thoại mới.
                                    </div>
                                    <form id="formNewPhone" novalidate>
                                        <div class="mb-4">
                                            <label class="form-label fw-medium text-muted" for="newPhone">Số điện thoại mới</label>
                                            <div class="input-group">
                                                <span class="input-group-text auth-input-prefix">
                                                    <i class="bi bi-phone me-1 text-primary"></i>
                                                </span>
                                                <input type="tel" class="form-control" id="newPhone" placeholder="0912 345 678" maxlength="11" inputmode="numeric" required>
                                            </div>
                                            <div class="form-text text-muted">Số điện thoại Việt Nam, 10-11 chữ số, bắt đầu bằng 0</div>
                                            <div class="invalid-feedback d-block d-none" id="phoneFeedback">Số điện thoại không hợp lệ.</div>
                                        </div>
                                        <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold" type="submit" id="btnUpdatePhone">
                                            <i class="bi bi-check2-circle me-2"></i>Cập nhật số điện thoại
                                        </button>
                                    </form>
                                </div>

                                <!-- Hoàn thành -->
                                <div id="stepDone" class="d-none text-center py-3">
                                    <div class="mb-3">
                                        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
                                    </div>
                                    <h5 class="fw-bold text-success">Đổi số thành công!</h5>
                                    <p class="text-muted">Số điện thoại của bạn đã được cập nhật. Bây giờ bạn có thể đăng nhập với số mới.</p>
                                    <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>index.php?route=login" class="btn btn-primary rounded-pill px-5">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập ngay
                                    </a>
                                </div>

                                <div id="resetError" class="mt-3"></div>
                            </div>

                            <div class="mt-4 d-flex flex-wrap gap-3">
                                <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>index.php?route=login" class="auth-alt-link"><i class="bi bi-arrow-left me-1"></i>Quay lại đăng nhập</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
    .step-dot {
        width: 32px; height: 32px;
        border-radius: 50%;
        border: 2px solid #dee2e6;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 0.85rem;
        color: #6c757d;
        flex-shrink: 0;
        transition: all 0.3s;
    }
    .step-dot.active {
        background: var(--bs-primary); border-color: var(--bs-primary); color: #fff;
    }
    .step-dot.done {
        background: #198754; border-color: #198754; color: #fff;
    }
    .step-line {
        flex: 1; height: 2px; background: #dee2e6; margin: 0 4px;
        transition: background 0.3s;
    }
    .step-line.done { background: #198754; }
    .otp-info-badge {
        background: #f0f4ff; border-radius: 10px;
        padding: 12px 16px; font-size: 0.9rem;
    }
    .otp-digit {
        width: 46px; height: 52px;
        text-align: center; font-size: 1.3rem; font-weight: 700;
        border: 2px solid #dee2e6; border-radius: 10px;
        transition: border-color 0.2s;
    }
    .otp-digit:focus { border-color: var(--bs-primary); box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
    </style>

    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/js/csrf.js"></script>
    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/js/otp-api-handler.js"></script>
    <script>
    (function () {
        'use strict';

        // ─── State ───────────────────────────────────────────────────
        let currentMode       = 'lookup';
        let verifiedEmail     = '';
        let countdownInterval = null;

        // ─── Helpers ─────────────────────────────────────────────────
        function showError(container, msg) {
            container.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>${msg}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        }
        function showSuccess(container, msg) {
            container.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>${msg}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        }
        function setLoading(btn, originalHtml, loading) {
            if (loading) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
            } else {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        // ─── Mode switch ──────────────────────────────────────────────
        window.switchMode = function(mode) {
            currentMode = mode;
            document.getElementById('tabLookup').className = mode === 'lookup'
                ? 'btn btn-primary rounded-pill flex-fill'
                : 'btn btn-outline-primary rounded-pill flex-fill';
            document.getElementById('tabReset').className = mode === 'reset'
                ? 'btn btn-primary rounded-pill flex-fill'
                : 'btn btn-outline-primary rounded-pill flex-fill';
            document.getElementById('modeLookup').classList.toggle('d-none', mode !== 'lookup');
            document.getElementById('modeReset').classList.toggle('d-none', mode !== 'reset');
        };

        // ─── Step navigation (reset flow) ────────────────────────────
        function goToStep(step) {
            ['stepEmail', 'stepOtp', 'stepPhone', 'stepDone'].forEach(id =>
                document.getElementById(id).classList.add('d-none')
            );
            document.getElementById('step' + step).classList.remove('d-none');

            // Update dot indicators
            const stepNum = { Email: 1, Otp: 2, Phone: 3, Done: 4 }[step];
            for (let i = 1; i <= 3; i++) {
                const dot  = document.getElementById('dot' + i);
                const line = document.getElementById('line' + i);
                if (i < stepNum) {
                    dot.className  = 'step-dot done';
                    dot.innerHTML  = '<i class="bi bi-check-lg"></i>';
                    if (line) line.className = 'step-line done';
                } else if (i === stepNum) {
                    dot.className  = 'step-dot active';
                    dot.innerHTML  = i;
                    if (line) line.className = 'step-line';
                } else {
                    dot.className  = 'step-dot';
                    dot.innerHTML  = i;
                    if (line) line.className = 'step-line';
                }
            }
        }

        // ─── OTP digit keypads ────────────────────────────────────────
        function setupOtpInputs(container) {
            const inputs = container.querySelectorAll('.otp-digit');
            inputs.forEach((input, idx) => {
                input.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/, '').slice(0, 1);
                    if (this.value && idx < inputs.length - 1) inputs[idx + 1].focus();
                });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !this.value && idx > 0) inputs[idx - 1].focus();
                });
                input.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    inputs.forEach((inp, i) => inp.value = text[i] || '');
                    if (text.length >= inputs.length) inputs[inputs.length - 1].focus();
                });
            });
        }
        setupOtpInputs(document.getElementById('otpResetInputs'));

        // ─── Countdown ────────────────────────────────────────────────
        function startCountdown(seconds) {
            if (countdownInterval) clearInterval(countdownInterval);
            const el = document.getElementById('otpCountdown');
            let rem = seconds;
            el.textContent = formatCountdownTime(rem);
            countdownInterval = setInterval(() => {
                rem--;
                el.textContent = formatCountdownTime(rem);
                if (rem <= 0) {
                    clearInterval(countdownInterval);
                    el.textContent = 'Hết hạn';
                }
            }, 1000);
        }

        // ─── MODE A: Tìm lại SĐT ─────────────────────────────────────
        const formLookup  = document.getElementById('formLookup');
        const lookupEmail = document.getElementById('lookupEmail');
        const btnLookup   = document.getElementById('btnLookup');
        const lookupResult = document.getElementById('lookupResult');

        formLookup.addEventListener('submit', async function (e) {
            e.preventDefault();
            const emailVal = lookupEmail.value.trim();
            if (!isValidEmail(emailVal)) {
                lookupEmail.classList.add('is-invalid');
                document.getElementById('lookupEmailFeedback').classList.remove('d-none');
                return;
            }
            lookupEmail.classList.remove('is-invalid');
            lookupEmail.classList.add('is-valid');
            document.getElementById('lookupEmailFeedback').classList.add('d-none');

            setLoading(btnLookup, '<i class="bi bi-send me-2"></i>Gửi thông tin', true);
            const result = await forgotPhone(emailVal);
            setLoading(btnLookup, '<i class="bi bi-send me-2"></i>Gửi thông tin', false);

            if (result.success) {
                showSuccess(lookupResult,
                    'Thông tin đã gửi về <strong>' + emailVal + '</strong>. Kiểm tra hộp thư (kể cả thư rác) trong vòng 5 phút.');
                lookupEmail.value = '';
                lookupEmail.classList.remove('is-valid');
            } else {
                showError(lookupResult, result.message || 'Không thể gửi thông tin');
            }
        });

        // ─── MODE B — Bước 1: Gửi OTP ────────────────────────────────
        const formResetEmail   = document.getElementById('formResetEmail');
        const resetEmailInput  = document.getElementById('resetEmail');
        const btnSendOtpReset  = document.getElementById('btnSendOtpReset');
        const resetError       = document.getElementById('resetError');

        formResetEmail.addEventListener('submit', async function (e) {
            e.preventDefault();
            const emailVal = resetEmailInput.value.trim();
            if (!isValidEmail(emailVal)) {
                resetEmailInput.classList.add('is-invalid');
                document.getElementById('resetEmailFeedback').classList.remove('d-none');
                return;
            }
            resetEmailInput.classList.remove('is-invalid');
            document.getElementById('resetEmailFeedback').classList.add('d-none');

            setLoading(btnSendOtpReset, '<i class="bi bi-send me-2"></i>Gửi mã xác minh', true);
            const result = await sendOtpPhoneReset(emailVal);
            setLoading(btnSendOtpReset, '<i class="bi bi-send me-2"></i>Gửi mã xác minh', false);

            if (result.success) {
                verifiedEmail = emailVal;
                document.getElementById('otpEmailDisplay').textContent =
                    (result.data && result.data.email_masked) ? result.data.email_masked : emailVal;

                // Dev mode auto-fill
                if (result.data && result.data.dev_otp) {
                    const digits = document.querySelectorAll('#otpResetInputs .otp-digit');
                    String(result.data.dev_otp).split('').forEach((ch, i) => {
                        if (digits[i]) digits[i].value = ch;
                    });
                }

                startCountdown(result.data ? result.data.expires_in || 300 : 300);
                resetError.innerHTML = '';
                goToStep('Otp');
            } else {
                showError(resetError, result.message || 'Không thể gửi mã OTP');
            }
        });

        // ─── MODE B — Bước 2: Xác minh OTP ──────────────────────────
        const formVerifyOtp = document.getElementById('formVerifyOtp');
        const btnVerifyOtp  = document.getElementById('btnVerifyOtp');

        formVerifyOtp.addEventListener('submit', async function (e) {
            e.preventDefault();
            const otp = getOtpValue('#otpResetInputs');

            if (!isValidOtp(otp)) {
                document.getElementById('otpFeedback').classList.remove('d-none');
                return;
            }
            document.getElementById('otpFeedback').classList.add('d-none');

            // Lưu OTP để dùng ở bước 3 (tránh yêu cầu OTP lại)
            window._resetOtp = otp;

            // Chuyển thẳng sang bước 3 (OTP sẽ được verify cùng lúc submit số mới)
            resetError.innerHTML = '';
            goToStep('Phone');
        });

        // ─── Gửi lại OTP ─────────────────────────────────────────────
        document.getElementById('btnResendOtp').addEventListener('click', async function () {
            if (!verifiedEmail) return;
            setLoading(this, 'Chưa nhận được mã? <span class="text-primary">Gửi lại</span>', true);
            const result = await sendOtpPhoneReset(verifiedEmail);
            setLoading(this, 'Chưa nhận được mã? <span class="text-primary">Gửi lại</span>', false);
            if (result.success) {
                startCountdown(300);
                document.querySelectorAll('#otpResetInputs .otp-digit').forEach(i => i.value = '');
                document.querySelector('#otpResetInputs .otp-digit').focus();
                if (result.data && result.data.dev_otp) {
                    const digits = document.querySelectorAll('#otpResetInputs .otp-digit');
                    String(result.data.dev_otp).split('').forEach((ch, i) => {
                        if (digits[i]) digits[i].value = ch;
                    });
                }
            } else {
                showError(resetError, result.message || 'Không thể gửi lại OTP');
            }
        });

        // ─── MODE B — Bước 3: Cập nhật số mới ───────────────────────
        const formNewPhone  = document.getElementById('formNewPhone');
        const newPhoneInput = document.getElementById('newPhone');
        const btnUpdatePhone = document.getElementById('btnUpdatePhone');

        // Chỉ cho phép nhập số
        newPhoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });

        formNewPhone.addEventListener('submit', async function (e) {
            e.preventDefault();
            const phone = newPhoneInput.value.trim();

            if (!/^(0|84)\d{9,10}$/.test(phone)) {
                newPhoneInput.classList.add('is-invalid');
                document.getElementById('phoneFeedback').classList.remove('d-none');
                return;
            }
            newPhoneInput.classList.remove('is-invalid');
            document.getElementById('phoneFeedback').classList.add('d-none');

            setLoading(btnUpdatePhone, '<i class="bi bi-check2-circle me-2"></i>Cập nhật số điện thoại', true);
            const result = await resetPhoneWithOtp(verifiedEmail, window._resetOtp, phone);
            setLoading(btnUpdatePhone, '<i class="bi bi-check2-circle me-2"></i>Cập nhật số điện thoại', false);

            if (result.success) {
                if (countdownInterval) clearInterval(countdownInterval);
                goToStep('Done');
            } else {
                newPhoneInput.classList.add('is-invalid');
                showError(resetError, result.message || 'Không thể cập nhật số điện thoại');
                // Nếu OTP hết hạn → quay lại bước nhập OTP
                if (result.message && result.message.toLowerCase().includes('otp')) {
                    setTimeout(() => goToStep('Otp'), 1500);
                }
            }
        });

    })();
    </script>
</body>
</html>
