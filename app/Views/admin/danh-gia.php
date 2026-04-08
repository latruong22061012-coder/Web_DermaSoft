<?php $activePage = 'danh-gia'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ STATS ROW ═══ -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-gold-soft"><i class="bi bi-star-fill"></i></div>
            <div>
                <div class="admin-stat-value" id="statAvg">—</div>
                <div class="admin-stat-label">Điểm trung bình</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-icon bg-primary-soft"><i class="bi bi-chat-square-text"></i></div>
            <div>
                <div class="admin-stat-value" id="statTotal">—</div>
                <div class="admin-stat-label">Tổng đánh giá</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-card h-100">
            <div class="admin-card-body py-2 px-3">
                <div id="starsDistribution" class="small"></div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ FILTER ═══ -->
<div class="d-flex align-items-center flex-wrap gap-2 mb-3">
    <span class="text-muted small">Lọc theo điểm:</span>
    <div class="btn-group btn-group-sm" role="group">
        <button class="btn btn-outline-secondary rounded-pill me-1 active" data-diem="" onclick="filterDiem(this, '')">Tất cả</button>
        <button class="btn btn-outline-secondary rounded-pill me-1" data-diem="5" onclick="filterDiem(this, 5)">5 ★</button>
        <button class="btn btn-outline-secondary rounded-pill me-1" data-diem="4" onclick="filterDiem(this, 4)">4 ★</button>
        <button class="btn btn-outline-secondary rounded-pill me-1" data-diem="3" onclick="filterDiem(this, 3)">3 ★</button>
        <button class="btn btn-outline-secondary rounded-pill me-1" data-diem="2" onclick="filterDiem(this, 2)">2 ★</button>
        <button class="btn btn-outline-secondary rounded-pill" data-diem="1" onclick="filterDiem(this, 1)">1 ★</button>
    </div>
</div>

<!-- ═══ TABLE ═══ -->
<div class="admin-card">
    <div class="admin-card-body-flush">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bệnh nhân</th>
                        <th>SĐT</th>
                        <th>Điểm</th>
                        <th>Nhận xét</th>
                        <th>Ngày</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="admin-pagination" id="paginationBar"></div>
