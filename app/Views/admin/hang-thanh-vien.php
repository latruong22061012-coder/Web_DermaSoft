<?php $activePage = 'hang-thanh-vien'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ TOOLBAR ═══ -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <p class="text-muted small mb-0">Cấu hình các hạng thành viên và ưu đãi giảm giá tương ứng.</p>
    <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="openCreate()">
        <i class="bi bi-plus-lg me-1"></i>Thêm hạng mới
    </button>
</div>

<!-- ═══ CARDS LIST ═══ -->
<div class="row g-3" id="hangList">
    <div class="col-12 text-center text-muted py-4">Đang tải...</div>
</div>

<!-- ═══ MODAL THÊM/SỬA ═══ -->
<div class="modal fade admin-modal" id="modalHang" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalHangTitle"><i class="bi bi-gem me-2"></i>Thêm hạng mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="hangId">
                <div class="mb-3">
                    <label class="form-label fw-medium">Tên hạng <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="hangTen" placeholder="VD: Bạc, Vàng, Kim Cương...">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-medium">Điểm tối thiểu <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="hangDiem" min="0" placeholder="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Màu hiển thị</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" class="form-control form-control-color" id="hangMau" value="#6c757d" style="width:48px">
                            <input type="text" class="form-control" id="hangMauText" value="#6c757d" maxlength="7" style="max-width:100px">
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <label class="form-label fw-medium small">% Giảm dược phẩm</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="hangGiamDP" min="0" max="100" step="0.1" value="0">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <label class="form-label fw-medium small">% Giảm tổng HĐ</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="hangGiamHD" min="0" max="100" step="0.1" value="0">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <label class="form-label fw-medium small">Giảm cố định (₫)</label>
                        <input type="number" class="form-control" id="hangGiamCD" min="0" step="1000" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary rounded-pill px-3" id="btnSaveHang">
                    <i class="bi bi-check-lg me-1"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>

