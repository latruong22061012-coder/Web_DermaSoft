document.addEventListener("DOMContentLoaded", function () {
  // 1. Kích hoạt hiệu ứng Scroll Animation (AOS)
  AOS.init({
    once: true, // Hiệu ứng chỉ chạy 1 lần khi cuộn xuống
    offset: 80, // Bắt đầu hiệu ứng cách viền dưới màn hình 80px
    duration: 800, // Thời gian hiệu ứng (0.8s)
    easing: "ease-out-cubic",
  });

  // 2. Hiệu ứng thu gọn và đổ bóng Navbar khi cuộn trang
  const navbar = document.getElementById("mainNav");

  window.addEventListener("scroll", function () {
    if (window.scrollY > 50) {
      navbar.classList.add("scrolled");
    } else {
      navbar.classList.remove("scrolled");
    }
  });

  // 3. (Mới) Tự động đóng Menu Mobile khi bấm vào link
  const navLinks = document.querySelectorAll(".navbar-nav .nav-link");
  const menuToggle = document.getElementById("navbarResponsive");

  // Kiểm tra xem Bootstrap có sẵn sàng chưa
  if (typeof bootstrap !== "undefined") {
    const bsCollapse = new bootstrap.Collapse(menuToggle, { toggle: false });

    navLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        // Nếu menu đang mở (có class 'show'), thì đóng lại
        if (menuToggle.classList.contains("show")) {
          bsCollapse.toggle();
        }
      });
    });
  }

  // ── Booking Form ──────────────────────────────────────────────────────────
  const bookingForm = document.getElementById("bookingForm");
  const bookingFeedback = document.getElementById("bookingFeedback");
  const bookingDateInput = document.getElementById("bookingDate");
  const bookingTimeInput = document.getElementById("bookingTime");
  const bookingSubmitBtn = document.getElementById("bookingSubmitBtn");

  // Set min / max dates (today → +60 days); default to tomorrow
  if (bookingDateInput) {
    const today = new Date();
    const maxDay = new Date(today);
    maxDay.setDate(maxDay.getDate() + 60);
    const toISO = (d) => d.toISOString().split("T")[0];
    bookingDateInput.min = toISO(today);
    bookingDateInput.max = toISO(maxDay);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    bookingDateInput.value = toISO(tomorrow);
  }

  function showBookingFeedback(msg, type) {
    if (!bookingFeedback) return;
    bookingFeedback.className = "alert alert-" + type + " mb-4";
    bookingFeedback.textContent = msg;
    bookingFeedback.classList.remove("d-none");
    bookingFeedback.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  if (bookingForm) {
    bookingForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const nameEl = document.getElementById("bookingName");
      const phoneEl = document.getElementById("bookingPhone");
      const noteEl = document.getElementById("bookingNote");

      // Client-side validation
      let valid = true;

      if (!nameEl.value.trim() || nameEl.value.trim().length < 3) {
        nameEl.classList.add("is-invalid");
        valid = false;
      } else {
        nameEl.classList.remove("is-invalid");
      }

      const phoneRgx = /^(0)(3[2-9]|5[25689]|7[06-9]|8[0-9]|9[0-9])\d{7}$/;
      if (!phoneRgx.test(phoneEl.value.replace(/\s/g, ""))) {
        phoneEl.classList.add("is-invalid");
        valid = false;
      } else {
        phoneEl.classList.remove("is-invalid");
      }

      if (!bookingDateInput.value) {
        bookingDateInput.classList.add("is-invalid");
        valid = false;
      } else {
        bookingDateInput.classList.remove("is-invalid");
      }

      if (!bookingTimeInput.value) {
        bookingTimeInput.classList.add("is-invalid");
        valid = false;
      } else {
        bookingTimeInput.classList.remove("is-invalid");
      }

      if (!valid) return;

      // Disable button while submitting
      bookingSubmitBtn.disabled = true;
      bookingSubmitBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang gửi...';

      const apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
      fetch(apiBase + "/api/booking/create", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          hoTen: nameEl.value.trim(),
          soDienThoai: phoneEl.value.replace(/\s/g, ""),
          thoiGianHen: bookingDateInput.value + " " + bookingTimeInput.value,
          ghiChu: noteEl && noteEl.value.trim() ? noteEl.value.trim() : null,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (result) {
          bookingSubmitBtn.disabled = false;
          bookingSubmitBtn.innerHTML =
            '<i class="bi bi-calendar-check me-2"></i>GỬI YÊU CẦU ĐẶT LỊCH';

          if (result.status === 200 || result.status === 201) {
            bookingForm.reset();
            // Restore default date after reset
            if (bookingDateInput) {
              const t = new Date();
              t.setDate(t.getDate() + 1);
              bookingDateInput.value = t.toISOString().split("T")[0];
            }
            // Khôi phục lại giá trị readonly nếu user đã đăng nhập
            if (nameEl.hasAttribute("readonly") && window._BOOKING_USER_NAME) {
              nameEl.value = window._BOOKING_USER_NAME;
              phoneEl.value = window._BOOKING_USER_PHONE;
            }
            showBookingFeedback("✅ " + result.message, "success");
          } else if (result.data && result.data.requireLogin) {
            // SĐT thuộc tài khoản đã đăng ký → hiện nút đăng nhập
            if (!bookingFeedback) return;
            bookingFeedback.className = "alert alert-warning mb-4";
            bookingFeedback.innerHTML =
              '<i class="bi bi-shield-exclamation me-2"></i>' +
              result.message +
              ' <a href="index.php?route=login" class="alert-link fw-bold">Đăng nhập ngay</a>';
            bookingFeedback.classList.remove("d-none");
            bookingFeedback.scrollIntoView({
              behavior: "smooth",
              block: "nearest",
            });
          } else {
            showBookingFeedback(
              result.message || "Lỗi đặt lịch. Vui lòng thử lại.",
              "danger",
            );
          }
        })
        .catch(function () {
          bookingSubmitBtn.disabled = false;
          bookingSubmitBtn.innerHTML =
            '<i class="bi bi-calendar-check me-2"></i>GỬI YÊU CẦU ĐẶT LỊCH';
          showBookingFeedback("Lỗi kết nối. Vui lòng thử lại sau.", "danger");
        });
    });
  }
});
