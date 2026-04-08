document.addEventListener("DOMContentLoaded", function () {
  const STORAGE_KEY = "profile-active-tab";
  const avatarUpload = document.getElementById("avatarUpload");
  const avatarPreview = document.getElementById("avatarPreview");
  const profileForm = document.getElementById("profileForm");
  const profileFeedback = document.getElementById("profileFeedback");
  const saveBtn = document.getElementById("btnSaveProfile");
  const resetBtn = document.getElementById("btnResetProfile");

  const fullNameInput = document.getElementById("fullName");
  const emailInput = document.getElementById("email");

  const initialFormValues = {
    fullName: fullNameInput ? fullNameInput.value : "",
    email: emailInput ? emailInput.value : "",
  };

  if (avatarUpload && avatarPreview) {
    avatarUpload.addEventListener("change", function (event) {
      const file = event.target.files[0];
      if (!file) return;

      if (file.size > 2 * 1024 * 1024) {
        showFeedback("Ảnh vượt quá 2MB. Vui lòng chọn ảnh nhỏ hơn.", "danger");
        avatarUpload.value = "";
        return;
      }

      const allowed = ["image/jpeg", "image/png", "image/webp"];
      if (!allowed.includes(file.type)) {
        showFeedback("Chỉ chấp nhận file JPG, PNG, WEBP.", "danger");
        avatarUpload.value = "";
        return;
      }

      // Preview ngay lập tức
      const reader = new FileReader();
      reader.onload = function (e) {
        avatarPreview.src = e.target.result;
        const heroAvatar = document.getElementById("heroAvatar");
        if (heroAvatar) heroAvatar.src = e.target.result;
      };
      reader.readAsDataURL(file);

      // Upload lên server
      const formData = new FormData();
      formData.append("avatar", file);

      const uploadLabel = document.querySelector('label[for="avatarUpload"]');
      if (uploadLabel) {
        uploadLabel.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang tải...';
        uploadLabel.classList.add("disabled");
      }

      const apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
      fetch(apiBase + "/api/profile/upload-avatar", {
        method: "POST",
        body: formData,
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (result) {
          if (uploadLabel) {
            uploadLabel.innerHTML =
              '<i class="bi bi-camera me-2"></i>Thay đổi ảnh đại diện';
            uploadLabel.classList.remove("disabled");
          }
          if (result.status === 200) {
            // Cập nhật dataset.original để rollback tương lai dùng ảnh mới
            avatarPreview.dataset.original = avatarPreview.src;
            const heroAvatar = document.getElementById("heroAvatar");
            if (heroAvatar) heroAvatar.dataset.original = heroAvatar.src;
            showFeedback(
              "Ảnh đại diện đã được cập nhật thành công.",
              "success",
            );
          } else {
            showFeedback(
              result.message || "Lỗi tải ảnh. Vui lòng thử lại.",
              "danger",
            );
            // Rollback cả 2 ảnh về trạng thái trước khi chọn file
            const prevSrc = avatarPreview.dataset.original || "";
            avatarPreview.src = prevSrc;
            const heroAvatar = document.getElementById("heroAvatar");
            if (heroAvatar)
              heroAvatar.src = heroAvatar.dataset.original || heroAvatar.src;
          }
        })
        .catch(function () {
          if (uploadLabel) {
            uploadLabel.innerHTML =
              '<i class="bi bi-camera me-2"></i>Thay đổi ảnh đại diện';
            uploadLabel.classList.remove("disabled");
          }
          showFeedback("Lỗi kết nối server. Vui lòng thử lại.", "danger");
        });
    });

    // Lưu src ban đầu để rollback nếu cần
    avatarPreview.dataset.original = avatarPreview.src;
    const heroAvatarInit = document.getElementById("heroAvatar");
    if (heroAvatarInit) heroAvatarInit.dataset.original = heroAvatarInit.src;
  }

  function showFeedback(message, type) {
    if (!profileFeedback) return;
    profileFeedback.className = "alert alert-" + type;
    profileFeedback.textContent = message;
    profileFeedback.classList.remove("d-none");
  }

  function clearFeedback() {
    if (!profileFeedback) return;
    profileFeedback.classList.add("d-none");
    profileFeedback.textContent = "";
  }

  function setInvalid(input, isInvalid) {
    if (!input) return;
    input.classList.toggle("is-invalid", isInvalid);
    input.classList.toggle("is-valid", !isInvalid);
  }

  function validateName(value) {
    return value.trim().length >= 3;
  }

  function validateEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
  }

  function runFormValidation() {
    const isNameValid = validateName(fullNameInput ? fullNameInput.value : "");
    const isEmailValid = validateEmail(emailInput ? emailInput.value : "");

    setInvalid(fullNameInput, !isNameValid);
    setInvalid(emailInput, !isEmailValid);

    return isNameValid && isEmailValid;
  }

  [fullNameInput, emailInput].forEach(function (input) {
    if (!input) return;
    input.addEventListener("input", function () {
      clearFeedback();
      if (input === fullNameInput)
        setInvalid(input, !validateName(input.value));
      if (input === emailInput) setInvalid(input, !validateEmail(input.value));
    });
  });

  if (profileForm && saveBtn) {
    profileForm.addEventListener("submit", function (event) {
      event.preventDefault();
      clearFeedback();

      if (!runFormValidation()) {
        showFeedback(
          "Vui lòng kiểm tra lại thông tin trước khi lưu.",
          "danger",
        );
        return;
      }

      saveBtn.disabled = true;
      saveBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang lưu...';

      const apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
      fetch(apiBase + "/api/profile/update", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          hoTen: fullNameInput ? fullNameInput.value.trim() : "",
          email: emailInput ? emailInput.value.trim() : "",
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (result) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="bi bi-save me-2"></i>Lưu thay đổi';
          if (result.status === 200) {
            const heroName = document.querySelector(
              ".font-heading.fw-bold.mb-2",
            );
            if (heroName && fullNameInput)
              heroName.textContent = fullNameInput.value.trim();
            if (fullNameInput)
              initialFormValues.fullName = fullNameInput.value.trim();
            if (emailInput) initialFormValues.email = emailInput.value.trim();
            [fullNameInput, emailInput].forEach(function (inp) {
              if (inp) inp.classList.remove("is-valid", "is-invalid");
            });
            showFeedback(
              "Cập nhật hồ sơ thành công. Thông tin của bạn đã được lưu.",
              "success",
            );
          } else {
            showFeedback(
              result.message || "Lỗi cập nhật, vui lòng thử lại.",
              "danger",
            );
          }
        })
        .catch(function () {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="bi bi-save me-2"></i>Lưu thay đổi';
          showFeedback("Lỗi kết nối server. Vui lòng thử lại.", "danger");
        });
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener("click", function () {
      if (fullNameInput) fullNameInput.value = initialFormValues.fullName;
      if (emailInput) emailInput.value = initialFormValues.email;
      [fullNameInput, emailInput].forEach(function (input) {
        if (!input) return;
        input.classList.remove("is-valid", "is-invalid");
      });
      clearFeedback();
      showFeedback("Đã khôi phục dữ liệu ban đầu của hồ sơ.", "secondary");
    });
  }

  function animateMembershipProgress() {
    const progressBar = document.getElementById("membershipProgressBar");
    if (!progressBar) return;
    const target = progressBar.getAttribute("data-progress") || "0";
    progressBar.style.width = "0%";
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        progressBar.style.width = target + "%";
      });
    });
  }

  function activateTabBySelector(selector) {
    if (!selector || typeof bootstrap === "undefined") return;
    const trigger = document.querySelector(
      '[data-bs-target="' + selector + '"]',
    );
    if (!trigger) return;
    bootstrap.Tab.getOrCreateInstance(trigger).show();
  }

  function resolveInitialTab() {
    const hash = window.location.hash;
    if (hash && document.querySelector(hash)) return hash;
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored && document.querySelector(stored)) return stored;
    return "#v-pills-membership";
  }

  const tabTriggers = document.querySelectorAll('[data-bs-toggle="pill"]');
  tabTriggers.forEach(function (trigger) {
    trigger.addEventListener("shown.bs.tab", function (event) {
      const targetSelector = event.target.getAttribute("data-bs-target");
      if (!targetSelector) return;

      localStorage.setItem(STORAGE_KEY, targetSelector);
      history.replaceState(null, "", targetSelector);

      const pane = document.querySelector(targetSelector);
      if (pane) {
        pane.classList.add("tab-pane-enter");
        setTimeout(function () {
          pane.classList.remove("tab-pane-enter");
        }, 380);
      }

      if (targetSelector === "#v-pills-membership") {
        animateMembershipProgress();
      }
    });
  });

  activateTabBySelector(resolveInitialTab());

  window.addEventListener("hashchange", function () {
    const hash = window.location.hash;
    if (hash && document.querySelector(hash)) {
      activateTabBySelector(hash);
    }
  });
});

