<?php
/**
 * Base API Controller - Base class cho tất cả API endpoints
 * Cung cấp các phương thức tiêu chuẩn cho API responses
 */

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Config;

abstract class ApiController
{
    /**
     * Lưu các tham số từ router
     */
    private array $params = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Base constructor - các class con có thể override
    }

    // ═══ CSRF PROTECTION ═══

    /**
     * Tạo CSRF token và lưu vào session
     */
    public static function generateCsrfToken(): string
    {
        Auth::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Kiểm tra CSRF token từ header X-CSRF-Token
     * Gọi method này trong các API endpoint thay đổi dữ liệu (POST/PUT/DELETE)
     */
    protected function requireCsrf(): void
    {
        Auth::startSession();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->error('CSRF token không hợp lệ', null, 403);
        }
    }

    // ═══ RATE LIMITING ═══

    /**
     * Kiểm tra rate limit dựa trên IP + action key
     * @param string $action Tên hành động (ví dụ: 'login', 'otp_send')
     * @param int $maxAttempts Số lần tối đa
     * @param int $windowSeconds Thời gian reset (đơn vị giây)
     */
    protected function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 300): void
    {
        Auth::startSession();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'rate_limit_' . $action . '_' . md5($ip);

        $record = $_SESSION[$key] ?? null;
        $now = time();

        // Reset nếu hết thời gian window
        if (!$record || ($now - $record['start']) > $windowSeconds) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return;
        }

        // Tăng đếm
        $_SESSION[$key]['count']++;

        if ($_SESSION[$key]['count'] > $maxAttempts) {
            $retryAfter = $windowSeconds - ($now - $record['start']);
            $this->error(
                "Quá nhiều yêu cầu. Vui lòng thử lại sau {$retryAfter} giây.",
                null,
                429
            );
        }
    }

    /**
     * Định dạng chung cho API response
     */
    protected function response(int $status, string $message, $data = null, int $httpCode = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);

        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Phản hồi thành công (200 OK)
     */
    protected function success($data = null, string $message = 'Thành công', int $httpCode = 200): void
    {
        $this->response(200, $message, $data, $httpCode);
    }

    /**
     * Phản hồi lỗi (400 Bad Request)
     */
    protected function error(string $message, $data = null, int $httpCode = 400): void
    {
        $this->response(400, $message, $data, $httpCode);
    }

    /**
     * Phản hồi không tìm thấy (404 Not Found)
     */
    protected function notFound(string $message = 'Không tìm thấy'): void
    {
        $this->response(404, $message, null, 404);
    }

    /**
     * Phản hồi không được phép (403 Forbidden)
     */
    protected function forbidden(string $message = 'Không được phép'): void
    {
        $this->response(403, $message, null, 403);
    }

    /**
     * Phản hồi chưa xác thực (401 Unauthorized)
     */
    protected function unauthorized(string $message = 'Chưa xác thực'): void
    {
        $this->response(401, $message, null, 401);
    }

    /**
     * Phản hồi lỗi server (500 Internal Server Error)
     */
    protected function internalError(string $message = 'Lỗi server'): void
    {
        $this->response(500, $message, null, 500);
    }

    /**
     * Kiểm tra người dùng đã đăng nhập
     */
    protected function requireAuth(): array|bool
    {
        Auth::startSession();
        
        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Vui lòng đăng nhập');
            return false;
        }

        $user = Auth::getCurrentUser();
        if (!$user || !Auth::isAccountActive()) {
            $this->unauthorized('Tài khoản không hoạt động');
            return false;
        }

        return $user;
    }

    /**
     * Lấy dữ liệu JSON từ request body
     */
    protected function getJSON(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Lấy tham số từ URL
     */
    protected function getParam(string $key, $default = null)
    {
        // Kiểm tra trong $params trước (từ router)
        if (isset($this->params[$key])) {
            return $this->params[$key];
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Đặt tham số (gọi từ router)
     */
    public function setParam(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * Lấy phương thức HTTP
     */
    protected function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Kiểm tra phương thức HTTP
     */
    protected function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Đặt header
     */
    protected function setHeader(string $key, string $value): void
    {
        header("$key: $value");
    }

    /**
     * Validate dữ liệu
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // required validation first
            if (strpos($rule, 'required') !== false) {
                if (empty($value)) {
                    $errors[$field] = "$field là bắt buộc";
                    continue;  // Skip other validations if required and empty
                }
            }

            // Skip other validations if value is empty and not required
            if (empty($value)) {
                continue;
            }

            // email validation
            if (strpos($rule, 'email') !== false) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "$field không hợp lệ";
                }
            }

            // numeric validation
            if (strpos($rule, 'numeric') !== false) {
                if (!is_numeric($value)) {
                    $errors[$field] = "$field phải là số";
                }
            }

            // minlen validation
            if (strpos($rule, 'minlen:') !== false) {
                preg_match('/minlen:(\d+)/', $rule, $matches);
                $min = (int)($matches[1] ?? 0);
                $length = is_string($value) ? strlen($value) : 0;
                if ($length < $min) {
                    $errors[$field] = "$field phải có ít nhất $min ký tự";
                }
            }
        }

        return $errors;
    }

    /**
     * Lấy trang hiện tại (pagination)
     */
    protected function getPage(int $default = 1): int
    {
        $page = $this->getParam('page', $default);
        return max((int)$page, 1);
    }

    /**
     * Lấy limit mỗi trang
     */
    protected function getLimit(int $default = 20, int $max = 100): int
    {
        $limit = (int)$this->getParam('limit', $default);
        return min($limit, $max);
    }

    /**
     * Tính offset cho pagination
     */
    protected function getOffset(int $page, int $limit): int
    {
        return ($page - 1) * $limit;
    }

    /**
     * Đăng nhập access log
     */
    protected function logAccess(string $action): void
    {
        $user = Auth::getCurrentUser();
        $userId = $user['MaNguoiDung'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');
        
        error_log("[$timestamp] User $userId: $action");
    }
}
