<?php $activePage = 'benh-nhan'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ TOOLBAR ═══ -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="admin-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="searchInput" placeholder="Tìm theo tên, SĐT, email...">
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
                        <th>Số điện thoại</th>
                        <th>Email</th>
                        <th>Hạng TV</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
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

<!-- ═══ MODAL CHI TIẾT ═══ -->
<div class="modal fade admin-modal" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person me-2"></i>Chi tiết bệnh nhân</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
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
        var url = 'benh-nhan?page=' + page + '&limit=15';
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
        info.textContent = 'Tổng: ' + formatNumber(pag.total) + ' bệnh nhân';

        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="admin-empty"><i class="bi bi-people"></i><p>Không tìm thấy bệnh nhân nào</p></td></tr>';
            document.getElementById('paginationBar').innerHTML = '';
            return;
        }

        var startIdx = (pag.page - 1) * pag.limit;
        var html = '';
        list.forEach(function(bn, i) {
            var hasAccount = !!bn.MaNguoiDung;
            var status = hasAccount && parseInt(bn.TrangThaiTK) === 1;
            var ngay = bn.NgayTao ? new Date(bn.NgayTao).toLocaleDateString('vi-VN') : '—';
            var hangHtml = bn.TenHang
                ? '<span class="admin-badge" style="background:' + bn.MauHangHex + '15;color:' + bn.MauHangHex + '">' +
                  '<span class="admin-color-dot" style="background:' + bn.MauHangHex + '"></span>' + bn.TenHang + '</span>'
                : '<span class="text-muted small">—</span>';

            var statusHtml = '';
            if (hasAccount) {
                statusHtml = '<span class="admin-badge ' + (status ? 'admin-badge-active' : 'admin-badge-locked') + '">' +
                    '<i class="bi bi-circle-fill" style="font-size:.45rem"></i>' +
                    (status ? 'Hoạt động' : 'Bị khóa') + '</span>';
            } else {
                statusHtml = '<span class="admin-badge" style="background:#f0f0f0;color:#999"><i class="bi bi-circle-fill" style="font-size:.45rem"></i>Chưa có TK</span>';
            }

            var actionHtml = '';
            if (hasAccount) {
                actionHtml = '<div class="d-flex gap-1">' +
                    '<button class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Chi tiết" onclick="viewDetail(' + bn.MaNguoiDung + ')">' +
                        '<i class="bi bi-eye"></i></button>' +
                    '<button class="btn btn-sm ' + (status ? 'btn-outline-warning' : 'btn-outline-success') + ' rounded-pill px-2" title="' + (status ? 'Khóa' : 'Mở khóa') + '" onclick="toggleStatus(' + bn.MaNguoiDung + ',' + (status ? 0 : 1) + ')">' +
                        '<i class="bi bi-' + (status ? 'lock' : 'unlock') + '"></i></button>' +
                    '</div>';
            } else {
                actionHtml = '<span class="text-muted small">—</span>';
            }

            html += '<tr>' +
                '<td class="text-muted">' + (startIdx + i + 1) + '</td>' +
                '<td class="fw-medium">' + (bn.HoTen || '—') + '</td>' +
                '<td>' + (bn.SoDienThoai || '—') + '</td>' +
                '<td class="small">' + (bn.Email || '—') + '</td>' +
                '<td>' + hangHtml + '</td>' +
                '<td>' + statusHtml + '</td>' +
                '<td class="text-muted small">' + ngay + '</td>' +
                '<td>' + actionHtml + '</td>' +
            '</tr>';
        });
        tbody.innerHTML = html;

        // Pagination
        renderPagination(pag);
    }

    function renderPagination(pag) {
        var container = document.getElementById('paginationBar');
        if (pag.totalPages <= 1) { container.innerHTML = ''; return; }

        var from = (pag.page - 1) * pag.limit + 1;
        var to = Math.min(pag.page * pag.limit, pag.total);

        var html = '<div class="admin-pagination-info">Hiển thị ' + from + '–' + to + ' / ' + pag.total + '</div>';
        html += '<div class="admin-pagination-btns">';

        if (pag.page > 1) {
            html += '<button class="btn btn-outline-secondary" onclick="goPage(' + (pag.page - 1) + ')"><i class="bi bi-chevron-left"></i></button>';
        }
        for (var p = 1; p <= pag.totalPages; p++) {
            if (p === pag.page) {
                html += '<button class="btn btn-primary">' + p + '</button>';
            } else if (p <= 3 || p > pag.totalPages - 2 || Math.abs(p - pag.page) <= 1) {
                html += '<button class="btn btn-outline-secondary" onclick="goPage(' + p + ')">' + p + '</button>';
            } else if (p === 4 || p === pag.totalPages - 2) {
                html += '<button class="btn btn-outline-secondary" disabled>…</button>';
            }
        }
        if (pag.page < pag.totalPages) {
            html += '<button class="btn btn-outline-secondary" onclick="goPage(' + (pag.page + 1) + ')"><i class="bi bi-chevron-right"></i></button>';
        }
        html += '</div>';
        container.innerHTML = html;
    }

    // Search handler
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        searchTimer = setTimeout(function() {
            currentPage = 1;
            loadData(1, q);
        }, 400);
    });

    // Navigation
    window.goPage = function(p) {
        currentPage = p;
        var q = document.getElementById('searchInput').value.trim();
        loadData(p, q);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Toggle account status
    window.toggleStatus = function(id, newStatus) {
        var label = newStatus === 1 ? 'mở khóa' : 'khóa';
        if (!confirm('Bạn có chắc muốn ' + label + ' tài khoản này?')) return;

        adminFetch('toggle-status', {
            method: 'POST',
            body: { id: id, status: newStatus }
        }).then(function(res) {
            if (res.status === 200) {
                adminToast(res.message, 'success');
                loadData(currentPage, document.getElementById('searchInput').value.trim());
            } else {
                adminToast(res.message || 'Lỗi', 'danger');
            }
        });
    };

    // Reset password
    window.resetPassword = function(id) {
        if (!confirm('Yêu cầu bệnh nhân đổi mật khẩu lần đăng nhập tiếp theo?')) return;

        adminFetch('reset-password', {
            method: 'POST',
            body: { id: id }
        }).then(function(res) {
            if (res.status === 200) {
                adminToast(res.message, 'success');
            } else {
                adminToast(res.message || 'Lỗi', 'danger');
            }
        });
    };

    // View detail modal
    window.viewDetail = function(id) {
        var body = document.getElementById('modalBody');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetail')).show();

        adminFetch('benh-nhan/' + id).then(function(res) {
            if (res.status !== 200 || !res.data) {
                body.innerHTML = '<div class="text-center py-3 text-danger">Không thể tải dữ liệu</div>';
                return;
            }
            var u = res.data.user;
            var lh = res.data.lichHen;
            var hd = res.data.hoaDon;
            var status = parseInt(u.TrangThaiTK) === 1;

            var html = '';
            // Thông tin cá nhân
            html += '<div class="row g-3 mb-4">';
            html += '<div class="col-sm-6"><strong class="text-muted small d-block">Họ tên</strong>' + (u.HoTen || '—') + '</div>';
            html += '<div class="col-sm-6"><strong class="text-muted small d-block">Số điện thoại</strong>' + (u.SoDienThoai || '—') + '</div>';
            html += '<div class="col-sm-6"><strong class="text-muted small d-block">Email</strong>' + (u.Email || '—') + '</div>';
            html += '<div class="col-sm-6"><strong class="text-muted small d-block">Giới tính</strong>' + (u.GioiTinh || '—') + '</div>';
            html += '<div class="col-sm-6"><strong class="text-muted small d-block">Ngày sinh</strong>' + (u.NgaySinh ? new Date(u.NgaySinh).toLocaleDateString('vi-VN') : '—') + '</div>';
            html += '<div class="col-sm-6"><strong class="text-muted small d-block">Trạng thái</strong>' +
                '<span class="admin-badge ' + (status ? 'admin-badge-active' : 'admin-badge-locked') + '">' + (status ? 'Hoạt động' : 'Bị khóa') + '</span></div>';

            if (u.TienSuBenhLy) {
                html += '<div class="col-12"><strong class="text-muted small d-block">Tiền sử bệnh lý</strong>' + u.TienSuBenhLy + '</div>';
            }
            if (u.TenHang) {
                html += '<div class="col-sm-6"><strong class="text-muted small d-block">Hạng thành viên</strong>' +
                    '<span class="admin-badge" style="background:' + u.MauHangHex + '15;color:' + u.MauHangHex + '">' + u.TenHang + '</span></div>';
                html += '<div class="col-sm-6"><strong class="text-muted small d-block">Điểm tích lũy</strong>' + formatNumber(u.DiemTichLuy || 0) + '</div>';
            }
            html += '</div>';

            // Nút hành động
            html += '<div class="d-flex gap-2 mb-4">';
            html += '<button class="btn btn-sm ' + (status ? 'btn-outline-warning' : 'btn-outline-success') + ' rounded-pill px-3" onclick="toggleStatus(' + u.MaNguoiDung + ',' + (status ? 0 : 1) + '); bootstrap.Modal.getInstance(document.getElementById(\'modalDetail\')).hide();">' +
                '<i class="bi bi-' + (status ? 'lock' : 'unlock') + ' me-1"></i>' + (status ? 'Khóa tài khoản' : 'Mở khóa') + '</button>';
            html += '<button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="resetPassword(' + u.MaNguoiDung + ')">' +
                '<i class="bi bi-key me-1"></i>Reset mật khẩu</button>';
            html += '</div>';

            // Lịch sử lịch hẹn
            var statusMap = {0:'Chờ xác nhận', 1:'Đã xác nhận', 2:'Hoàn thành', 3:'Đã hủy'};
            html += '<h6 class="fw-bold text-primary mb-2"><i class="bi bi-calendar3 me-1"></i>Lịch hẹn gần đây</h6>';
            if (lh && lh.length > 0) {
                html += '<div class="table-responsive mb-4"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Bác sĩ</th><th>Trạng thái</th></tr></thead><tbody>';
                lh.forEach(function(l) {
                    var ngay = l.ThoiGianHen ? new Date(l.ThoiGianHen).toLocaleString('vi-VN') : '—';
                    html += '<tr><td>' + ngay + '</td><td>' + (l.TenBacSi || '—') + '</td><td>' + (statusMap[l.TrangThai] || '—') + '</td></tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p class="text-muted small mb-4">Chưa có lịch hẹn nào</p>';
            }

            // Hóa đơn
            html += '<h6 class="fw-bold text-primary mb-2"><i class="bi bi-receipt me-1"></i>Hóa đơn gần đây</h6>';
            if (hd && hd.length > 0) {
                html += '<div class="table-responsive"><table class="admin-table"><thead><tr><th>Mã HD</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày</th></tr></thead><tbody>';
                hd.forEach(function(h) {
                    var ngay = h.NgayThanhToan ? new Date(h.NgayThanhToan).toLocaleDateString('vi-VN') : '—';
                    var ttTT = parseInt(h.TrangThai) === 1 ? '<span class="admin-badge admin-badge-active">Đã TT</span>' : '<span class="admin-badge admin-badge-locked">Chưa TT</span>';
                    html += '<tr><td>#' + h.MaHoaDon + '</td><td>' + formatCurrency(h.TongTien || 0) + '</td><td>' + ttTT + '</td><td>' + ngay + '</td></tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p class="text-muted small">Chưa có hóa đơn nào</p>';
            }

            body.innerHTML = html;
        }).catch(function() {
            body.innerHTML = '<div class="text-center py-3 text-danger">Lỗi kết nối</div>';
        });
    };

    // Load initial data
    loadData(1);
})();
</script>