// ── Hủy lịch hẹn (global scope — được gọi từ onclick trong HTML) ────────────
function cancelAppointment(maLichHen) {
  if (!confirm("Bạn có chắc muốn hủy lịch hẹn này không?")) return;

  const btn = document.querySelector(
    '[onclick="cancelAppointment(' + maLichHen + ')"]',
  );
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Đang hủy...";
  }

  const apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
  fetch(apiBase + "/api/lichhens/" + maLichHen + "/cancel", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (result) {
      if (result.status === 200) {
        location.reload();
      } else {
        if (btn) {
          btn.disabled = false;
          btn.textContent = "Hủy lịch";
        }
        alert(result.message || "Không thể hủy lịch hẹn. Vui lòng thử lại.");
      }
    })
    .catch(function () {
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Hủy lịch";
      }
      alert("Lỗi kết nối. Vui lòng thử lại.");
    });
}

// ── Xem chi tiết Phiếu Khám ─────────────────────────────────────────────────
var _currentPkId = null;
var _currentPkData = null;

function viewPhieuKham(maPhieuKham) {
  _currentPkId = maPhieuKham;
  _currentPkData = null;

  var modalEl = document.getElementById("modalPhieuKham");
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  var body = document.getElementById("pkModalBody");
  var footer = document.getElementById("pkModalFooter");
  var btnReview = document.getElementById("btnOpenReview");

  body.innerHTML =
    '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-muted mt-3">Đang tải dữ liệu...</p></div>';
  footer.style.display = "none";
  btnReview.style.display = "none";
  modal.show();

  var apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
  fetch(apiBase + "/api/phieukham/" + maPhieuKham)
    .then(function (r) {
      return r.json();
    })
    .then(function (result) {
      if (result.status !== 200 || !result.data) {
        body.innerHTML =
          '<div class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><p class="mt-2">' +
          (result.message || "Không thể tải dữ liệu phiếu khám.") +
          "</p></div>";
        footer.style.display = "flex";
        return;
      }

      _currentPkData = result.data;
      renderPhieuKhamDetail(result.data);
      footer.style.display = "flex";

      // Kiểm tra đã đánh giá chưa
      if (parseInt(result.data.TrangThai) === 1) {
        fetch(apiBase + "/api/danhgia/check/" + maPhieuKham)
          .then(function (r2) {
            return r2.json();
          })
          .then(function (r2) {
            if (r2.status === 200 && r2.data) {
              if (r2.data.hasRated) {
                btnReview.style.display = "none";
                appendExistingReview(r2.data.review);
              } else {
                btnReview.style.display = "inline-flex";
              }
            }
          })
          .catch(function () {});
      }
    })
    .catch(function () {
      body.innerHTML =
        '<div class="text-center py-4 text-danger"><i class="bi bi-wifi-off" style="font-size:2rem"></i><p class="mt-2">Lỗi kết nối. Vui lòng thử lại.</p></div>';
      footer.style.display = "flex";
    });
}

