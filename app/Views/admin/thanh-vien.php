<?php $activePage = 'thanh-vien'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ TOOLBAR ═══ -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="admin-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="searchInput" placeholder="Tìm theo tên, SĐT...">
    </div>
    <div class="text-muted small" id="totalInfo"></div>
</div>

<!-- ═══ TABLE ═══ -->
<div class="admin-card">
    <div class="admin-card-body-flush">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Họ tên</th>
                        <th>SĐT</th>
                        <th>Hạng</th>
                        <th>Điểm tích lũy</th>
                        <th>Điểm thưởng</th>
                        <th>Tỷ lệ hài lòng</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="admin-pagination" id="paginationBar"></div>
</div>

<!-- ═══ MODAL SỬA ĐIỂM ═══ -->
<div class="modal fade admin-modal" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Cập nhật điểm thành viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="mb-3">
                    <label class="form-label fw-medium">Tên thành viên</label>
                    <input type="text" class="form-control" id="editName" readonly>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-medium">Điểm tích lũy</label>
                        <input type="number" class="form-control" id="editDiemTL" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Điểm thưởng</label>
                        <input type="number" class="form-control" id="editDiemThuong" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary rounded-pill px-3" id="btnSaveEdit">
                    <i class="bi bi-check-lg me-1"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>

<script>
(function() {
    var currentPage = 1;
    var searchTimer = null;

    function loadData(page, query) {
        page = page || 1;
        query = query || '';
        var url = 'thanh-vien?page=' + page + '&limit=15';
        if (query) url += '&q=' + encodeURIComponent(query);

        adminFetch(url).then(function(res) {
            if (res.status !== 200) {
                adminToast(res.message || 'Lỗi tải dữ liệu', 'danger');
                return;
            }
            renderTable(res.data.list, res.data.pagination);
        }).catch(function() {
            adminToast('Lỗi kết nối server', 'danger');
        });
    }

    function renderTable(list, pag) {
        var tbody = document.getElementById('tableBody');
        var info = document.getElementById('totalInfo');
        info.textContent = 'Tổng: ' + formatNumber(pag.total) + ' thành viên';

        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="admin-empty"><i class="bi bi-person-badge"></i><p>Không tìm thấy thành viên nào</p></td></tr>';
            document.getElementById('paginationBar').innerHTML = '';
            return;
        }

        var startIdx = (pag.page - 1) * pag.limit;
        var html = '';
        list.forEach(function(tv, i) {
            var hangHtml = tv.TenHang
                ? '<span class="admin-badge" style="background:' + tv.MauHangHex + '15;color:' + tv.MauHangHex + '">' +
                  '<span class="admin-color-dot" style="background:' + tv.MauHangHex + '"></span>' + tv.TenHang + '</span>'
                : '<span class="text-muted small">—</span>';

            var tyLe = tv.TyLeHaiLong ? (parseFloat(tv.TyLeHaiLong).toFixed(1) + '%') : '—';

            html += '<tr>' +
                '<td class="text-muted">' + (startIdx + i + 1) + '</td>' +
                '<td class="fw-medium">' + (tv.HoTen || '—') + '</td>' +
                '<td>' + (tv.SoDienThoai || '—') + '</td>' +
                '<td>' + hangHtml + '</td>' +
                '<td class="fw-medium">' + formatNumber(tv.DiemTichLuy || 0) + '</td>' +
                '<td>' + formatNumber(tv.DiemThuong || 0) + '</td>' +
                '<td>' + tyLe + '</td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Sửa điểm" onclick="openEdit(' + tv.MaThanhVien + ',\'' + (tv.HoTen || '').replace(/'/g, "\\'") + '\',' + (tv.DiemTichLuy || 0) + ',' + (tv.DiemThuong || 0) + ')">' +
                        '<i class="bi bi-pencil"></i>' +
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

    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        searchTimer = setTimeout(function() { currentPage = 1; loadData(1, q); }, 400);
    });

    window.goPage = function(p) {
        currentPage = p;
        loadData(p, document.getElementById('searchInput').value.trim());
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Mở modal sửa điểm
    window.openEdit = function(id, name, diemTL, diemThuong) {
        document.getElementById('editId').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editDiemTL').value = diemTL;
        document.getElementById('editDiemThuong').value = diemThuong;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEdit')).show();
    };

    // Lưu sửa điểm
    document.getElementById('btnSaveEdit').addEventListener('click', function() {
        var id = document.getElementById('editId').value;
        var diemTL = parseInt(document.getElementById('editDiemTL').value) || 0;
        var diemThuong = parseInt(document.getElementById('editDiemThuong').value) || 0;

        adminFetch('thanh-vien/' + id, {
            method: 'PUT',
            body: { DiemTichLuy: diemTL, DiemThuong: diemThuong }
        }).then(function(res) {
            if (res.status === 200) {
                adminToast(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalEdit')).hide();
                loadData(currentPage, document.getElementById('searchInput').value.trim());
            } else {
                adminToast(res.message || 'Lỗi', 'danger');
            }
        });
    });

    loadData(1);
})();
</script>
