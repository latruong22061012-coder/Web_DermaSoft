/**
 * ============================================================
 * OTP/EMAIL API HANDLER JAVASCRIPT
 * ============================================================
 *
 * Xử lý tất cả 7 API endpoints cho OTP/Email authentication
 * Tiếp hợp: login, register, forgot-password, test page
 *
 * Ngày tạo: 23/03/2026
 * Phiên bản: 1.0 Production
 * Ngôn ngữ: 100% Tiếng Việt
 *
 * ============================================================
 */

// ============================================================
// CONFIG
// ============================================================

// Tự động nhận diện base path để hỗ trợ cả /DarmaSoft/ và /
(function () {
  var path = window.location.pathname;
  // Nếu URL bắt đầu bằng /DarmaSoft thì dùng /DarmaSoft làm prefix
  // Ngược lại dùng rỗng (chạy từ root)
  if (path.startsWith("/DarmaSoft")) {
    window._API_BASE_PATH = "/DarmaSoft";
  } else {
    window._API_BASE_PATH = "";
  }
})();

const OTP_API = {
  get baseUrl() {
    return window._API_BASE_PATH + "/api/auth";
  },
  endpoints: {
    checkPhone: "/check-phone",
    sendOtpLogin: "/send-otp-login",
    loginWithOtp: "/login-with-otp",
    registerPhone: "/register-phone",
    forgotPhone: "/forgot-phone",
    updatePhone: "/update-phone",
    verifyEmailToken: "/verify-email-token",
    sendOtpPhoneReset: "/send-otp-phone-reset",
    resetPhoneWithOtp: "/reset-phone-with-otp",
  },
  timeout: 10000, // 10 seconds
};

// ============================================================
// 1. CHECK PHONE - Kiểm tra số điện thoại
// ============================================================

async function checkPhone(sodienthoai) {
  console.log("🔍 Checking phone:", sodienthoai);

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.checkPhone,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          sodienthoai: sodienthoai,
        }),
      },
    );

    const result = await response.json();

    console.log("✅ Check phone result:", result);

    return {
      success: response.ok,
      data: result.data,
      message: result.message,
      httpCode: response.status,
    };
  } catch (error) {
    console.error("❌ Check phone error:", error);
    return {
      success: false,
      error: error.message,
      message: "Lỗi kết nối: " + error.message,
    };
  }
}

// ============================================================
// 2. SEND OTP LOGIN - Gửi OTP đăng nhập (sử dụng số điện thoại)
// ============================================================

async function sendOtpLogin(sodienthoai) {
  console.log("📱 Sending OTP to phone:", sodienthoai);

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.sendOtpLogin,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          sodienthoai: sodienthoai,
        }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Không thể gửi OTP");
    }

    console.log("✅ OTP sent successfully:", result);

    return {
      success: true,
      data: result.data,
      message: result.message,
      expiresIn: result.data?.expires_in || 300,
      httpCode: response.status,
    };
  } catch (error) {
    console.error("❌ Send OTP error:", error);
    return {
      success: false,
      error: error.message,
      message: error.message,
    };
  }
}
// ============================================================
// 3. LOGIN WITH OTP - Đăng nhập bằng OTP (sử dụng số điện thoại)
// ============================================================

async function loginWithOtp(sodienthoai, otp) {
  console.log("🔓 Login with OTP - Phone:", sodienthoai);

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.loginWithOtp,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          sodienthoai: sodienthoai,
          otp: otp,
        }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "OTP không đúng");
    }

    console.log("✅ Login successful:", result);

    // Lưu token vào localStorage
    if (result.data?.token) {
      localStorage.setItem("accessToken", result.data.token);
      localStorage.setItem("user", JSON.stringify(result.data.user));
    }

    return {
      success: true,
      data: result.data,
      message: result.message,
      token: result.data?.token,
      user: result.data?.user,
      httpCode: response.status,
    };
  } catch (error) {
    console.error("❌ Login error:", error);
    return {
      success: false,
      error: error.message,
      message: error.message,
    };
  }
}