function renderPhieuKhamDetail(pk) {
  var body = document.getElementById("pkModalBody");
  var statusMap = {
    0: { label: "Chờ xử lý", cls: "status-pending" },
    1: { label: "Đã hoàn thành", cls: "status-completed" },
    2: { label: "Đã hủy", cls: "status-cancelled" },
  };
  var st = statusMap[parseInt(pk.TrangThai)] || statusMap[0];
  var ngay = pk.NgayKham
    ? new Date(pk.NgayKham).toLocaleDateString("vi-VN")
    : "—";
  var ngayTaiKham = pk.NgayTaiKham
    ? new Date(pk.NgayTaiKham).toLocaleDateString("vi-VN")
    : null;

  var html = "";

  // Phần thông tin chung
  html += '<div class="pk-detail-section">';
  html +=
    '<h6><i class="bi bi-file-earmark-medical me-2"></i>Thông tin phiếu khám</h6>';
  html +=
    '<div class="pk-info-row"><span class="pk-info-label">Mã phiếu:</span><span class="pk-info-value">#' +
    pk.MaPhieuKham +
    "</span></div>";
  html +=
    '<div class="pk-info-row"><span class="pk-info-label">Ngày khám:</span><span class="pk-info-value">' +
    ngay +
    "</span></div>";
  html +=
    '<div class="pk-info-row"><span class="pk-info-label">Trạng thái:</span><span class="status-badge ' +
    st.cls +
    '">' +
    st.label +
    "</span></div>";
  if (pk.TenBacSi) {
    html +=
      '<div class="pk-info-row"><span class="pk-info-label">Bác sĩ:</span><span class="pk-info-value">' +
      escHtml(pk.TenBacSi) +
      "</span></div>";
  }
  if (pk.TrieuChung) {
    html +=
      '<div class="pk-info-row"><span class="pk-info-label">Triệu chứng:</span><span class="pk-info-value">' +
      escHtml(pk.TrieuChung) +
      "</span></div>";
  }
  if (pk.ChanDoan) {
    html +=
      '<div class="pk-info-row"><span class="pk-info-label">Chẩn đoán:</span><span class="pk-info-value fw-bold">' +
      escHtml(pk.ChanDoan) +
      "</span></div>";
  }
  if (ngayTaiKham) {
    html +=
      '<div class="pk-info-row"><span class="pk-info-label">Tái khám:</span><span class="pk-info-value text-primary">' +
      ngayTaiKham +
      "</span></div>";
  }
  if (pk.GhiChu) {
    html +=
      '<div class="pk-info-row"><span class="pk-info-label">Ghi chú:</span><span class="pk-info-value text-muted">' +
      escHtml(pk.GhiChu) +
      "</span></div>";
  }
  html += "</div>";

  // Danh sách dịch vụ
  var svcs = pk.services || [];
  if (svcs.length > 0) {
    html += '<div class="pk-detail-section">';
    html +=
      '<h6><i class="bi bi-heart-pulse me-2"></i>Dịch vụ (' +
      svcs.length +
      ")</h6>";
    html += '<table class="pk-detail-table">';
    html +=
      "<thead><tr><th>Dịch vụ</th><th class='text-center'>SL</th><th class='text-end'>Thành tiền</th></tr></thead><tbody>";
    var tongDV = 0;
    for (var i = 0; i < svcs.length; i++) {
      var s = svcs[i];
      var tt = parseFloat(s.ThanhTien || 0);
      tongDV += tt;
      html +=
        "<tr><td>" +
        escHtml(s.TenDichVu || "Dịch vụ #" + s.MaDichVu) +
        "</td><td class='text-center'>" +
        (s.SoLuong || 1) +
        "</td><td class='text-end'>" +
        formatVND(tt) +
        "</td></tr>";
    }
    html +=
      '</tbody><tfoot><tr><th colspan="2">Tổng dịch vụ</th><th class="text-end text-primary">' +
      formatVND(tongDV) +
      "</th></tr></tfoot></table>";
    html += "</div>";
  }

  // Danh sách thuốc
  var meds = pk.medicines || [];
  if (meds.length > 0) {
    html += '<div class="pk-detail-section">';
    html +=
      '<h6><i class="bi bi-capsule me-2"></i>Đơn thuốc (' +
      meds.length +
      ")</h6>";
    html += '<table class="pk-detail-table">';
    html +=
      "<thead><tr><th>Thuốc</th><th class='text-center'>SL</th><th>Liều dùng</th><th class='text-end'>Thành tiền</th></tr></thead><tbody>";
    var tongThuoc = 0;
    for (var j = 0; j < meds.length; j++) {
      var m = meds[j];
      var medTT = parseFloat(m.ThanhTien || 0);
      tongThuoc += medTT;
      html +=
        "<tr><td>" +
        escHtml(m.TenThuoc || "Thuốc #" + m.MaThuoc) +
        (m.DonViTinh
          ? " <small class='text-muted'>(" + escHtml(m.DonViTinh) + ")</small>"
          : "") +
        "</td><td class='text-center'>" +
        (m.SoLuong || 1) +
        "</td><td>" +
        escHtml(m.LieuDung || "—") +
        "</td><td class='text-end'>" +
        formatVND(medTT) +
        "</td></tr>";
    }
    html +=
      '</tbody><tfoot><tr><th colspan="3">Tổng đơn thuốc</th><th class="text-end text-primary">' +
      formatVND(tongThuoc) +
      "</th></tr></tfoot></table>";
    html += "</div>";
  }

  // Tổng hóa đơn
  var hd = pk.hoaDon;
  if (hd) {
    var tongDichVuHD = parseFloat(hd.TongTienDichVu || 0);
    var tongThuocHD = parseFloat(hd.TongThuoc || 0);
    var giamGia = parseFloat(hd.GiamGia || 0);
    var tongTien = parseFloat(hd.TongTien || 0);
    var trangThaiHD = parseInt(hd.TrangThai);
    var pttt = hd.PhuongThucThanhToan || "—";

    html +=
      '<div class="pk-detail-section" style="border-color: rgba(15,92,77,0.25); background: linear-gradient(160deg, #fff, rgba(209,233,226,0.25));">';
    html += '<h6><i class="bi bi-receipt me-2"></i>Tổng hóa đơn</h6>';
    html += '<table class="pk-detail-table">';
    html += "<tbody>";
    html +=
      '<tr><td>Tổng dịch vụ</td><td class="text-end">' +
      formatVND(tongDichVuHD) +
      "</td></tr>";
    html +=
      '<tr><td>Tổng đơn thuốc</td><td class="text-end">' +
      formatVND(tongThuocHD) +
      "</td></tr>";
    if (giamGia > 0) {
      html +=
        '<tr class="text-success"><td><i class="bi bi-tag me-1"></i>Giảm giá</td><td class="text-end">-' +
        formatVND(giamGia) +
        "</td></tr>";
    }
    html += "</tbody>";
    html += "<tfoot>";
    html +=
      '<tr style="border-top: 2px solid rgba(15,92,77,0.2);"><th class="fs-6">Tổng thanh toán</th><th class="text-end fs-6 text-primary">' +
      formatVND(tongTien) +
      "</th></tr>";
    html += "</tfoot></table>";
    html +=
      '<div class="d-flex justify-content-between align-items-center mt-2 small">';
    html +=
      '<span class="text-muted"><i class="bi bi-credit-card me-1"></i>' +
      escHtml(pttt) +
      "</span>";
    html +=
      '<span class="' +
      (trangThaiHD === 1 ? "text-success" : "text-warning") +
      ' fw-bold">';
    html +=
      '<i class="bi ' +
      (trangThaiHD === 1 ? "bi-check-circle-fill" : "bi-clock") +
      ' me-1"></i>';
    html += trangThaiHD === 1 ? "Đã thanh toán" : "Chưa thanh toán";
    html += "</span></div>";
    html += "</div>";
  }

  // Không có dịch vụ & thuốc (phiếu đang chờ)
  if (svcs.length === 0 && meds.length === 0 && parseInt(pk.TrangThai) === 0) {
    html +=
      '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split" style="font-size:1.5rem"></i><p class="mt-2 mb-0">Phiếu khám đang chờ xử lý. Kết quả sẽ được cập nhật sau khi hoàn thành.</p></div>';
  }

  // Vùng hiển thị đánh giá
  html += '<div id="existingReviewContainer"></div>';

  body.innerHTML = html;
}