</div>
<?php require __DIR__ . '/layout-footer.php'; ?>
<script>
(function() {
    var currentPage = 1;
    var currentDiem = '';

    // Tải thống kê
    function loadStats() {
        adminFetch('danh-gia-stats').then(function(res) {
            if (res.status !== 200 || !res.data) return;
            var d = res.data;
            document.getElementById('statAvg').textContent = d.diemTrungBinh > 0 ? d.diemTrungBinh + ' ★' : 'Chưa có';
            document.getElementById('statTotal').textContent = formatNumber(d.tongDanhGia);

            // Phân bố sao
            var distHtml = '';
            var maxCount = Math.max.apply(null, d.phanBo.map(function(p) { return p.SoLuong; })) || 1;
            for (var s = 5; s >= 1; s--) {
                var found = d.phanBo.find(function(p) { return parseInt(p.DiemDanh) === s; });
                var count = found ? found.SoLuong : 0;
                var pct = d.tongDanhGia > 0 ? (count / d.tongDanhGia * 100).toFixed(0) : 0;
                distHtml += '<div class="d-flex align-items-center gap-2 mb-1">' +
                    '<span class="text-nowrap" style="width:30px">' + s + ' <i class="bi bi-star-fill text-warning" style="font-size:.65rem"></i></span>' +
                    '<div class="flex-grow-1 bg-light rounded-pill" style="height:8px;overflow:hidden">' +
                        '<div class="rounded-pill h-100" style="width:' + pct + '%;background:var(--color-luxe-gold)"></div>' +
                    '</div>' +
                    '<span class="text-muted" style="width:28px;text-align:right">' + count + '</span>' +
                '</div>';
            }
            document.getElementById('starsDistribution').innerHTML = distHtml;
        });
    }

    function loadData(page, diem) {
        page = page || 1;
        var url = 'danh-gia?page=' + page + '&limit=15';
        if (diem) url += '&diem=' + diem;

        adminFetch(url).then(function(res) {
            if (res.status !== 200) {
                adminToast(res.message || 'Lỗi', 'danger');
                return;
            }
            renderTable(res.data.list, res.data.pagination);
        }).catch(function() {
            adminToast('Lỗi kết nối server', 'danger');
        });
    }

    function renderTable(list, pag) {
        var tbody = document.getElementById('tableBody');
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="admin-empty"><i class="bi bi-chat-square-text"></i><p>Không có đánh giá nào</p></td></tr>';
            document.getElementById('paginationBar').innerHTML = '';
            return;
        }

        var startIdx = (pag.page - 1) * pag.limit;
        var html = '';
        list.forEach(function(dg, i) {
            var ngay = dg.NgayDanhGia ? new Date(dg.NgayDanhGia).toLocaleDateString('vi-VN') : '—';
            var nhanXet = dg.NhanXet || '<span class="text-muted fst-italic">Không có nhận xét</span>';

            html += '<tr>' +
                '<td class="text-muted">' + (startIdx + i + 1) + '</td>' +
                '<td class="fw-medium">' + (dg.TenBenhNhan || '—') + '</td>' +
                '<td class="small">' + (dg.SoDienThoai || '—') + '</td>' +
                '<td><span class="admin-stars">' + renderStars(dg.DiemDanh) + '</span></td>' +
                '<td class="small" style="max-width:280px">' + nhanXet + '</td>' +
                '<td class="text-muted small text-nowrap">' + ngay + '</td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Xóa" onclick="deleteDG(' + dg.MaDanhGia + ')">' +
                        '<i class="bi bi-trash"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
        });
        tbody.innerHTML = html;
        renderPagination(pag);
    }

    function renderPagination(pag) {
        var container = document.getElementById('paginationBar');
        if (pag.totalPages <= 1) { container.innerHTML = ''; return; }

        var from = (pag.page - 1) * pag.limit + 1;
        var to = Math.min(pag.page * pag.limit, pag.total);

        var html = '<div class="admin-pagination-info">Hiển thị ' + from + '–' + to + ' / ' + pag.total + '</div>';
        html += '<div class="admin-pagination-btns">';
        if (pag.page > 1) html += '<button class="btn btn-outline-secondary" onclick="goPage(' + (pag.page - 1) + ')"><i class="bi bi-chevron-left"></i></button>';
        for (var p = 1; p <= pag.totalPages; p++) {
            if (p === pag.page) html += '<button class="btn btn-primary">' + p + '</button>';
            else if (p <= 2 || p > pag.totalPages - 1 || Math.abs(p - pag.page) <= 1) html += '<button class="btn btn-outline-secondary" onclick="goPage(' + p + ')">' + p + '</button>';
            else if (p === 3 || p === pag.totalPages - 1) html += '<button class="btn btn-outline-secondary" disabled>…</button>';
        }
        if (pag.page < pag.totalPages) html += '<button class="btn btn-outline-secondary" onclick="goPage(' + (pag.page + 1) + ')"><i class="bi bi-chevron-right"></i></button>';
        html += '</div>';
        container.innerHTML = html;
    }

    window.goPage = function(p) {
        currentPage = p;
        loadData(p, currentDiem);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.filterDiem = function(btn, diem) {
        // Toggle active
        document.querySelectorAll('[data-diem]').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        currentDiem = diem;
        currentPage = 1;
        loadData(1, diem);
    };

    window.deleteDG = function(id) {
        if (!confirm('Xóa đánh giá này? Hành động không thể hoàn tác.')) return;
        adminFetch('danh-gia/' + id, { method: 'DELETE' }).then(function(res) {
            if (res.status === 200) {
                adminToast(res.message, 'success');
                loadData(currentPage, currentDiem);
                loadStats();
            } else {
                adminToast(res.message || 'Lỗi', 'danger');
            }
        });
    };

    loadStats();
    loadData(1);
})();
</script>
