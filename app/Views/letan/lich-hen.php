<?php $activePage = 'lich-hen'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ FILTER ═══ -->
<div class="admin-card mb-4">
    <div class="admin-card-head">
        <h2 class="admin-card-title"><i class="bi bi-calendar-event"></i>Danh sách lịch hẹn</h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <input type="date" class="form-control form-control-sm" id="filterNgay" style="max-width:170px">
            <select class="form-select form-select-sm" id="filterTrangThai" style="max-width:170px">
                <option value="">Tất cả trạng thái</option>
                <option value="0">Chờ xác nhận</option>
                <option value="1">Đã xác nhận</option>
                <option value="2">Hoàn thành</option>
                <option value="3">Đã hủy</option>
            </select>
        </div>
    </div>
    <div class="admin-card-body-flush" style="overflow-x:auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Giờ hẹn</th>
                    <th>Bệnh nhân</th>
                    <th>SĐT</th>
                    <th>Bác sĩ</th>
                    <th>Ghi chú</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody id="tableLichHen">
                <tr><td colspan="7" class="text-center text-muted py-4">Đang tải...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
<script>
(function() {
    var filterNgay = document.getElementById('filterNgay');
    var filterTT = document.getElementById('filterTrangThai');
    filterNgay.value = new Date().toISOString().slice(0, 10);

    function loadLichHen() {
        var ngay = filterNgay.value;
        var tt = filterTT.value;
        var query = 'lich-hen?ngay=' + ngay;
        if (tt !== '') query += '&trangThai=' + tt;

        letanFetch(query).then(function(res) {
            if (res.status !== 200) return;
            var list = res.data.lichHen || [];
            var tbody = document.getElementById('tableLichHen');

            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-3 mb-2"></i>Không có lịch hẹn</td></tr>';
                return;
            }

            var statusMap = {0: ['Chờ xác nhận','bg-warning text-dark'], 1: ['Đã xác nhận','bg-info'], 2: ['Hoàn thành','bg-success'], 3: ['Đã hủy','bg-danger']};
            var html = '';
            list.forEach(function(lh, idx) {
                var gio = lh.ThoiGianHen ? new Date(lh.ThoiGianHen).toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'}) : '—';
                var st = statusMap[lh.TrangThai] || ['Khác','bg-secondary'];

                html += '<tr>' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td><strong>' + gio + '</strong></td>' +
                    '<td>' + (lh.HoTen || '') +
                        (lh.NgaySinh ? '<br><small class="text-muted">' + new Date(lh.NgaySinh).toLocaleDateString('vi-VN') + '</small>' : '') + '</td>' +
                    '<td>' + (lh.SoDienThoai || lh.SoDienThoaiKhach || '') + '</td>' +
                    '<td>' + (lh.TenBacSi || '<span class="text-muted">Chưa gán</span>') + '</td>' +
                    '<td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (lh.GhiChu || '—') + '</td>' +
                    '<td><span class="badge ' + st[1] + ' rounded-pill">' + st[0] + '</span></td></tr>';
            });
            tbody.innerHTML = html;
        });
    }

    filterNgay.addEventListener('change', loadLichHen);
    filterTT.addEventListener('change', loadLichHen);

    loadLichHen();
})();
</script>
</body>
</html>
