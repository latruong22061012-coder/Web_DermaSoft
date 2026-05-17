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
  const bookingTimeHelp = document.getElementById("bookingTimeHelp");
  var bookingSlotsRefreshTimer = null;
  var bookingDoctorsRefreshTimer = null;
  var lastSelectedDoctorId = "";

  function isToday(isoDate) {
    if (!isoDate) {
      return false;
    }
    const now = new Date();
    return isoDate === now.toISOString().split("T")[0];
  }

  function clearSlotsRefreshTimer() {
    if (bookingSlotsRefreshTimer) {
      window.clearInterval(bookingSlotsRefreshTimer);
      bookingSlotsRefreshTimer = null;
    }
  }

  function clearDoctorsRefreshTimer() {
    if (bookingDoctorsRefreshTimer) {
      window.clearInterval(bookingDoctorsRefreshTimer);
      bookingDoctorsRefreshTimer = null;
    }
  }

  function startDoctorsRealtimeRefresh() {
    clearDoctorsRefreshTimer();

    bookingDoctorsRefreshTimer = window.setInterval(function () {
      if (!bookingDateInput || !isToday(bookingDateInput.value)) {
        return;
      }

      // Tự động làm mới danh sách bác sĩ đang trực theo thời gian thực.
      loadDoctorsByDate(true);
    }, 30000);
  }

  function startSlotsRealtimeRefresh() {
    clearSlotsRefreshTimer();

    bookingSlotsRefreshTimer = window.setInterval(function () {
      if (
        !lastSelectedDoctorId ||
        !bookingDateInput ||
        !isToday(bookingDateInput.value)
      ) {
        return;
      }

      // Tự động làm mới để đóng các giờ đã qua theo thời gian thực.
      loadAvailableSlots(lastSelectedDoctorId, true);
    }, 30000);
  }

  function resetBookingTimeOptions(placeholder) {
    if (!bookingTimeInput) {
      return;
    }

    bookingTimeInput.innerHTML = "";
    const option = document.createElement("option");
    option.value = "";
    option.textContent = placeholder;
    bookingTimeInput.appendChild(option);
    bookingTimeInput.value = "";
  }

  function setBookingTimeHelp(message, isError) {
    if (!bookingTimeHelp) {
      return;
    }

    bookingTimeHelp.textContent = message;
    bookingTimeHelp.classList.toggle("text-danger", !!isError);
    bookingTimeHelp.classList.toggle("text-muted", !isError);
  }

  function loadAvailableSlots(doctorId, silentRefresh) {
    const ngay = bookingDateInput ? bookingDateInput.value : "";
    const apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
    const previousSelection = bookingTimeInput ? bookingTimeInput.value : "";

    if (!doctorId || !ngay) {
      resetBookingTimeOptions("-- Chọn bác sĩ để xem giờ trống --");
      setBookingTimeHelp(
        "Khung giờ sẽ được lọc theo ca làm và các lịch đã đặt của bác sĩ.",
        false,
      );
      clearSlotsRefreshTimer();
      return;
    }

    if (!silentRefresh) {
      resetBookingTimeOptions("-- Đang tải giờ trống --");
      setBookingTimeHelp("Đang tải khung giờ khả dụng...", false);
    }

    fetch(
      apiBase +
        "/api/booking/slots?ngay=" +
        encodeURIComponent(ngay) +
        "&maNguoiDung=" +
        encodeURIComponent(doctorId),
    )
      .then(function (r) {
        return r.json();
      })
      .then(function (result) {
        const slots =
          result.data && Array.isArray(result.data.slots)
            ? result.data.slots
            : [];
        const shifts =
          result.data && Array.isArray(result.data.shifts)
            ? result.data.shifts
            : [];

        if (!slots.length) {
          resetBookingTimeOptions("-- Không còn giờ trống --");
          if (shifts.length) {
            const shiftLabels = shifts
              .map(function (shift) {
                return (
                  shift.tenCa +
                  " (" +
                  shift.gioBatDau +
                  " - " +
                  shift.gioKetThuc +
                  ")"
                );
              })
              .join(", ");
            setBookingTimeHelp(
              "Bác sĩ làm việc: " +
                shiftLabels +
                ". Hiện không còn khung giờ trống.",
              true,
            );
          } else {
            setBookingTimeHelp("Bác sĩ không có ca làm trong ngày này.", true);
          }
          return;
        }

        resetBookingTimeOptions("-- Chọn giờ --");
        slots.forEach(function (slot) {
          const option = document.createElement("option");
          option.value = slot.value;
          option.textContent =
            slot.label + (slot.caLam ? " - " + slot.caLam : "");
          bookingTimeInput.appendChild(option);
        });

        if (previousSelection) {
          bookingTimeInput.value = previousSelection;
          if (bookingTimeInput.value !== previousSelection) {
            bookingTimeInput.classList.remove("is-invalid");
          }
        }

        if (shifts.length) {
          const shiftLabels = shifts
            .map(function (shift) {
              return (
                shift.tenCa +
                " (" +
                shift.gioBatDau +
                " - " +
                shift.gioKetThuc +
                ")"
              );
            })
            .join(", ");
          setBookingTimeHelp(
            "Khung giờ khả dụng theo ca: " + shiftLabels + ".",
            false,
          );
        } else {
          setBookingTimeHelp(
            "Khung giờ sẽ được lọc theo ca làm và các lịch đã đặt của bác sĩ.",
            false,
          );
        }

        if (isToday(ngay)) {
          startSlotsRealtimeRefresh();
        } else {
          clearSlotsRefreshTimer();
        }
      })
      .catch(function () {
        resetBookingTimeOptions("-- Không tải được giờ trống --");
        setBookingTimeHelp("Không thể tải khung giờ. Vui lòng thử lại.", true);
      });
  }

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
    resetBookingTimeOptions("-- Chọn bác sĩ để xem giờ trống --");

    // Load doctors when date changes
    bookingDateInput.addEventListener("change", loadDoctorsByDate);
    // Load on initial date
    loadDoctorsByDate();
  }

  function loadDoctorsByDate(silentRefresh) {
    if (typeof silentRefresh === "undefined") {
      silentRefresh = false;
    }

    const ngay = bookingDateInput ? bookingDateInput.value : "";
    const container = document.getElementById("doctorListContainer");
    const loading = document.getElementById("doctorListLoading");
    const empty = document.getElementById("doctorListEmpty");
    const cards = document.getElementById("doctorCards");
    const hiddenInput = document.getElementById("bookingDoctor");
    const invalidMsg = document.getElementById("doctorInvalid");
    const previousDoctorId = lastSelectedDoctorId;
    var shouldReloadSlotsForSelectedDoctor = false;

    if (!ngay || !container) return;

    container.style.display = "";
    if (!silentRefresh) {
      loading.classList.remove("d-none");
    }
    empty.classList.add("d-none");
    cards.innerHTML = "";
    hiddenInput.value = "";
    lastSelectedDoctorId = "";
    clearSlotsRefreshTimer();
    if (isToday(ngay)) {
      startDoctorsRealtimeRefresh();
    } else {
      clearDoctorsRefreshTimer();
    }
    resetBookingTimeOptions("-- Chọn bác sĩ để xem giờ trống --");
    setBookingTimeHelp(
      "Khung giờ sẽ được lọc theo ca làm và các lịch đã đặt của bác sĩ.",
      false,
    );
    if (invalidMsg) invalidMsg.classList.add("d-none");

    const apiBase = (window._API_BASE_PATH || "").replace(/\/$/, "");
    fetch(apiBase + "/api/booking/doctors?ngay=" + encodeURIComponent(ngay))
      .then(function (r) {
        return r.json();
      })
      .then(function (result) {
        loading.classList.add("d-none");
        if (
          !result.data ||
          !result.data.doctors ||
          result.data.doctors.length === 0
        ) {
          empty.innerHTML =
            '<i class="bi bi-info-circle me-1"></i>Không có bác sĩ nào làm việc vào ngày này.';
          empty.classList.remove("d-none");
          return;
        }
        var limit = result.data.gioiHanBN || 8;
        var doctors = result.data.doctors;

        // Group shifts by doctor
        var grouped = {};
        doctors.forEach(function (d) {
          if (!grouped[d.MaNguoiDung]) {
            grouped[d.MaNguoiDung] = {
              hoTen: d.HoTen,
              maNguoiDung: d.MaNguoiDung,
              soBN: parseInt(d.SoBN) || 0,
              shifts: [],
            };
          }
          grouped[d.MaNguoiDung].shifts.push({
            tenCa: d.TenCa,
            gioBatDau: (d.GioBatDau || "").substring(0, 5),
            gioKetThuc: (d.GioKetThuc || "").substring(0, 5),
            daKetThuc: !!d.DaKetThuc,
          });
        });

        Object.keys(grouped).forEach(function (key) {
          var doc = grouped[key];
          var isFull = doc.soBN >= limit;
          var allShiftsEnded =
            doc.shifts.length > 0 &&
            doc.shifts.every(function (s) {
              return s.daKetThuc;
            });
          var disabled = isFull || allShiftsEnded;
          var pct = Math.min(Math.round((doc.soBN / limit) * 100), 100);
          var barColor =
            pct >= 100 ? "bg-danger" : pct >= 75 ? "bg-warning" : "bg-success";

          var shiftsHtml = doc.shifts
            .map(function (s) {
              if (s.daKetThuc) {
                return (
                  '<span class="badge bg-secondary bg-opacity-75 me-1 mb-1 text-decoration-line-through" title="Hết ca">' +
                  '<i class="bi bi-clock-history me-1"></i>' +
                  s.tenCa +
                  " (" +
                  s.gioBatDau +
                  " - " +
                  s.gioKetThuc +
                  ') <span class="text-decoration-none ms-1">· Hết ca</span></span>'
                );
              }
              return (
                '<span class="badge bg-primary bg-opacity-75 me-1 mb-1"><i class="bi bi-clock me-1"></i>' +
                s.tenCa +
                " (" +
                s.gioBatDau +
                " - " +
                s.gioKetThuc +
                ")</span>"
              );
            })
            .join("");

          var statusBadge;
          if (allShiftsEnded && !isFull) {
            statusBadge = '<span class="badge bg-secondary">Hết ca</span>';
          } else if (isFull) {
            statusBadge = '<span class="badge bg-danger">Đã đầy</span>';
          } else {
            statusBadge =
              '<span class="badge bg-success">' +
              doc.soBN +
              "/" +
              limit +
              " BN</span>";
          }

          var col = document.createElement("div");
          col.className = "col-md-6";
          col.innerHTML =
            '<div class="card border' +
            (disabled
              ? " border-secondary bg-light"
              : " border-primary border-opacity-50") +
            " doctor-card" +
            (disabled ? " opacity-50" : "") +
            '" ' +
            'data-doctor="' +
            doc.maNguoiDung +
            '" ' +
            'style="cursor:' +
            (disabled ? "not-allowed" : "pointer") +
            '">' +
            '<div class="card-body py-2 px-3">' +
            '<div class="d-flex justify-content-between align-items-center mb-1">' +
            '<span class="fw-bold"><i class="bi bi-person-circle me-1 text-primary"></i>' +
            doc.hoTen +
            "</span>" +
            statusBadge +
            "</div>" +
            '<div class="mb-1">' +
            shiftsHtml +
            "</div>" +
            '<div class="progress" style="height:5px;">' +
            '<div class="progress-bar ' +
            barColor +
            '" style="width:' +
            pct +
            '%"></div>' +
            "</div>" +
            "</div>" +
            "</div>";

          var doctorCard = col.querySelector(".doctor-card");

          if (!disabled) {
            doctorCard.addEventListener("click", function () {
              // Deselect all
              cards.querySelectorAll(".doctor-card").forEach(function (c) {
                c.classList.remove("border-primary", "border-3", "shadow-sm");
                c.classList.add("border-opacity-50");
              });
              // Select this
              this.classList.add("border-primary", "border-3", "shadow-sm");
              this.classList.remove("border-opacity-50");
              hiddenInput.value = doc.maNguoiDung;
              lastSelectedDoctorId = String(doc.maNguoiDung || "");
              loadAvailableSlots(doc.maNguoiDung);
              if (invalidMsg) invalidMsg.classList.add("d-none");
            });

            if (String(doc.maNguoiDung) === String(previousDoctorId)) {
              doctorCard.classList.add(
                "border-primary",
                "border-3",
                "shadow-sm",
              );
              doctorCard.classList.remove("border-opacity-50");
              hiddenInput.value = doc.maNguoiDung;
              lastSelectedDoctorId = String(doc.maNguoiDung || "");
              shouldReloadSlotsForSelectedDoctor = true;
            }
          }

          cards.appendChild(col);
        });

        if (shouldReloadSlotsForSelectedDoctor && lastSelectedDoctorId) {
          loadAvailableSlots(lastSelectedDoctorId, true);
        }
      })
      .catch(function () {
        loading.classList.add("d-none");
        empty.classList.remove("d-none");
        empty.textContent = "Lỗi tải danh sách bác sĩ.";
      });
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

      // Validate doctor selection
      var doctorInput = document.getElementById("bookingDoctor");
      var doctorInvalid = document.getElementById("doctorInvalid");
      var doctorContainer = document.getElementById("doctorListContainer");
      if (
        doctorContainer &&
        doctorContainer.style.display !== "none" &&
        (!doctorInput || !doctorInput.value)
      ) {
        if (doctorInvalid) doctorInvalid.classList.remove("d-none");
        valid = false;
      } else {
        if (doctorInvalid) doctorInvalid.classList.add("d-none");
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
          maNguoiDung: document.getElementById("bookingDoctor")
            ? document.getElementById("bookingDoctor").value || null
            : null,
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
            resetBookingTimeOptions("-- Chọn bác sĩ để xem giờ trống --");
            lastSelectedDoctorId = "";
            clearSlotsRefreshTimer();
            clearDoctorsRefreshTimer();
            setBookingTimeHelp(
              "Khung giờ sẽ được lọc theo ca làm và các lịch đã đặt của bác sĩ.",
              false,
            );
            // Khôi phục lại giá trị readonly nếu user đã đăng nhập
            if (nameEl.hasAttribute("readonly") && window._BOOKING_USER_NAME) {
              nameEl.value = window._BOOKING_USER_NAME;
              phoneEl.value = window._BOOKING_USER_PHONE;
            }
            // Reload doctor list for new date
            loadDoctorsByDate();
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

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      return;
    }

    if (bookingDateInput && isToday(bookingDateInput.value)) {
      loadDoctorsByDate(true);
    }

    if (
      lastSelectedDoctorId &&
      bookingDateInput &&
      isToday(bookingDateInput.value)
    ) {
      loadAvailableSlots(lastSelectedDoctorId, true);
    }
  });
});
