<?php $activePage = 'benh-nhan'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ FILTER ═══ -->
<div class="admin-card mb-4">
    <div class="admin-card-head">
        <h2 class="admin-card-title"><i class="bi bi-people"></i>Bệnh nhân của tôi</h2>
        <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 me-1 text-muted small">Ngày:</label>
            <input type="date" class="form-control form-control-sm" id="filterNgay" style="max-width:170px">
        </div>
    </div>
    <div class="admin-card-body-flush">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bệnh nhân</th>
                    <th>SĐT</th>
                    <th>Giờ hẹn</th>
                    <th>Triệu chứng</th>
                    <th>Chẩn đoán</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody id="tableBenhNhan">
                <tr><td colspan="7" class="text-center text-muted py-4">Đang tải...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ MODAL CHI TIẾT ═══ -->
<div class="modal fade admin-modal" id="modalChiTiet" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-medical me-2"></i>Chi tiết phiếu khám</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
<script>
(function() {
    var filterNgay = document.getElementById('filterNgay');
    filterNgay.value = new Date().toISOString().slice(0, 10);

    function loadBenhNhan(ngay) {
        bacsiFetch('benh-nhan?ngay=' + ngay).then(function(res) {
            if (res.status !== 200) return;
            var list = res.data.benhNhan || [];
            var tbody = document.getElementById('tableBenhNhan');

            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-3 mb-2"></i>Không có bệnh nhân nào trong ngày này</td></tr>';
                return;
            }

            var html = '';
            list.forEach(function(bn, idx) {
                var statusMap = {0: ['Chờ khám','bg-warning text-dark'], 1: ['Đang khám','bg-info'], 2: ['Hoàn thành','bg-success'], 3: ['Đã lập HĐ','bg-primary']};
                var st = statusMap[bn.TrangThaiPK] || ['Khác','bg-secondary'];
                var gioHen = bn.ThoiGianHen ? new Date(bn.ThoiGianHen).toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'}) : '—';

                html += '<tr style="cursor:pointer" onclick="showDetail(' + JSON.stringify(bn).replace(/"/g, '&quot;') + ')">' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td><strong>' + (bn.HoTen || '') + '</strong>' +
                        (bn.NgaySinh ? '<br><small class="text-muted">' + new Date(bn.NgaySinh).toLocaleDateString('vi-VN') + '</small>' : '') + '</td>' +
                    '<td>' + (bn.SoDienThoai || '') + '</td>' +
                    '<td>' + gioHen + '</td>' +
                    '<td class="text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (bn.TrieuChung || '—') + '</td>' +
                    '<td class="text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (bn.ChanDoan || '—') + '</td>' +
                    '<td><span class="badge ' + st[1] + ' rounded-pill">' + st[0] + '</span></td></tr>';
            });
            tbody.innerHTML = html;
        });
    }

    filterNgay.addEventListener('change', function() {
        loadBenhNhan(this.value);
    });

    window.showDetail = function(bn) {
        var gioiTinh = bn.GioiTinh == 1 ? 'Nam' : bn.GioiTinh == 0 ? 'Nữ' : '—';
        var ngaySinh = bn.NgaySinh ? new Date(bn.NgaySinh).toLocaleDateString('vi-VN') : '—';
        var html = '<div class="row g-3">' +
            '<div class="col-md-6">' +
                '<h6 class="text-primary mb-3"><i class="bi bi-person me-1"></i>Thông tin bệnh nhân</h6>' +
                '<p><strong>Họ tên:</strong> ' + (bn.HoTen || '') + '</p>' +
                '<p><strong>Ngày sinh:</strong> ' + ngaySinh + '</p>' +
                '<p><strong>Giới tính:</strong> ' + gioiTinh + '</p>' +
                '<p><strong>SĐT:</strong> ' + (bn.SoDienThoai || '') + '</p>' +
                '<p><strong>Tiền sử bệnh lý:</strong> ' + (bn.TienSuBenhLy || '—') + '</p>' +
            '</div>' +
            '<div class="col-md-6">' +
                '<h6 class="text-primary mb-3"><i class="bi bi-file-medical me-1"></i>Phiếu khám #' + bn.MaPhieuKham + '</h6>' +
                '<p><strong>Triệu chứng:</strong> ' + (bn.TrieuChung || '—') + '</p>' +
                '<p><strong>Chẩn đoán:</strong> ' + (bn.ChanDoan || '—') + '</p>' +
                '<p><strong>Ghi chú:</strong> ' + (bn.GhiChu || '—') + '</p>' +
                '<p><strong>Ghi chú hẹn:</strong> ' + (bn.GhiChuHen || '—') + '</p>' +
            '</div></div>';

        document.getElementById('modalBody').innerHTML = html;
        new bootstrap.Modal(document.getElementById('modalChiTiet')).show();
    };

    loadBenhNhan(filterNgay.value);
})();
</script>
</body>
</html>