// ============================================================
// 4. REGISTER PHONE - Đăng ký tài khoản mới
// ============================================================

async function registerPhone(hoTen, sodienthoai, email, matkhau) {
  console.log("👤 Registering new account:", email);

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.registerPhone,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          hoTen: hoTen,
          sodienthoai: sodienthoai,
          email: email,
          matkhau: matkhau,
        }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Đăng ký thất bại");
    }

    console.log("✅ Registration successful:", result);

    return {
      success: true,
      data: result.data,
      message: result.message,
      userId: result.data?.user_id,
      verifyToken: result.data?.verify_token,
      httpCode: response.status,
    };
  } catch (error) {
    console.error("❌ Registration error:", error);
    return {
      success: false,
      error: error.message,
      message: error.message,
    };
  }
}

// ============================================================
// 5. FORGOT PHONE - Lấy lại số điện thoại
// ============================================================

async function forgotPhone(email) {
  console.log("🔄 Forgot phone recovery:", email);

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.forgotPhone,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          email: email,
        }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Không thể xử lý yêu cầu");
    }

    console.log("✅ Forgot phone request processed:", result);

    return {
      success: true,
      data: result.data,
      message: result.message,
      httpCode: response.status,
    };
  } catch (error) {
    console.error("❌ Forgot phone error:", error);
    return {
      success: false,
      error: error.message,
      message: error.message,
    };
  }
}

// ============================================================
// 6. UPDATE PHONE - Cập nhật số điện thoại + email
// ============================================================

async function updatePhone(sodienthoai_moi, email_moi, otp_confirm) {
  console.log("✏️ Updating phone:", sodienthoai_moi);

  const token = localStorage.getItem("accessToken");

  if (!token) {
    return {
      success: false,
      message: "Chưa đăng nhập. Vui lòng đăng nhập trước.",
    };
  }

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.updatePhone,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: "Bearer " + token,
        },
        body: JSON.stringify({
          sodienthoai_moi: sodienthoai_moi,
          email_moi: email_moi,
          otp_confirm: otp_confirm,
        }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Cập nhật thất bại");
    }

    console.log("✅ Phone updated successfully:", result);

    return {
      success: true,
      data: result.data,
      message: result.message,
      httpCode: response.status,
    };
  } catch (error) {
    console.error("❌ Update phone error:", error);
    return {
      success: false,
      error: error.message,
      message: error.message,
    };
  }
}

// ============================================================
// 7. VERIFY EMAIL TOKEN - Xác thực email token
// ============================================================

async function verifyEmailToken(token) {
  console.log("✔️ Verifying email token");

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.verifyEmailToken,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          token: token,
        }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Token không hợp lệ");
    }

    console.log("✅ Email verified successfully:", result);

    return {
      success: true,
      data: result.data,
      message: result.message,
      userId: result.data?.user_id,
      httpCode: response.status,
    };
  } catch (error) {
    console.error("❌ Email verification error:", error);
    return {
      success: false,
      error: error.message,
      message: error.message,
    };
  }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Format tiếng Việt kết quả API
 */
function formatApiResponse(result) {
  if (result.success) {
    return {
      icon: "✅",
      color: "success",
      title: "Thành công!",
      message: result.message,
    };
  } else {
    return {
      icon: "❌",
      color: "danger",
      title: "Lỗi!",
      message: result.message || "Có lỗi xảy ra",
    };
  }
}

/**
 * Hiển thị alert Sweetalert2/Bootstrap
 */
