<?php $activePage = 'lich-lam-viec'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ THỐNG KÊ CA ═══ -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-primary-soft"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="admin-stat-value" id="statTongCa">—</div>
                <div class="admin-stat-label">Tổng ca</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-green-soft"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="admin-stat-value" id="statDaDiemDanh">—</div>
                <div class="admin-stat-label">Đã điểm danh</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-danger-soft"><i class="bi bi-x-circle"></i></div>
            <div>
                <div class="admin-stat-value" id="statCaVang">—</div>
                <div class="admin-stat-label">Ca vắng</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ BẢNG LỊCH ═══ -->
<div class="admin-card">
    <div class="admin-card-head">
        <h2 class="admin-card-title"><i class="bi bi-calendar3"></i>Lịch phân công ca</h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <select id="cboXemTheo" class="form-select form-select-sm" style="width:auto">
                <option value="thang" selected>Xem theo tháng</option>
                <option value="ngay">Xem theo ngày</option>
            </select>
            <!-- Điều hướng theo tháng -->
            <div id="navThang" class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="btnPrevMonth"><i class="bi bi-chevron-left"></i></button>
                <span class="fw-semibold" id="labelThang"></span>
                <button class="btn btn-outline-secondary btn-sm" id="btnNextMonth"><i class="bi bi-chevron-right"></i></button>
            </div>
            <!-- Điều hướng theo ngày -->
            <div id="navNgay" class="d-flex align-items-center gap-2" style="display:none!important">
                <button class="btn btn-outline-secondary btn-sm" id="btnPrevDay"><i class="bi bi-chevron-left"></i></button>
                <input type="date" id="inputNgay" class="form-control form-control-sm" style="width:auto">
                <button class="btn btn-outline-secondary btn-sm" id="btnNextDay"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
    <div class="admin-card-body-flush">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Thứ</th>
                    <th>Ca làm</th>
                    <th>Giờ</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody id="tableLichCa">
                <tr><td colspan="5" class="text-center text-muted py-4">Đang tải...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
<script>
(function() {
    var now = new Date();
    var currentMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    var currentDay = now.toISOString().slice(0, 10);
    var mode = 'thang';

    var thuVN = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];

    var cbo = document.getElementById('cboXemTheo');
    var navThang = document.getElementById('navThang');
    var navNgay = document.getElementById('navNgay');
    var inputNgay = document.getElementById('inputNgay');

    inputNgay.value = currentDay;

    cbo.addEventListener('change', function() {
        mode = this.value;
        if (mode === 'thang') {
            navThang.style.display = 'flex';
            navNgay.style.cssText = 'display:none!important';
            loadTheoThang(currentMonth);
        } else {
            navThang.style.display = 'none';
            navNgay.style.cssText = 'display:flex!important';
            loadTheoNgay(currentDay);
        }
    });

    function renderTable(lichCa) {
        var tbody = document.getElementById('tableLichCa');
        if (!lichCa || lichCa.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Không có ca nào</td></tr>';
            return;
        }
        var html = '';
        lichCa.forEach(function(ca) {
            var d = new Date(ca.NgayLamViec);
            var thu = thuVN[d.getDay()];
            var ngay = d.toLocaleDateString('vi-VN');
            var badge = ca.TrangThaiDiemDanh == 2 ? '<span class="admin-badge admin-badge-active"><i class="bi bi-check-circle-fill"></i>Đã điểm danh</span>' :
                        ca.TrangThaiDiemDanh == 3 ? '<span class="admin-badge admin-badge-locked"><i class="bi bi-x-circle-fill"></i>Vắng</span>' :
                        '<span class="admin-badge" style="background:rgba(255,193,7,.12);color:#856404"><i class="bi bi-clock"></i>Chưa điểm danh</span>';
            html += '<tr><td>' + ngay + '</td><td>' + thu + '</td><td><strong>' + ca.TenCa + '</strong></td>' +
                '<td>' + ca.GioBatDau.substring(0,5) + ' - ' + ca.GioKetThuc.substring(0,5) + '</td><td>' + badge + '</td></tr>';
        });
        tbody.innerHTML = html;
    }

    function updateStats(thongKe) {
        document.getElementById('statTongCa').textContent = thongKe.tongCa;
        document.getElementById('statDaDiemDanh').textContent = thongKe.daDiemDanh;
        document.getElementById('statCaVang').textContent = thongKe.caVang;
    }

    function loadTheoThang(thang) {
        currentMonth = thang;
        document.getElementById('labelThang').textContent = thang;
        letanFetch('lich-lam-viec?thang=' + thang).then(function(res) {
            if (res.status !== 200) return;
            updateStats(res.data.thongKe);
            renderTable(res.data.lichCa);
        });
    }

    function loadTheoNgay(ngay) {
        currentDay = ngay;
        inputNgay.value = ngay;
        letanFetch('lich-lam-viec?ngay=' + ngay).then(function(res) {
            if (res.status !== 200) return;
            updateStats(res.data.thongKe);
            renderTable(res.data.lichCa);
        });
    }

    document.getElementById('btnPrevMonth').addEventListener('click', function() {
        var p = currentMonth.split('-'), y = parseInt(p[0]), m = parseInt(p[1]) - 1;
        if (m < 1) { m = 12; y--; }
        loadTheoThang(y + '-' + String(m).padStart(2, '0'));
    });
    document.getElementById('btnNextMonth').addEventListener('click', function() {
        var p = currentMonth.split('-'), y = parseInt(p[0]), m = parseInt(p[1]) + 1;
        if (m > 12) { m = 1; y++; }
        loadTheoThang(y + '-' + String(m).padStart(2, '0'));
    });

    function shiftDay(offset) {
        var d = new Date(currentDay);
        d.setDate(d.getDate() + offset);
        loadTheoNgay(d.toISOString().slice(0, 10));
    }
    document.getElementById('btnPrevDay').addEventListener('click', function() { shiftDay(-1); });
    document.getElementById('btnNextDay').addEventListener('click', function() { shiftDay(1); });
    inputNgay.addEventListener('change', function() { loadTheoNgay(this.value); });

    loadTheoThang(currentMonth);
})();
</script>
</body>
</html>
