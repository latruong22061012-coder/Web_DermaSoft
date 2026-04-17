        </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-wrapper -->

<script src="<?= $baseUrl ?>public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= $baseUrl ?>public/assets/js/csrf.js"></script>
<script>
// ═══ Sidebar Toggle (Mobile) ═══
(function() {
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
})();

// ═══ BacSi API Helper ═══
var BACSI_API = '<?= $baseUrl ?>index.php?route=api/bacsi/';

function bacsiFetch(endpoint, options) {
    options = options || {};
    var parts = endpoint.split('?');
    var url = BACSI_API + parts[0];
    if (parts[1]) url += '&' + parts[1];
    var config = {
        method: options.method || 'GET',
        headers: { 'Content-Type': 'application/json' },
    };
    if (options.body) {
        config.body = JSON.stringify(options.body);
    }
    return fetch(url, config).then(function(r) { return r.json(); });
}

function bacsiToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('toastContainer');
    if (!container) return;

    var icon = type === 'success' ? 'bi-check-circle-fill' : type === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';
    var el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + type + ' border-0 show mb-2';
    el.setAttribute('role', 'alert');
    el.innerHTML =
        '<div class="d-flex">' +
            '<div class="toast-body"><i class="bi ' + icon + ' me-2"></i>' + message + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
        '</div>';
    container.appendChild(el);
    setTimeout(function() { el.remove(); }, 4000);
}

function formatNumber(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

function formatCurrency(num) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
}

function renderStars(score) {
    var html = '';
    for (var i = 1; i <= 5; i++) {
        html += '<i class="bi ' + (i <= score ? 'bi-star-fill' : 'bi-star') + '"></i>';
    }
    return html;
}
</script>
