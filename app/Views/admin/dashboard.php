<?php $activePage = 'dashboard'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ STAT CARDS ═══ -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-primary-soft"><i class="bi bi-people"></i></div>
            <div>
                <div class="admin-stat-value" id="statBenhNhan">—</div>
                <div class="admin-stat-label">Tổng bệnh nhân</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-gold-soft"><i class="bi bi-person-badge"></i></div>
            <div>
                <div class="admin-stat-value" id="statThanhVien">—</div>
                <div class="admin-stat-label">Thành viên</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-green-soft"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="admin-stat-value" id="statLichHen">—</div>
                <div class="admin-stat-label">Lịch hẹn hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-danger-soft"><i class="bi bi-star"></i></div>
            <div>
                <div class="admin-stat-value" id="statDanhGia">—</div>
                <div class="admin-stat-label">Đánh giá trung bình</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ CHARTS ROW ═══ -->
<div class="row g-3 mb-4">
    <!-- Bệnh nhân mới 6 tháng -->
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-bar-chart"></i>Bệnh nhân mới theo tháng</h2>
            </div>
            <div class="admin-card-body">
                <div class="admin-chart-container">
                    <canvas id="chartBenhNhanMoi"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- Phân bố hạng -->
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-pie-chart"></i>Phân bố hạng thành viên</h2>
            </div>
            <div class="admin-card-body">
                <div class="admin-chart-container">
                    <canvas id="chartHangPhanBo"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ ĐÁNH GIÁ MỚI + DOANH THU ═══ -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-chat-quote"></i>Đánh giá mới nhất</h2>
            </div>
            <div class="admin-card-body-flush">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Bệnh nhân</th>
                            <th>Điểm</th>
                            <th>Nhận xét</th>
                            <th>Ngày</th>
                        </tr>
                    </thead>
                    <tbody id="tableDanhGiaMoi">
                        <tr><td colspan="4" class="text-center text-muted py-4">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-cash-coin"></i>Doanh thu tháng</h2>
            </div>
            <div class="admin-card-body text-center py-4">
                <div class="admin-stat-value mb-1" id="statDoanhThu" style="font-size:1.8rem">—</div>
                <div class="text-muted small">Tháng <?= date('m/Y') ?></div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-lightning"></i>Truy cập nhanh</h2>
            </div>
            <div class="admin-card-body">
                <div class="d-grid gap-2">
                    <a href="<?= $baseUrl ?>index.php?route=admin/benh-nhan" class="btn btn-outline-primary btn-sm rounded-pill">
                        <i class="bi bi-people me-1"></i>Quản lý bệnh nhân
                    </a>
                    <a href="<?= $baseUrl ?>index.php?route=admin/thanh-vien" class="btn btn-outline-primary btn-sm rounded-pill">
                        <i class="bi bi-person-badge me-1"></i>Quản lý thành viên
                    </a>
                    <a href="<?= $baseUrl ?>index.php?route=admin/hang-thanh-vien" class="btn btn-outline-primary btn-sm rounded-pill">
                        <i class="bi bi-gem me-1"></i>Cấu hình hạng
                    </a>
                    <a href="<?= $baseUrl ?>index.php?route=admin/danh-gia" class="btn btn-outline-primary btn-sm rounded-pill">
                        <i class="bi bi-star me-1"></i>Xem đánh giá
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
(function() {
    // Tải dữ liệu Dashboard
    adminFetch('stats').then(function(res) {
        if (res.status !== 200 || !res.data) {
            adminToast('Không thể tải dữ liệu Dashboard', 'danger');
            return;
        }
        var d = res.data;

        // Cập nhật stat cards
        document.getElementById('statBenhNhan').textContent = formatNumber(d.tongBenhNhan);
        document.getElementById('statThanhVien').textContent = formatNumber(d.tongThanhVien);
        document.getElementById('statLichHen').textContent = formatNumber(d.lichHenHomNay);
        document.getElementById('statDanhGia').textContent = d.diemDanhGiaTB > 0 ? d.diemDanhGiaTB + ' ★' : 'Chưa có';
        document.getElementById('statDoanhThu').textContent = formatCurrency(d.doanhThuThang);

        // Chart: Bệnh nhân mới
        renderBarChart(d.bnMoi6Thang);

        // Chart: Phân bố hạng
        renderDonutChart(d.hangPhanBo);

        // Bảng đánh giá mới
        renderDanhGia(d.danhGiaMoi);
    }).catch(function() {
        adminToast('Lỗi kết nối server', 'danger');
    });

    function renderBarChart(data) {
        var ctx = document.getElementById('chartBenhNhanMoi');
        if (!ctx) return;
        var labels = data.map(function(r) { return r.thang; });
        var values = data.map(function(r) { return r.soLuong; });

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Bệnh nhân mới',
                    data: values,
                    backgroundColor: 'rgba(15,92,77,.55)',
                    borderColor: 'rgba(15,92,77,.9)',
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 48,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderDonutChart(data) {
        var ctx = document.getElementById('chartHangPhanBo');
        if (!ctx) return;
        var labels = data.map(function(r) { return r.TenHang; });
        var values = data.map(function(r) { return r.soLuong; });
        var colors = data.map(function(r) { return r.MauHangHex || '#6c757d'; });

        if (values.every(function(v) { return v === 0; })) {
            ctx.parentNode.innerHTML = '<div class="admin-empty"><i class="bi bi-pie-chart"></i><p>Chưa có dữ liệu thành viên</p></div>';
            return;
        }

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } }
                }
            }
        });
    }

    function renderDanhGia(data) {
        var tbody = document.getElementById('tableDanhGiaMoi');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="admin-empty"><i class="bi bi-chat-square-text"></i><p>Chưa có đánh giá nào</p></td></tr>';
            return;
        }

        var html = '';
        data.forEach(function(dg) {
            var ngay = dg.NgayDanhGia ? new Date(dg.NgayDanhGia).toLocaleDateString('vi-VN') : '—';
            var nhanXet = dg.NhanXet || '<span class="text-muted fst-italic">Không có nhận xét</span>';
            if (typeof nhanXet === 'string' && nhanXet.length > 80) {
                nhanXet = nhanXet.substring(0, 80) + '...';
            }
            html += '<tr>' +
                '<td class="fw-medium">' + (dg.TenBenhNhan || '—') + '</td>' +
                '<td><span class="admin-stars">' + renderStars(dg.DiemDanh) + '</span></td>' +
                '<td class="text-muted small">' + nhanXet + '</td>' +
                '<td class="text-muted small text-nowrap">' + ngay + '</td>' +
            '</tr>';
        });
        tbody.innerHTML = html;
    }
})();
</script>
