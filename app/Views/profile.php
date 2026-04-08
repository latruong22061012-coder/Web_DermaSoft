<?php
// Dữ liệu phòng khám từ DB
$tenPK = htmlspecialchars($phongKham['tenPhongKham'] ?? 'DarmaSoft Clinic', ENT_QUOTES, 'UTF-8');
$diaChi = htmlspecialchars($phongKham['diaChi'] ?? '', ENT_QUOTES, 'UTF-8');
$sdt = htmlspecialchars($phongKham['soDienThoai'] ?? '', ENT_QUOTES, 'UTF-8');
$emailPK = htmlspecialchars($phongKham['email'] ?? '', ENT_QUOTES, 'UTF-8');
$moTaPK = htmlspecialchars($phongKham['moTa'] ?? '', ENT_QUOTES, 'UTF-8');
$gioMo = $phongKham['gioMoCua'] ?? '';
$gioDong = $phongKham['gioDongCua'] ?? '';
$lichLV = htmlspecialchars($phongKham['lichLamViec'] ?? 'Thứ Hai - Chủ Nhật', ENT_QUOTES, 'UTF-8');
$gioMoDisplay = $gioMo ? substr($gioMo, 0, 5) : '';
$gioDongDisplay = $gioDong ? substr($gioDong, 0, 5) : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân | <?= $tenPK ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <link href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    
    <link href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/aos/aos.css" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/css/style.css">
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/css/profile.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm transition-all" id="mainNav">
        <div class="container-fluid px-4 px-lg-5">
            <a class="navbar-brand logo-wrapper" href="index.php?route=home">
                <img src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/images/logo.png" alt="<?= $tenPK ?>" class="img-fluid logo-img">
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 text-uppercase fw-medium" style="font-family: var(--font-body); font-size: 0.9rem;">
                    <li class="nav-item px-2"><a class="nav-link" href="index.php?route=home#about">Không gian</a></li>
                    <li class="nav-item px-2"><a class="nav-link" href="index.php?route=home#doctor">Chuyên gia</a></li>
                    <li class="nav-item px-2"><a class="nav-link" href="index.php?route=home#process">Quy trình</a></li>
                    <li class="nav-item px-2"><a class="nav-link" href="index.php?route=home#products">Sản phẩm</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="index.php?route=home#booking" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">Đặt Lịch</a>
                    <div class="dropdown">
                        <a href="#" class="text-dark fs-4 dropdown-toggle icon-link" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-primary"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-3">
                            <li><a class="dropdown-item active bg-light-mint text-primary fw-bold" href="index.php?route=profile"><i class="bi bi-person-vcard me-2"></i>Hồ sơ của tôi</a></li>
                            <li><a class="dropdown-item" href="index.php?route=profile#v-pills-appointments"><i class="bi bi-clock-history me-2"></i>Lịch sử khám</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="index.php?route=logout"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <?php
        // Dữ liệu từ session (do ProfileController truyền vào)
        $customerName  = htmlspecialchars($user['HoTen'] ?? 'Người dùng', ENT_QUOTES, 'UTF-8');
        $customerPhone = htmlspecialchars($user['SoDienThoai'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerEmail = htmlspecialchars($user['Email'] ?? '', ENT_QUOTES, 'UTF-8');
        $avatarName    = urlencode(str_replace(' ', '+', $user['HoTen'] ?? 'User'));
        // Avatar: dùng ảnh đã upload nếu có, ngược lại fallback ui-avatars
        $storedAvatar = $user['AnhDaiDien'] ?? '';
        $baseUrl      = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $avatarSrcLg  = $storedAvatar
            ? $baseUrl . '/' . ltrim($storedAvatar, '/')
            : 'https://ui-avatars.com/api/?name=' . $avatarName . '&background=0F5C4D&color=fff&size=120';
        $avatarSrcSm  = $storedAvatar
            ? $baseUrl . '/' . ltrim($storedAvatar, '/')
            : 'https://ui-avatars.com/api/?name=' . $avatarName . '&background=0F5C4D&color=fff&size=96';
        // Biến thành viên (truyền từ controller)
        $hangLabel   = $hangHienTai ?? 'Thành viên';
        $hangBgColor = $hangColor   ?? '#0F5C4D';

        // Biến hạng tiếp theo
        $tenHangTiepLabel = $tenHangTiep ?? null;
        $diemConLaiLabel  = $diemConLai  ?? 0;
        $progressVal      = $progressPercent ?? 0;
    ?>

    <section class="profile-hero-section mt-5 pt-5 pb-4">
        <div class="container pt-4">
            <div class="profile-hero-card" data-aos="fade-up">
                <div class="profile-hero-main">
                    <div class="profile-avatar-wrap">
                        <img src="<?= htmlspecialchars($avatarSrcLg, ENT_QUOTES, 'UTF-8') ?>" alt="Avatar" class="profile-avatar-lg" id="heroAvatar">
                        <span class="profile-rank-badge" title="<?= htmlspecialchars($hangLabel, ENT_QUOTES,'UTF-8') ?>" style="background:<?= htmlspecialchars($hangBgColor, ENT_QUOTES,'UTF-8') ?>">
                            <i class="bi bi-award-fill"></i>
                            <?= htmlspecialchars($hangLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div>
                        <p class="text-uppercase small text-muted fw-semibold mb-2 profile-overline">Patient Profile</p>
                        <h1 class="font-heading fw-bold mb-2"><?php echo $customerName; ?></h1>
                        <div class="profile-meta-list">
                            <span><i class="bi bi-telephone-fill text-primary me-2"></i><?php echo $customerPhone; ?></span>
                            <?php if ($customerEmail): ?>
                            <span><i class="bi bi-envelope-fill text-primary me-2"></i><?php echo $customerEmail; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="profile-quick-stats">
                    <div class="quick-stat-item">
                        <span class="quick-stat-label">Điểm tích lũy</span>
                        <strong class="quick-stat-value"><?= number_format($diemTichLuy) ?></strong>
                    </div>
                    <div class="quick-stat-item">
                        <span class="quick-stat-label">Lịch sắp tới</span>
                        <strong class="quick-stat-value"><?= $soLichSapToi ?></strong>
                    </div>
                    <div class="quick-stat-item">
                        <span class="quick-stat-label">Mức hài lòng</span>
                        <strong class="quick-stat-value"><?= $tyLeHaiLong > 0 ? number_format($tyLeHaiLong, 0) . '%' : '—' ?></strong>
                    </div>
                    <?php if ($thanhVienInfo): ?>
                    <?php
                        $giamDuocPham = (float)($thanhVienInfo['PhanTramGiamDuocPham'] ?? 0);
                        $giamTongHD   = (float)($thanhVienInfo['PhanTramGiamTongHD'] ?? 0);
                    ?>
                    <?php if ($giamDuocPham > 0): ?>
                    <div class="quick-stat-item">
                        <span class="quick-stat-label">Ưu đãi dược phẩm</span>
                        <strong class="quick-stat-value text-success"><?= number_format($giamDuocPham, 0) ?>%</strong>
                    </div>
                    <?php elseif ($giamTongHD > 0): ?>
                    <div class="quick-stat-item">
                        <span class="quick-stat-label">Ưu đãi tổng HĐ</span>
                        <strong class="quick-stat-value text-success"><?= number_format($giamTongHD, 0) ?>%</strong>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-xl-3 col-lg-4" data-aos="fade-right">
                    <div class="profile-sidebar card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-body p-3 p-md-4">
                            <p class="text-uppercase small fw-semibold text-muted mb-3">Quản lý tài khoản</p>
                            <div class="nav flex-column nav-pills custom-nav-pills" id="v-pills-tab" role="tablist">
                                <button class="nav-link active text-start mb-2 rounded-3 fw-medium py-3" id="v-pills-membership-tab" data-bs-toggle="pill" data-bs-target="#v-pills-membership" type="button" role="tab" aria-controls="v-pills-membership" aria-selected="true">
                                    <i class="bi bi-award-fill me-2 fs-5"></i>Hạng Thành Viên
                                    <small class="d-block mt-1 opacity-75">Điểm thưởng và quyền lợi</small>
                                </button>
                                <button class="nav-link text-start mb-2 rounded-3 fw-medium py-3" id="v-pills-appointments-tab" data-bs-toggle="pill" data-bs-target="#v-pills-appointments" type="button" role="tab" aria-controls="v-pills-appointments" aria-selected="false">
                                    <i class="bi bi-calendar-check-fill me-2 fs-5"></i>Lịch Hẹn Khám
                                    <small class="d-block mt-1 opacity-75">Theo dõi lịch hẹn và lịch sử</small>
                                </button>
                                <button class="nav-link text-start rounded-3 fw-medium py-3" id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab" aria-controls="v-pills-profile" aria-selected="false">
                                    <i class="bi bi-person-lines-fill me-2 fs-5"></i>Chỉnh Sửa Hồ Sơ
                                    <small class="d-block mt-1 opacity-75">Cập nhật thông tin cá nhân</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-9 col-lg-8" data-aos="fade-left">
                    <div class="tab-content profile-tab-content" id="v-pills-tabContent">
                        <div class="tab-pane fade show active" id="v-pills-membership" role="tabpanel" aria-labelledby="v-pills-membership-tab">
                            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 profile-surface-card">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                    <div>
                                        <h3 class="fw-bold text-primary mb-2 font-heading">Hạng Thành Viên Của Bạn</h3>
                                        <?php if ($thanhVienInfo): ?>
                                        <p class="text-muted mb-0">Bạn đang ở mức <strong><?= htmlspecialchars($hangLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                            với <strong><?= number_format($diemTichLuy) ?> điểm</strong> tích lũy.</p>
                                        <?php else: ?>
                                        <p class="text-muted mb-0">Chưa có thông tin hạng thành viên. Hãy đặt lịch khám đầu tiên!</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($thanhVienInfo): ?>
                                    <span class="profile-pill" style="background:<?= htmlspecialchars($hangBgColor.'22', ENT_QUOTES,'UTF-8') ?>;color:<?= htmlspecialchars($hangBgColor, ENT_QUOTES,'UTF-8') ?>;border:1.5px solid <?= htmlspecialchars($hangBgColor.'66', ENT_QUOTES,'UTF-8') ?>">
                                        <i class="bi bi-award-fill me-2"></i><?= htmlspecialchars($hangLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($thanhVienInfo && $tenHangTiepLabel): ?>
                                <div class="membership-progress-card mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-semibold text-primary">Tiến độ nâng hạng</span>
                                        <span class="text-muted small">Còn <?= number_format($diemConLaiLabel) ?> điểm để lên <strong><?= htmlspecialchars($tenHangTiepLabel, ENT_QUOTES, 'UTF-8') ?></strong></span>
                                    </div>
                                    <div class="progress progress-modern" role="progressbar" aria-valuenow="<?= $progressVal ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" id="membershipProgressBar" data-progress="<?= $progressVal ?>"></div>
                                    </div>
                                </div>
                                <?php elseif ($thanhVienInfo): ?>
                                <div class="alert alert-success mb-4 py-2">
                                    <i class="bi bi-trophy-fill me-2"></i>Bạn đã đạt hạng thành viên cao nhất!
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($allHangs)): ?>
                                <?php
                                    $tierIcons     = ['bi-heart-fill','bi-award-fill','bi-star-fill','bi-gem','bi-trophy-fill'];
                                    $currentHangId = ($thanhVienInfo && isset($thanhVienInfo['MaHang']))
                                                   ? (int)$thanhVienInfo['MaHang'] : 0;
                                    $foundCurrent  = false;
                                ?>
                                <div class="membership-track" aria-label="Lộ trình hạng thành viên">
                                    <div class="membership-track-line"></div>
                                    <?php foreach ($allHangs as $idx => $hang): ?>
                                    <?php
                                        $isCurrent = ($currentHangId > 0 && (int)$hang['MaHang'] === $currentHangId);
                                        $isDone    = ($currentHangId > 0 && !$foundCurrent && !$isCurrent);
                                        $icon      = $tierIcons[$idx] ?? 'bi-circle-fill';
                                        if ($isCurrent) $foundCurrent = true;
                                    ?>
                                    <div class="tier-node <?= $isCurrent ? 'current' : ($isDone ? 'done' : '') ?>"
                                         title="<?= htmlspecialchars($hang['GhiChuKhuyenMai'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="tier-dot"><i class="bi <?= $icon ?>"></i></span>
                                        <span class="tier-label"><?= htmlspecialchars($hang['TenHang'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-muted text-center py-3"><i class="bi bi-info-circle me-2"></i>Không thể tải lộ trình hạng thành viên.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-appointments" role="tabpanel" aria-labelledby="v-pills-appointments-tab">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                                <h3 class="fw-bold text-primary font-heading mb-0">Lịch Hẹn Khám</h3>
                                <a href="index.php?route=home#booking" class="btn btn-primary rounded-pill px-4"><i class="bi bi-plus-lg me-1"></i>Đặt lịch mới</a>
                            </div>

                            <?php if (!empty($upcomingAppointments)): ?>
                            <div class="row g-3 mb-5">
                                <?php foreach ($upcomingAppointments as $appt):
                                    $trangThai = (int)$appt['TrangThai'];
                                    $statusClass = $trangThai === 1 ? 'status-confirmed' : 'status-pending';
                                    $statusLabel = $trangThai === 1 ? 'Đã xác nhận' : 'Chờ xác nhận';
                                    $thoiGian = new DateTime($appt['ThoiGianHen']);
                                ?>
                                <div class="col-md-6">
                                    <article class="appointment-card upcoming">
                                        <div class="appointment-head">
                                            <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                            <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= $thoiGian->format('H:i') ?></span>
                                        </div>
                                        <p class="mb-2 text-muted"><i class="bi bi-calendar3 text-primary me-2"></i><?= $thoiGian->format('d/m/Y') ?></p>
                                        <?php if (!empty($appt['TenBacSi'])): ?>
                                        <p class="mb-2 text-muted"><i class="bi bi-person-badge text-primary me-2"></i><?= htmlspecialchars($appt['TenBacSi'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($appt['GhiChu'])): ?>
                                        <p class="mb-3 text-muted small"><i class="bi bi-chat-text text-primary me-2"></i><?= htmlspecialchars($appt['GhiChu'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if ($trangThai === 0): ?>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-danger btn-sm rounded-pill px-3"
                                                    onclick="cancelAppointment(<?= (int)$appt['MaLichHen'] ?>)">Hủy lịch</button>
                                        </div>
                                        <?php endif; ?>
                                    </article>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5 mb-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size:3rem"></i>
                                <p class="text-muted mt-3">Bạn chưa có lịch hẹn nào sắp tới.</p>
                                <a href="index.php?route=home#booking" class="btn btn-primary rounded-pill px-5 mt-2">Đặt lịch ngay</a>
                            </div>
                            <?php endif; ?>

                            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 profile-surface-card">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                                    <h4 class="fw-bold text-primary font-heading mb-0">Lịch Sử Khám Bệnh</h4>
                                    <span class="text-muted small"><?= count($historyRecords) ?> lần khám gần nhất</span>
                                </div>

                                <?php if (!empty($historyRecords)): ?>
                                <div class="history-timeline">
                                    <?php
                                    $pkStatusMap = [
                                        0 => ['label'=>'Chờ xử lý',  'class'=>'status-pending'],
                                        1 => ['label'=>'Đã hoàn thành','class'=>'status-completed'],
                                        2 => ['label'=>'Đã hủy',     'class'=>'status-cancelled'],
                                    ];
                                    ?>
                                    <?php foreach ($historyRecords as $pk):
                                        $pkTT = (int)($pk['TrangThai'] ?? 0);
                                        $badge = $pkStatusMap[$pkTT] ?? ['label'=>'Đã khám','class'=>'status-completed'];
                                        $ngayKham = new DateTime($pk['NgayKham']);
                                    ?>
                                    <article class="history-item history-item-clickable" role="button" tabindex="0"
                                             onclick="viewPhieuKham(<?= (int)$pk['MaPhieuKham'] ?>)"
                                             title="Nhấn để xem chi tiết">
                                        <div class="history-dot"></div>
                                        <div class="history-content">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                <h6 class="fw-bold mb-0">
                                                    <?php if (!empty($pk['ChanDoan'])): ?>
                                                    <?php
                                                        $cd = $pk['ChanDoan'];
                                                        $cdDisplay = (function_exists('mb_strimwidth'))
                                                            ? mb_strimwidth($cd, 0, 60, '...')
                                                            : (strlen($cd) > 60 ? substr($cd, 0, 57) . '...' : $cd);
                                                    ?>
                                                    <?= htmlspecialchars($cdDisplay, ENT_QUOTES, 'UTF-8') ?>
                                                    <?php else: ?>
                                                    Phiếu khám #<?= (int)$pk['MaPhieuKham'] ?>
                                                    <?php endif; ?>
                                                </h6>
                                                <span class="status-badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                                            </div>
                                            <p class="text-muted mb-1"><i class="bi bi-calendar3 text-primary me-2"></i><?= $ngayKham->format('d/m/Y') ?></p>
                                            <?php if (!empty($pk['TenBacSi'])): ?>
                                            <p class="text-muted mb-0"><i class="bi bi-person-badge text-primary me-2"></i><?= htmlspecialchars($pk['TenBacSi'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                            <p class="text-primary small mt-2 mb-0 view-detail-hint"><i class="bi bi-eye me-1"></i>Xem chi tiết</p>
                                        </div>
                                    </article>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-clipboard-x" style="font-size:2.5rem"></i>
                                    <p class="mt-2">Chưa có lịch sử khám bệnh.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-profile" role="tabpanel" aria-labelledby="v-pills-profile-tab">
                            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 profile-surface-card">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                    <div>
                                        <h3 class="fw-bold text-primary mb-2 font-heading">Chỉnh Sửa Thông Tin Cá Nhân</h3>
                                        <p class="text-muted mb-0">Cập nhật thông tin để nhận nhắc lịch, ưu đãi và chăm sóc phù hợp từ <?= $tenPK ?>.</p>
                                    </div>
                                    <span class="profile-pill"><i class="bi bi-shield-check me-2"></i>Bảo mật dữ liệu</span>
                                </div>

                                <div id="profileFeedback" class="alert d-none" role="alert"></div>

                                <form id="profileForm" novalidate>
                                    <div class="profile-form-section mb-4">
                                        <h6 class="section-subtitle">Ảnh đại diện</h6>
                                        <div class="d-flex align-items-center gap-4 flex-wrap">
                                            <img src="<?= htmlspecialchars($avatarSrcSm, ENT_QUOTES, 'UTF-8') ?>" id="avatarPreview" alt="Avatar" class="profile-avatar-edit">
                                            <div>
                                                <label for="avatarUpload" class="btn btn-outline-primary rounded-pill px-4 mb-2"><i class="bi bi-camera me-2"></i>Thay đổi ảnh đại diện</label>
                                                <input type="file" id="avatarUpload" name="avatar" class="d-none" accept="image/png,image/jpeg,image/webp">
                                                <p class="text-muted small mb-0">Định dạng JPG, PNG, WEBP. Kích thước tối đa 2MB.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-form-section mb-4">
                                        <h6 class="section-subtitle">Thông tin cá nhân</h6>
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <label class="form-label fw-medium text-muted" for="fullName">Họ và tên</label>
                                                <input type="text" id="fullName" name="fullname" class="form-control form-control-lg border-primary border-opacity-50" value="<?php echo $customerName; ?>" required>
                                                <div class="invalid-feedback">Vui lòng nhập họ tên hợp lệ (ít nhất 3 ký tự).</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-medium text-muted" for="email">Email</label>
                                                <input type="email" id="email" name="email" class="form-control form-control-lg border-primary border-opacity-50" value="<?php echo $customerEmail; ?>" required>
                                                <div class="invalid-feedback">Email không đúng định dạng.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-medium text-muted">Số điện thoại</label>
                                                <div class="input-group">
                                                    <input type="tel" class="form-control form-control-lg bg-light" value="<?php echo $customerPhone; ?>" readonly disabled>
                                                    <a href="index.php?route=forgot-password" class="btn btn-outline-secondary" title="Đổi số điện thoại qua quy trình xác minh">
                                                        <i class="bi bi-pencil me-1"></i>Đổi SĐT
                                                    </a>
                                                </div>
                                                <div class="form-text text-muted"><i class="bi bi-info-circle me-1"></i>Để thay đổi SĐT, vui lòng dùng chức năng "Đổi số điện thoại".</div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4 opacity-10">

                                    <div class="d-flex justify-content-end gap-3 flex-wrap">
                                        <button type="button" class="btn btn-light rounded-pill px-4" id="btnResetProfile">Khôi phục</button>
                                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold" id="btnSaveProfile"><i class="bi bi-save me-2"></i>Lưu thay đổi</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-5 bg-white border-top">
        <div class="container">
            <div class="row g-4 justify-content-between mb-4">
                <div class="col-lg-5">
                    <img src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/images/logo.png" alt="<?= $tenPK ?>" class="img-fluid mb-4" style="height: 45px;">
                    <?php if ($moTaPK): ?>
                    <p class="text-muted mb-4 pe-lg-4"><?= $moTaPK ?></p>
                    <?php else: ?>
                    <p class="text-muted mb-4 pe-lg-4"><?= $tenPK ?> tự hào là hệ thống phòng khám chuyên khoa da liễu hàng đầu, nơi kiến tạo và tôn vinh vẻ đẹp nguyên bản thông qua các giải pháp thẩm mỹ an toàn, cá nhân hóa và chuẩn y khoa.</p>
                    <?php endif; ?>
                    
                    <?php if ($diaChi): ?>
                    <div class="d-flex align-items-center mb-3 text-muted">
                        <i class="bi bi-geo-alt-fill text-primary fs-5 me-3"></i>
                        <span><?= $diaChi ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($sdt): ?>
                    <div class="d-flex align-items-center mb-3 text-muted">
                        <i class="bi bi-telephone-fill text-primary fs-5 me-3"></i>
                        <span>Hotline CSKH: <strong class="text-primary"><?= $sdt ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($emailPK): ?>
                    <div class="d-flex align-items-center text-muted">
                        <i class="bi bi-envelope-fill text-primary fs-5 me-3"></i>
                        <span>Email: <?= $emailPK ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-5">
                    <h5 class="text-primary fw-bold mb-4 font-heading">Thời gian hoạt động</h5>
                    <p class="text-muted mb-4">
                        <i class="bi bi-clock-fill text-primary me-2"></i> <?= $lichLV ?>: <strong><?= $gioMoDisplay ?> - <?= $gioDongDisplay ?></strong><br>
                        <span class="ms-4 small">(Phòng khám mở cửa xuyên suốt các ngày lễ)</span>
                    </p>
                    
                    <h5 class="text-primary fw-bold mb-3 font-heading mt-5">Kết nối với chúng tôi</h5>
                    <div class="d-flex gap-3 mb-4">
                        <a href="#" class="btn btn-outline-primary rounded-circle" style="width: 40px; height: 40px; padding: 6px;"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="btn btn-outline-primary rounded-circle" style="width: 40px; height: 40px; padding: 6px;"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="btn btn-outline-primary rounded-circle" style="width: 40px; height: 40px; padding: 6px;"><i class="bi bi-tiktok"></i></a>
                    </div>

                    <ul class="list-inline mb-0">
                        <li class="list-inline-item me-3"><a href="index.php?route=home#about" class="text-decoration-none text-muted hover-primary">Về <?= $tenPK ?></a></li>
                        <li class="list-inline-item me-3"><a href="#" class="text-decoration-none text-muted hover-primary">Chính sách bảo mật</a></li>
                        <li class="list-inline-item"><a href="#" class="text-decoration-none text-muted hover-primary">Điều khoản dịch vụ</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="text-muted opacity-25 my-4">
            
            <div class="text-center">
                <p class="mb-0 small text-muted">&copy; <?= date('Y') ?> <?= $tenPK ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- ═══ Modal: Chi tiết Phiếu Khám ═══ -->
    <div class="modal fade" id="modalPhieuKham" tabindex="-1" aria-labelledby="modalPhieuKhamLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-primary bg-opacity-10 border-0 rounded-top-4 px-4 py-3">
                    <h5 class="modal-title fw-bold text-primary" id="modalPhieuKhamLabel">
                        <i class="bi bi-clipboard2-pulse me-2"></i>Chi tiết Phiếu Khám
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body px-4 py-4" id="pkModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-3">Đang tải dữ liệu...</p>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0" id="pkModalFooter" style="display:none">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold" id="btnOpenReview" style="display:none"
                            onclick="openReviewModal()">
                        <i class="bi bi-star me-2"></i>Đánh giá dịch vụ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Modal: Đánh giá dịch vụ ═══ -->
    <div class="modal fade" id="modalDanhGia" tabindex="-1" aria-labelledby="modalDanhGiaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-warning bg-opacity-10 border-0 rounded-top-4 px-4 py-3">
                    <h5 class="modal-title fw-bold text-dark" id="modalDanhGiaLabel">
                        <i class="bi bi-star-fill text-warning me-2"></i>Đánh giá dịch vụ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <p class="text-muted mb-3" id="reviewPkTitle"></p>
                    <div class="text-center mb-4">
                        <div class="star-rating" id="starRating">
                            <i class="bi bi-star star-btn" data-value="1"></i>
                            <i class="bi bi-star star-btn" data-value="2"></i>
                            <i class="bi bi-star star-btn" data-value="3"></i>
                            <i class="bi bi-star star-btn" data-value="4"></i>
                            <i class="bi bi-star star-btn" data-value="5"></i>
                        </div>
                        <p class="small text-muted mt-2" id="starLabel">Chọn số sao đánh giá</p>
                    </div>
                    <div class="mb-3">
                        <label for="reviewComment" class="form-label fw-medium">Nhận xét <span class="text-muted fw-normal">(tùy chọn)</span></label>
                        <textarea id="reviewComment" class="form-control" rows="3" maxlength="500"
                                  placeholder="Chia sẻ trải nghiệm của bạn tại phòng khám..."></textarea>
                    </div>
                    <div id="reviewFeedback" class="alert d-none" role="alert"></div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold" id="btnSubmitReview" onclick="submitReview()">
                        <i class="bi bi-send me-2"></i>Gửi đánh giá
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/vendor/aos/aos.js"></script>
    
    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/js/script.js"></script>
    <script src="<?= defined('BASE_URL') ? BASE_URL : '/' ?>public/assets/js/profile.js"></script>
</body>
</html>
