/**
 * CSRF Token Helper
 * Tự động thêm header X-CSRF-Token vào mọi fetch request (POST/PUT/DELETE)
 */
(function () {
  const meta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = meta ? meta.getAttribute("content") : "";

  // Override fetch để tự động thêm CSRF token
  const originalFetch = window.fetch;
  window.fetch = function (url, options) {
    options = options || {};
    const method = (options.method || "GET").toUpperCase();

    // Chỉ thêm CSRF token cho các request thay đổi dữ liệu
    if (["POST", "PUT", "DELETE", "PATCH"].includes(method)) {
      options.headers = options.headers || {};

      // Nếu headers là Headers object, dùng set()
      if (options.headers instanceof Headers) {
        if (!options.headers.has("X-CSRF-Token")) {
          options.headers.set("X-CSRF-Token", csrfToken);
        }
      } else {
        // Plain object
        if (!options.headers["X-CSRF-Token"]) {
          options.headers["X-CSRF-Token"] = csrfToken;
        }
      }
    }

    return originalFetch.call(this, url, options);
  };
})();