function appendExistingReview(review) {
  var container = document.getElementById("existingReviewContainer");
  if (!container || !review) return;

  var stars = "";
  for (var i = 1; i <= 5; i++) {
    stars +=
      '<i class="bi ' +
      (i <= review.DiemDanh ? "bi-star-fill" : "bi-star") +
      '"></i>';
  }

  container.innerHTML =
    '<div class="pk-detail-section review-existing">' +
    '<h6><i class="bi bi-chat-square-heart me-2"></i>Đánh giá của bạn</h6>' +
    '<div class="d-flex align-items-center gap-3 mb-2">' +
    '<span class="review-stars">' +
    stars +
    "</span>" +
    '<span class="fw-bold">' +
    review.DiemDanh +
    "/5</span>" +
    '<span class="text-muted small ms-auto">' +
    (review.NgayDanhGia
      ? new Date(review.NgayDanhGia).toLocaleDateString("vi-VN")
      : "") +
    "</span></div>" +
    (review.NhanXet
      ? '<p class="mb-0 text-muted">' + escHtml(review.NhanXet) + "</p>"
      : "") +
    "</div>";
}

// ── Đánh giá dịch vụ ─────────────────────────────────────────────────────────
var _selectedStars = 0;

function openReviewModal() {
  _selectedStars = 0;
  document.getElementById("reviewComment").value = "";
  var feedback = document.getElementById("reviewFeedback");
  feedback.classList.add("d-none");

  var title = document.getElementById("reviewPkTitle");
  if (_currentPkData && _currentPkData.ChanDoan) {
    title.textContent = "Đánh giá cho: " + _currentPkData.ChanDoan;
  } else {
    title.textContent = "Đánh giá phiếu khám #" + _currentPkId;
  }

  // Đặt lại sao về mặc định
  var starBtns = document.querySelectorAll("#starRating .star-btn");
  starBtns.forEach(function (s) {
    s.classList.remove("active");
    s.classList.replace("bi-star-fill", "bi-star");
  });
  document.getElementById("starLabel").textContent = "Chọn số sao đánh giá";

  var modal = bootstrap.Modal.getOrCreateInstance(
    document.getElementById("modalDanhGia"),
  );
  modal.show();
}

