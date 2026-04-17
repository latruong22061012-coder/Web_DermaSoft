<?php $activePage = 'dashboard'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ STAT CARDS ═══ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-gold-soft"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="admin-stat-value" id="statChoKham">—</div>
                <div class="admin-stat-label">Chờ khám hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-green-soft"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="admin-stat-value" id="statDaKham">—</div>
                <div class="admin-stat-label">Đã khám hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:rgba(13,110,253,.12);color:#0d6efd"><i class="bi bi-calendar2-check"></i></div>
            <div>
                <div class="admin-stat-value" id="statLichYeuCau">—</div>
                <div class="admin-stat-label">Lịch yêu cầu hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-primary-soft"><i class="bi bi-people"></i></div>
            <div>
                <div class="admin-stat-value" id="statBNThang">—</div>
                <div class="admin-stat-label">BN tháng này</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-danger-soft"><i class="bi bi-star"></i></div>
            <div>
                <div class="admin-stat-value" id="statDiemTB">—</div>
                <div class="admin-stat-label">Điểm đánh giá TB</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ CA LÀM HÔM NAY + BN THEO THÁNG ═══ -->
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
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-bar-chart"></i>Bệnh nhân theo tháng</h2>
            </div>
            <div class="admin-card-body">
                <div class="admin-chart-container">
                    <canvas id="chartBNTheoThang"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ ĐÁNH GIÁ GẦN ĐÂY ═══ -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-chat-quote"></i>Đánh giá gần đây</h2>
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
                    <tbody id="tableDanhGia">
                        <tr><td colspan="4" class="text-center text-muted py-4">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="bi bi-pie-chart"></i>Phân bố điểm</h2>
            </div>
            <div class="admin-card-body">
                <div class="admin-chart-container">
                    <canvas id="chartPhanBoDiem"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    // ═══ Load stats ═══
    bacsiFetch('stats').then(function(res) {
        if (res.status !== 200) return;
        var d = res.data;
        document.getElementById('statChoKham').textContent = formatNumber(d.choKham);
        document.getElementById('statDaKham').textContent = formatNumber(d.daKham);
        document.getElementById('statLichYeuCau').textContent = formatNumber(d.lichYeuCau);
        document.getElementById('statBNThang').textContent = formatNumber(d.bnThangNay);
        document.getElementById('statDiemTB').textContent = d.diemTB > 0 ? d.diemTB + '/5' : '—';

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

    // ═══ Load BN theo tháng ═══
    bacsiFetch('thong-ke-bn').then(function(res) {
        if (res.status !== 200) return;
        var data = res.data.bnTheoThang || [];
        var labels = data.map(function(i) { return i.thang; });
        var values = data.map(function(i) { return i.soLuong; });

        new Chart(document.getElementById('chartBNTheoThang'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Bệnh nhân',
                    data: values,
                    backgroundColor: 'rgba(15,92,77,0.7)',
                    borderRadius: 6,
                    barThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    });

    // ═══ Load đánh giá ═══
    bacsiFetch('danh-gia').then(function(res) {
        if (res.status !== 200) return;
        var dg = res.data.danhGia || [];
        var phanBo = res.data.phanBoDiem || [];

        // Table - 5 mới nhất
        var tbody = document.getElementById('tableDanhGia');
        if (dg.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Chưa có đánh giá</td></tr>';
        } else {
            var html = '';
            dg.slice(0, 5).forEach(function(r) {
                var ngay = r.NgayDanhGia ? new Date(r.NgayDanhGia).toLocaleDateString('vi-VN') : '';
                html += '<tr>' +
                    '<td><strong>' + (r.TenBenhNhan || '') + '</strong></td>' +
                    '<td><span class="admin-stars">' + renderStars(r.DiemDanh) + '</span></td>' +
                    '<td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (r.NhanXet || '—') + '</td>' +
                    '<td class="text-muted">' + ngay + '</td></tr>';
            });
            tbody.innerHTML = html;
        }

        // Pie chart phân bố điểm
        if (phanBo.length > 0) {
            var pieLabels = phanBo.map(function(p) { return p.DiemDanh + ' sao'; });
            var pieValues = phanBo.map(function(p) { return p.soLuong; });
            var pieColors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#0f5c4d'];
            new Chart(document.getElementById('chartPhanBoDiem'), {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieValues,
                        backgroundColor: phanBo.map(function(p) { return pieColors[p.DiemDanh - 1] || '#6c757d'; }),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } } }
                }
            });
        }
    });
})();
</script>
</body>
</html>