function showAlert(title, message, icon = "info") {
  const alertClass =
    {
      success: "alert-success",
      danger: "alert-danger",
      warning: "alert-warning",
      info: "alert-info",
    }[icon] || "alert-info";

  const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <strong>${title}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

  const container =
    document.querySelector("[data-alert-container]") ||
    document.querySelector("main") ||
    document.body;

  const alertDiv = document.createElement("div");
  alertDiv.innerHTML = alertHtml;

  const firstChild = container.firstChild;
  container.insertBefore(alertDiv.firstChild, firstChild);

  // Auto remove sau 5 giây
  setTimeout(() => {
    const alert = container.querySelector(".alert");
    if (alert) {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    }
  }, 5000);
}

/**
 * Validate email
 */
function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

/**
 * Validate phone (10-15 characters)
 */
function isValidPhone(phone) {
  const re = /^\d{10,15}$/;
  return re.test(phone);
}

/**
 * Validate OTP (6 digits)
 */
function isValidOtp(otp) {
  const re = /^\d{6}$/;
  return re.test(otp);
}

/**
 * Get OTP từ OTP input fields
 */
function getOtpValue(containerSelector = ".otp-inputs") {
  const inputs = document.querySelectorAll(containerSelector + " .otp-digit");
  return Array.from(inputs)
    .map((input) => input.value)
    .join("");
}

/**
 * Countdown timer cho OTP
 */
function startOtpCountdown(
  seconds = 300,
  onTickCallback = null,
  onExpireCallback = null,
) {
  let remaining = seconds;

  const interval = setInterval(() => {
    remaining--;

    if (onTickCallback) {
      onTickCallback(remaining);
    }

    if (remaining <= 0) {
      clearInterval(interval);
      if (onExpireCallback) {
        onExpireCallback();
      }
    }
  }, 1000);

  return interval;
}

/**
 * Format time display for countdown
 */
function formatCountdownTime(seconds) {
  const minutes = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${minutes}:${secs.toString().padStart(2, "0")}`;
}

/**
 * Logout user
 */
function logoutUser() {
  localStorage.removeItem("accessToken");
  localStorage.removeItem("user");
  window.location.href = "index.php?route=login";
}

/**
 * Get current logged in user
 */
function getCurrentUser() {
  const userJson = localStorage.getItem("user");
  if (userJson) {
    try {
      return JSON.parse(userJson);
    } catch (e) {
      return null;
    }
  }
  return null;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
  return !!localStorage.getItem("accessToken");
}

// ============================================================
// 8. SEND OTP PHONE RESET - Gửi OTP về email để đổi số điện thoại
// ============================================================

async function sendOtpPhoneReset(email) {
  console.log("📧 Sending OTP for phone reset to:", email);

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.sendOtpPhoneReset,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Không thể gửi OTP");
    }

    console.log("✅ OTP phone reset sent:", result);
    return {
      success: true,
      data: result.data,
      message: result.message,
    };
  } catch (error) {
    console.error("❌ sendOtpPhoneReset error:", error);
    return { success: false, message: error.message };
  }
}

// ============================================================
// 9. RESET PHONE WITH OTP - Đặt lại số điện thoại sau khi xác minh OTP
// ============================================================

async function resetPhoneWithOtp(email, otp, phone_moi) {
  console.log("📱 Resetting phone number for:", email);

  try {
    const response = await fetch(
      OTP_API.baseUrl + OTP_API.endpoints.resetPhoneWithOtp,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, otp, phone_moi }),
      },
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Không thể đổi số điện thoại");
    }

    console.log("✅ Phone reset successful:", result);
    return {
      success: true,
      data: result.data,
      message: result.message,
    };
  } catch (error) {
    console.error("❌ resetPhoneWithOtp error:", error);
    return { success: false, message: error.message };
  }
}

// ============================================================
// EXPORTS (for debugging)
// ============================================================

console.log("✅ OTP API Handler loaded successfully");
console.log("📍 Available functions:", {
  checkPhone,
  sendOtpLogin,
  loginWithOtp,
  registerPhone,
  forgotPhone,
  updatePhone,
  verifyEmailToken,
  sendOtpPhoneReset,
  resetPhoneWithOtp,
});