// Xử lý sự kiện click chọn sao
document.addEventListener("click", function (e) {
  var star = e.target.closest(".star-btn");
  if (!star) return;

  var val = parseInt(star.getAttribute("data-value"));
  _selectedStars = val;

  var labels = [
    "",
    "Rất không hài lòng",
    "Không hài lòng",
    "Bình thường",
    "Hài lòng",
    "Rất hài lòng",
  ];
  document.getElementById("starLabel").textContent = labels[val] || "";

  var allStars = document.querySelectorAll("#starRating .star-btn");
  allStars.forEach(function (s) {
    var sv = parseInt(s.getAttribute("data-value"));
    if (sv <= val) {
      s.classList.add("active");
      s.classList.replace("bi-star", "bi-star-fill");
    } else {
      s.classList.remove("active");
      s.classList.replace("bi-star-fill", "bi-star");
    }
  });
});

function submitReview() {
  if (_selectedStars < 1) {
    showReviewFeedback("Vui lòng chọn số sao đánh giá.", "warning");
    return;
  }

  var comment = document.getElementById("reviewComment").value.trim();
  var btn = document.getElementById("btnSubmitReview");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi...';

  var apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
  fetch(apiBase + "/api/danhgia", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      maPhieuKham: _currentPkId,
      diemDanh: _selectedStars,
      nhanXet: comment || null,
    }),
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (result) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-2"></i>Gửi đánh giá';

      if (result.status === 200 || result.status === 201) {
        // Đóng modal đánh giá
        bootstrap.Modal.getInstance(
          document.getElementById("modalDanhGia"),
        ).hide();

        // Cập nhật modal chi tiết
        var btnReview = document.getElementById("btnOpenReview");
        if (btnReview) btnReview.style.display = "none";
        appendExistingReview({
          DiemDanh: _selectedStars,
          NhanXet: comment,
          NgayDanhGia: new Date().toISOString(),
        });
      } else {
        showReviewFeedback(
          result.message || "Không thể gửi đánh giá. Vui lòng thử lại.",
          "danger",
        );
      }
    })
    .catch(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-2"></i>Gửi đánh giá';
      showReviewFeedback("Lỗi kết nối. Vui lòng thử lại.", "danger");
    });
}

function showReviewFeedback(msg, type) {
  var el = document.getElementById("reviewFeedback");
  el.className = "alert alert-" + type;
  el.textContent = msg;
  el.classList.remove("d-none");
}

function escHtml(str) {
  if (!str) return "";
  var div = document.createElement("div");
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

function formatVND(num) {
  return new Intl.NumberFormat("vi-VN").format(num) + "đ";
}