<script>
(function() {
    // Đồng bộ color picker & text
    document.getElementById('hangMau').addEventListener('input', function() {
        document.getElementById('hangMauText').value = this.value;
    });
    document.getElementById('hangMauText').addEventListener('input', function() {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
            document.getElementById('hangMau').value = this.value;
        }
    });

    function loadData() {
        adminFetch('hang-thanh-vien').then(function(res) {
            if (res.status !== 200) {
                adminToast(res.message || 'Lỗi', 'danger');
                return;
            }
            renderList(res.data || []);
        }).catch(function() {
            adminToast('Lỗi kết nối server', 'danger');
        });
    }

    function renderList(list) {
        var container = document.getElementById('hangList');
        if (!list || list.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="admin-empty"><i class="bi bi-gem"></i><p>Chưa có hạng thành viên nào</p></div></div>';
            return;
        }

        var html = '';
        list.forEach(function(h) {
            var color = h.MauHangHex || '#6c757d';
            html += '<div class="col-md-6 col-xl-4">' +
                '<div class="hang-config-card d-flex gap-3">' +
                    '<div class="hang-color-strip" style="background:' + color + '"></div>' +
                    '<div class="flex-grow-1">' +
                        '<div class="d-flex align-items-center justify-content-between mb-2">' +
                            '<h6 class="mb-0 fw-bold" style="color:' + color + '">' + h.TenHang + '</h6>' +
                            '<span class="admin-badge admin-badge-star">' + formatNumber(h.SoThanhVien || 0) + ' TV</span>' +
                        '</div>' +
                        '<div class="small text-muted mb-2">Điểm tối thiểu: <strong>' + formatNumber(h.DiemToiThieu) + '</strong></div>' +
                        '<div class="d-flex gap-3 small text-muted mb-3">' +
                            '<span><i class="bi bi-capsule me-1"></i>Dược phẩm: <strong>' + (h.PhanTramGiamDuocPham || 0) + '%</strong></span>' +
                            '<span><i class="bi bi-receipt me-1"></i>Tổng HĐ: <strong>' + (h.PhanTramGiamTongHD || 0) + '%</strong></span>' +
                        '</div>' +
                        (parseFloat(h.GiamGiaCodinh || 0) > 0 ? '<div class="small text-muted mb-3"><i class="bi bi-tag me-1"></i>Giảm cố định: <strong>' + formatCurrency(h.GiamGiaCodinh) + '</strong></div>' : '') +
                        '<div class="d-flex gap-1">' +
                            '<button class="btn btn-sm btn-outline-primary rounded-pill px-2" onclick="openEdit(' + h.MaHang + ')" title="Sửa"><i class="bi bi-pencil"></i></button>' +
                            (parseInt(h.SoThanhVien || 0) === 0
                                ? '<button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="deleteHang(' + h.MaHang + ',\'' + h.TenHang.replace(/'/g, "\\'") + '\')" title="Xóa"><i class="bi bi-trash"></i></button>'
                                : '') +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        });
        container.innerHTML = html;
    }

    window.openCreate = function() {
        document.getElementById('hangId').value = '';
        document.getElementById('hangTen').value = '';
        document.getElementById('hangDiem').value = '';
        document.getElementById('hangGiamDP').value = '0';
        document.getElementById('hangGiamHD').value = '0';
        document.getElementById('hangGiamCD').value = '0';
        document.getElementById('hangMau').value = '#6c757d';
        document.getElementById('hangMauText').value = '#6c757d';
        document.getElementById('modalHangTitle').innerHTML = '<i class="bi bi-gem me-2"></i>Thêm hạng mới';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHang')).show();
    };

    window.openEdit = function(id) {
        adminFetch('hang-thanh-vien/' + id).then(function(res) {
            if (res.status !== 200 || !res.data) {
                adminToast('Không tìm thấy hạng', 'danger');
                return;
            }
            var h = res.data;
            document.getElementById('hangId').value = h.MaHang;
            document.getElementById('hangTen').value = h.TenHang || '';
            document.getElementById('hangDiem').value = h.DiemToiThieu || 0;
            document.getElementById('hangGiamDP').value = h.PhanTramGiamDuocPham || 0;
            document.getElementById('hangGiamHD').value = h.PhanTramGiamTongHD || 0;
            document.getElementById('hangGiamCD').value = h.GiamGiaCodinh || 0;
            document.getElementById('hangMau').value = h.MauHangHex || '#6c757d';
            document.getElementById('hangMauText').value = h.MauHangHex || '#6c757d';
            document.getElementById('modalHangTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Sửa hạng: ' + h.TenHang;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHang')).show();
        });
    };

    window.deleteHang = function(id, name) {
        if (!confirm('Xóa hạng "' + name + '"? Hành động này không thể hoàn tác.')) return;
        adminFetch('hang-thanh-vien/' + id, { method: 'DELETE' }).then(function(res) {
            if (res.status === 200) {
                adminToast(res.message, 'success');
                loadData();
            } else {
                adminToast(res.message || 'Lỗi', 'danger');
            }
        });
    };

    document.getElementById('btnSaveHang').addEventListener('click', function() {
        var id = document.getElementById('hangId').value;
        var body = {
            TenHang: document.getElementById('hangTen').value.trim(),
            DiemToiThieu: parseInt(document.getElementById('hangDiem').value) || 0,
            MauHangHex: document.getElementById('hangMauText').value || '#6c757d',
            PhanTramGiamDuocPham: parseFloat(document.getElementById('hangGiamDP').value) || 0,
            PhanTramGiamTongHD: parseFloat(document.getElementById('hangGiamHD').value) || 0,
            GiamGiaCodinh: parseFloat(document.getElementById('hangGiamCD').value) || 0,
        };

        if (!body.TenHang) {
            adminToast('Vui lòng nhập tên hạng', 'danger');
            return;
        }

        var url = id ? 'hang-thanh-vien/' + id : 'hang-thanh-vien';
        var method = id ? 'PUT' : 'POST';

        adminFetch(url, { method: method, body: body }).then(function(res) {
            if (res.status === 200 || res.status === 201) {
                adminToast(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalHang')).hide();
                loadData();
            } else {
                adminToast(res.message || 'Lỗi', 'danger');
            }
        });
    });

    loadData();
})();
</script>
