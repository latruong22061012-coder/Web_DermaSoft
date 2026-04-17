<?php $activePage = 'dashboard'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ STAT CARDS ═══ -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-gold-soft"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="admin-stat-value" id="statChoXN">—</div>
                <div class="admin-stat-label">Chờ xác nhận</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-green-soft"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="admin-stat-value" id="statDaXN">—</div>
                <div class="admin-stat-label">Đã xác nhận</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-primary-soft"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="admin-stat-value" id="statTongHN">—</div>
                <div class="admin-stat-label">Tổng hẹn hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-danger-soft"><i class="bi bi-x-circle"></i></div>
            <div>
                <div class="admin-stat-value" id="statDaHuy">—</div>
                <div class="admin-stat-label">Hủy (tháng này)</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ CA LÀM HÔM NAY + BIỂU ĐỒ LH ═══ -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-clock"></i>Ca làm hôm nay</h2>
            </div>
            <div class="admin-card-body" id="caHomNayContent">
                <div class="text-center text-muted py-4">Đang tải...</div>
            </div>
        </div>

        <!-- BN theo bác sĩ -->
        <div class="admin-card mt-3">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-person-check"></i>BN theo Bác sĩ hôm nay</h2>
            </div>
            <div class="admin-card-body" id="bnTheoBacSiContent">
                <div class="text-center text-muted py-3">Đang tải...</div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-bar-chart"></i>Lịch hẹn theo tháng</h2>
            </div>
            <div class="admin-card-body">
                <div class="admin-chart-container">
                    <canvas id="chartLHTheoThang"></canvas>
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-pie-chart"></i>Phân bố trạng thái tháng này</h2>
            </div>
            <div class="admin-card-body">
                <div class="admin-chart-container" style="height:200px">
                    <canvas id="chartPhanBoTT"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    // ═══ Stats ═══
    letanFetch('stats').then(function(res) {
        if (res.status !== 200) return;
        var d = res.data;
        document.getElementById('statChoXN').textContent = formatNumber(d.choXacNhan);
        document.getElementById('statDaXN').textContent = formatNumber(d.daXacNhan);
        document.getElementById('statTongHN').textContent = formatNumber(d.tongHomNay);
        document.getElementById('statDaHuy').textContent = formatNumber(d.daHuy);

        // Ca hôm nay
        var caHtml = '';
        if (d.caHomNay && d.caHomNay.length > 0) {
            d.caHomNay.forEach(function(ca) {
                var statusClass = ca.TrangThaiDiemDanh == 2 ? 'bg-success' : ca.TrangThaiDiemDanh == 3 ? 'bg-danger' : 'bg-warning';
                var statusText = ca.TrangThaiDiemDanh == 2 ? 'Đã điểm danh' : ca.TrangThaiDiemDanh == 3 ? 'Vắng' : 'Chưa điểm danh';
                caHtml += '<div class="d-flex align-items-center justify-content-between p-3 mb-2 rounded-3" style="background:#f8faf9;border:1px solid var(--admin-border)">' +
                    '<div><strong>' + ca.TenCa + '</strong><br><small class="text-muted">' +
                    ca.GioBatDau.substring(0,5) + ' - ' + ca.GioKetThuc.substring(0,5) + '</small></div>' +
                    '<span class="badge ' + statusClass + ' rounded-pill">' + statusText + '</span></div>';
            });
        } else {
            caHtml = '<div class="text-center text-muted py-3"><i class="bi bi-emoji-smile d-block fs-3 mb-2"></i>Hôm nay không có ca</div>';
        }
        document.getElementById('caHomNayContent').innerHTML = caHtml;
    });

    // ═══ Charts ═══
    letanFetch('thong-ke-lh').then(function(res) {
        if (res.status !== 200) return;
        var data = res.data;

        // Bar chart - LH theo tháng
        var lhData = data.lhTheoThang || [];
        new Chart(document.getElementById('chartLHTheoThang'), {
            type: 'bar',
            data: {
                labels: lhData.map(function(i) { return i.thang; }),
                datasets: [{
                    label: 'Lịch hẹn',
                    data: lhData.map(function(i) { return i.soLuong; }),
                    backgroundColor: 'rgba(15,92,77,0.7)',
                    borderRadius: 6,
                    barThickness: 28
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Pie chart - Phân bố trạng thái
        var ttData = data.phanBoTrangThai || [];
        var ttLabels = {0: 'Chờ xác nhận', 1: 'Đã xác nhận', 2: 'Hoàn thành', 3: 'Đã hủy'};
        var ttColors = {0: '#ffc107', 1: '#0dcaf0', 2: '#198754', 3: '#dc3545'};
        if (ttData.length > 0) {
            new Chart(document.getElementById('chartPhanBoTT'), {
                type: 'doughnut',
                data: {
                    labels: ttData.map(function(t) { return ttLabels[t.TrangThai] || 'Khác'; }),
                    datasets: [{
                        data: ttData.map(function(t) { return t.soLuong; }),
                        backgroundColor: ttData.map(function(t) { return ttColors[t.TrangThai] || '#6c757d'; }),
                        borderWidth: 2, borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } } }
                }
            });
        }

        // BN theo bác sĩ
        var bsList = data.bnTheoBacSi || [];
        var bsHtml = '';
        if (bsList.length > 0) {
            bsList.forEach(function(bs) {
                bsHtml += '<div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded" style="background:#f8faf9">' +
                    '<span><i class="bi bi-person-badge me-2 text-primary"></i>' + bs.TenBacSi + '</span>' +
                    '<span class="badge bg-primary rounded-pill">' + bs.soLuong + ' BN</span></div>';
            });
        } else {
            bsHtml = '<div class="text-center text-muted py-2">Chưa có dữ liệu</div>';
        }
        document.getElementById('bnTheoBacSiContent').innerHTML = bsHtml;
    });
})();
</script>
</body>
</html>
