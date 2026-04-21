<?php $activePage = 'luong'; ?>
<?php require __DIR__ . '/layout-header.php'; ?>

<!-- ═══ FILTER NĂM ═══ -->
<div class="d-flex align-items-center gap-2 mb-4">
    <button class="btn btn-outline-secondary btn-sm" id="btnPrevYear"><i class="bi bi-chevron-left"></i></button>
    <span class="fw-semibold fs-5" id="labelNam"></span>
    <button class="btn btn-outline-secondary btn-sm" id="btnNextYear"><i class="bi bi-chevron-right"></i></button>
</div>

<!-- ═══ BẢNG LƯƠNG ═══ -->
<div class="admin-card mb-4">
    <div class="admin-card-head">
        <h2 class="admin-card-title"><i class="bi bi-wallet2"></i>Bảng lương</h2>
    </div>
    <div class="admin-card-body-flush">
        <table class="admin-table" style="min-width:850px">
            <thead>
                <tr>
                    <th>Tháng</th>
                    <th>Loại tính</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-center">Số BN</th>
                    <th class="text-center">Ca ĐD</th>
                    <th class="text-center">Ca vắng</th>
                    <th class="text-end">Lương chính</th>
                    <th class="text-end">Tăng ca</th>
                    <th class="text-end">Thưởng</th>
                    <th class="text-end">Khấu trừ</th>
                    <th class="text-end">Tổng lương</th>
                    <th class="text-center">TT</th>
                </tr>
            </thead>
            <tbody id="tableLuong">
                <tr><td colspan="12" class="text-center text-muted py-4">Đang tải...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ LỊCH SỬ TRẢ LƯƠNG ═══ -->
<div class="admin-card">
    <div class="admin-card-head">
        <h2 class="admin-card-title"><i class="bi bi-clock-history"></i>Lịch sử trả lương</h2>
    </div>
    <div class="admin-card-body-flush">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Tháng lương</th>
                    <th class="text-end">Số tiền</th>
                    <th>Phương thức</th>
                    <th>Ngày trả</th>
                    <th>Người trả</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody id="tableLichSuTra">
                <tr><td colspan="6" class="text-center text-muted py-4">Đang tải...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
<script>
(function() {
    var currentYear = new Date().getFullYear();

    function loadLuong(nam) {
        currentYear = nam;
        document.getElementById('labelNam').textContent = 'Năm ' + nam;

        bacsiFetch('luong?nam=' + nam).then(function(res) {
            if (res.status !== 200) return;
            var data = res.data;

            // Bảng lương
            var tbody = document.getElementById('tableLuong');
            if (!data.bangLuong || data.bangLuong.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">Chưa có dữ liệu lương</td></tr>';
            } else {
                var html = '';
                data.bangLuong.forEach(function(bl) {
                    var thang = bl.ThangNam ? new Date(bl.ThangNam).toLocaleDateString('vi-VN', {month:'2-digit', year:'numeric'}) : '';
                    var loai = bl.LoaiTinhLuong === 'THEO_BN' ? 'Theo BN' : bl.LoaiTinhLuong === 'THEO_GIO' ? 'Theo giờ' : bl.LoaiTinhLuong;
                    var ttBadge = bl.TrangThai == 1 ? '<span class="admin-badge admin-badge-active">Đã duyệt</span>' :
                                  bl.TrangThai == 2 ? '<span class="admin-badge admin-badge-locked">Từ chối</span>' :
                                  '<span class="admin-badge" style="background:rgba(255,193,7,.12);color:#856404">Chờ duyệt</span>';

                    html += '<tr>' +
                        '<td><strong>' + thang + '</strong></td>' +
                        '<td>' + loai + '</td>' +
                        '<td class="text-end">' + formatCurrency(bl.DonGia) + '</td>' +
                        '<td class="text-center">' + (bl.SoBenhNhan || 0) + '</td>' +
                        '<td class="text-center">' + (bl.SoCaDiemDanh || 0) + '</td>' +
                        '<td class="text-center">' + (bl.SoCaVang || 0) + '</td>' +
                        '<td class="text-end">' + formatCurrency(bl.LuongChinh) + '</td>' +
                        '<td class="text-end">' + formatCurrency(bl.LuongTangCa) + '</td>' +
                        '<td class="text-end text-success">' + formatCurrency(bl.ThuongThem) + '</td>' +
                        '<td class="text-end text-danger">' + formatCurrency(bl.KhauTru) + '</td>' +
                        '<td class="text-end fw-bold">' + formatCurrency(bl.TongLuong) + '</td>' +
                        '<td class="text-center">' + ttBadge + '</td></tr>';
                });
                tbody.innerHTML = html;
            }

            // Lịch sử trả
            var tbody2 = document.getElementById('tableLichSuTra');
            if (!data.lichSuTra || data.lichSuTra.length === 0) {
                tbody2.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Chưa có lịch sử trả lương</td></tr>';
            } else {
                var html2 = '';
                data.lichSuTra.forEach(function(lst) {
                    var ngay = lst.NgayTra ? new Date(lst.NgayTra).toLocaleDateString('vi-VN') : '';
                    html2 += '<tr>' +
                        '<td>' + (lst.ThangLuong || '') + '</td>' +
                        '<td class="text-end fw-bold">' + formatCurrency(lst.SoTienTra) + '</td>' +
                        '<td>' + (lst.PhuongThuc || '—') + '</td>' +
                        '<td>' + ngay + '</td>' +
                        '<td>' + (lst.NguoiTraTen || '') + '</td>' +
                        '<td class="text-muted">' + (lst.GhiChu || '—') + '</td></tr>';
                });
                tbody2.innerHTML = html2;
            }
        });
    }

    document.getElementById('btnPrevYear').addEventListener('click', function() { loadLuong(currentYear - 1); });
    document.getElementById('btnNextYear').addEventListener('click', function() { loadLuong(currentYear + 1); });

    loadLuong(currentYear);
})();
</script>
</body>
</html>
