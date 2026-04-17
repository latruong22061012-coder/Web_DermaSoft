<?php

declare(strict_types=1);

// ═══ ERROR HANDLING - Prevent HTML errors in API responses ═══
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// ═══ BASE URL - Tính từ vị trí tuyệt đối của index.php ═══
// Dùng DOCUMENT_ROOT để tính chính xác, không phụ thuộc vào SCRIPT_NAME
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_this_dir = rtrim(str_replace('\\', '/', __DIR__), '/');

if (!empty($_doc_root) && strpos($_this_dir, $_doc_root) === 0) {
    $_base = substr($_this_dir, strlen($_doc_root));
} else {
    // Fallback: dùng dirname(SCRIPT_NAME)
    $_base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\');
}

// Đảm bảo bắt đầu bằng / và kết thúc bằng /
if (empty($_base) || $_base === '.') {
    $_base = '';
}
define('BASE_URL', $_base . '/');

// Tự động load các lớp
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/app/Core/Controller.php';
require_once __DIR__ . '/app/Controllers/ApiController.php';
require_once __DIR__ . '/app/Controllers/HomeController.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/ProfileController.php';
require_once __DIR__ . '/app/Controllers/Api/OtpEmailController.php';
require_once __DIR__ . '/app/Controllers/Api/ProfileApiController.php';
require_once __DIR__ . '/app/Controllers/Api/BookingController.php';
require_once __DIR__ . '/app/Controllers/Api/DanhGiaController.php';
require_once __DIR__ . '/app/Controllers/AdminController.php';
require_once __DIR__ . '/app/Controllers/Api/AdminApiController.php';
require_once __DIR__ . '/app/Controllers/BacSiController.php';
require_once __DIR__ . '/app/Controllers/Api/BacSiApiController.php';
require_once __DIR__ . '/app/Controllers/LeTanController.php';
require_once __DIR__ . '/app/Controllers/Api/LeTanApiController.php';

// Khởi tạo session + CSRF token cho mọi request
\App\Core\Auth::startSession();
$csrfToken = \App\Controllers\ApiController::generateCsrfToken();

// Bộ xử lý tuyến đường API
// Bộ xử lý tuyến đường API
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';

// Xóa đường dẫn cơ sở /DermaSoft nếu có
if (strpos($request_uri, '/DermaSoft') === 0) {
    $request_uri = substr($request_uri, strlen('/DermaSoft'));
}

// Cũng xử lý chỉ /api* mà không có DermaSoft
if (empty($request_uri) || $request_uri === '/') {
    $request_uri = $_GET['route'] ?? 'home';
}

// Tuyến đường API - kiểm tra nếu yêu cầu là cho /api/*
if (strpos($request_uri, '/api/') === 0) {
    // Bật output buffering để bắt mọi output lạ trước khi trả JSON
    ob_start();
    try {
        handleApiRoute($request_uri);
    } catch (\Throwable $e) {
        ob_end_clean();
        // Log chi tiết lỗi vào file, chỉ trả message chung cho client
        error_log('[API Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 500,
            'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Tuyến đường Web
$route = $_GET['route'] ?? 'home';

// Fallback: xử lý API routes qua ?route=api/... (dùng khi không có .htaccess)
if (strpos($route, 'api/') === 0) {
    ob_start();
    try {
        handleApiRoute('/' . $route);
    } catch (\Throwable $e) {
        ob_end_clean();
        error_log('[API Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 500,
            'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

switch ($route) {
    case 'home':
        (new HomeController())->index();
        break;
    case 'login':
        (new AuthController())->login();
        break;
    case 'register':
        (new AuthController())->register();
        break;
    case 'forgot-password':
        (new AuthController())->forgotPassword();
        break;
    case 'profile':
        (new ProfileController())->index();
        break;
    case 'logout':
        \App\Core\Auth::startSession();
        \App\Core\Auth::logout();
        header('Location: index.php?route=login');
        exit;

    // ═══ ADMIN ROUTES ═══
    case 'admin':
    case 'admin/dashboard':
        (new AdminController())->dashboard();
        break;
    case 'admin/benh-nhan':
        (new AdminController())->benhNhan();
        break;
    case 'admin/thanh-vien':
        (new AdminController())->thanhVien();
        break;
    case 'admin/hang-thanh-vien':
        (new AdminController())->hangThanhVien();
        break;
    case 'admin/danh-gia':
        (new AdminController())->danhGia();
        break;

    // ═══ BÁC SĨ ROUTES ═══
    case 'bacsi':
    case 'bacsi/dashboard':
        (new BacSiController())->dashboard();
        break;
    case 'bacsi/lich-lam-viec':
        (new BacSiController())->lichLamViec();
        break;
    case 'bacsi/benh-nhan':
        (new BacSiController())->benhNhan();
        break;
    case 'bacsi/luong':
        (new BacSiController())->luong();
        break;

    // ═══ LỄ TÂN ROUTES ═══
    case 'letan':
    case 'letan/dashboard':
        (new LeTanController())->dashboard();
        break;
    case 'letan/lich-hen':
        (new LeTanController())->lichHen();
        break;
    case 'letan/lich-lam-viec':
        (new LeTanController())->lichLamViec();
        break;
    case 'letan/luong':
        (new LeTanController())->luong();
        break;

    default:
        http_response_code(404);
        echo '404 - Trang khong ton tai';
        break;
}

/**
 * Bộ xử lý tuyến đường API
 * Định dạng tuyến đường: /api/resource/method/param
 * Ví dụ:
 *   GET   /api/auth/login
 *   GET   /api/lichhens
 *   PUT   /api/lichhens/1
 *   POST  /api/lichhens
 */
function handleApiRoute($uri)
{
    // Luôn đặt header JSON trước
    header('Content-Type: application/json; charset=utf-8');

    // Xóa tiền tố /api và query string
    $path = substr($uri, 5);
    $path = strtok($path, '?');
    $parts = array_filter(explode('/', $path));
    $parts = array_values($parts);  // Sắp xếp lại mảng

    if (empty($parts)) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Endpoint không tìm thấy', 'data' => null]);
        return;
    }

    $resource = $parts[0];
    $method = isset($parts[1]) ? $parts[1] : null;
    $id = isset($parts[2]) ? $parts[2] : null;

    // Ánh xạ tuyến đường
    $routes = [
        'auth' => 'App\Controllers\Api\AuthController',
        'admin' => 'App\Controllers\Api\AdminApiController',
        'bacsi' => 'App\Controllers\Api\BacSiApiController',
        'letan' => 'App\Controllers\Api\LeTanApiController',
        'profile' => 'App\Controllers\Api\ProfileApiController',
        'booking' => 'App\Controllers\Api\BookingController',
        'lichhens' => 'App\Controllers\Api\LichHenController',
        'lichhen' => 'App\Controllers\Api\LichHenController',  // Alternative name
        'phieukham' => 'App\Controllers\Api\PhieuKhamController',
        'hoadon' => 'App\Controllers\Api\HoaDonController',
        'benhnhan' => 'App\Controllers\Api\BenhNhanController',
        'thanh-vien' => 'App\Controllers\Api\ThanhVienInfoController',
        'thanhvien' => 'App\Controllers\Api\ThanhVienInfoController',  // Alternative name
        'danhgia' => 'App\Controllers\Api\DanhGiaController',
    ];

    if (!isset($routes[$resource])) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Tài nguyên không tìm thấy', 'data' => null]);
        return;
    }

    $controller_class = $routes[$resource];
    
    if (!class_exists($controller_class)) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => 'Controller không tìm thấy', 'data' => null]);
        return;
    }

    $controller = new $controller_class();

    // Định tuyến đến phương thức controller dựa trên phương thức HTTP và đường dẫn
    $http_method = strtoupper($_SERVER['REQUEST_METHOD']);

    // Ánh xạ tuyến đường đến các phương thức
    if ($resource === 'auth') {
        // ────── Standard Auth Endpoints (AuthController) ──────
        if ($method === 'login' && $http_method === 'POST') {
            $controller->login();
        } elseif ($method === 'logout' && $http_method === 'POST') {
            $controller->logout();
        } elseif ($method === 'me' && $http_method === 'GET') {
            $controller->getCurrentUser();
        } elseif ($method === 'register' && $http_method === 'POST') {
            $controller->register();
        } elseif ($method === 'forgot-password' && $http_method === 'POST') {
            $controller->forgotPassword();
        } elseif ($method === 'verify-token' && $http_method === 'POST') {
            $controller->verifyToken();
        } elseif ($method === 'change-password' && $http_method === 'POST') {
            $controller->changePassword();
        } 
        // ────── OTP/Email Endpoints (OtpEmailController) ──────
        elseif ($method === 'check-phone' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->checkPhone();
        } elseif ($method === 'send-otp-login' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->sendOtpLogin();
        } elseif ($method === 'login-with-otp' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->loginWithOtp();
        } elseif ($method === 'register-phone' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->registerPhone();
        } elseif ($method === 'forgot-phone' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->forgotPhone();
        } elseif ($method === 'update-phone' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->updatePhone();
        } elseif ($method === 'verify-email-token' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->verifyEmailToken();
        } elseif ($method === 'send-otp-phone-reset' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->sendOtpPhoneReset();
        } elseif ($method === 'reset-phone-with-otp' && $http_method === 'POST') {
            $otpController = new \App\Controllers\Api\OtpEmailController();
            $otpController->resetPhoneWithOtp();
        }
        else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Endpoint xác thực không tìm thấy', 'data' => null]);
        }
        return;
    }

    // Cho các controller tài nguyên (LichHen, PhieuKham, HoaDon, BenhNhan, ThanhVienInfo)
    if ($resource === 'profile') {
        if ($method === 'update' && $http_method === 'POST') {
            $controller->updateInfo();
        } elseif ($method === 'upload-avatar' && $http_method === 'POST') {
            $controller->uploadAvatar();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Profile endpoint không tìm thấy', 'data' => null]);
        }
        return;
    }

    // ═══ ADMIN API ROUTES ═══
    if ($resource === 'admin') {
        $controller->setParam('id', $id);
        if ($method === 'stats' && $http_method === 'GET') {
            $controller->stats();
        } elseif ($method === 'benh-nhan' && $http_method === 'GET' && !$id) {
            $controller->listBenhNhan();
        } elseif ($method === 'benh-nhan' && $http_method === 'GET' && $id) {
            $controller->showBenhNhan();
        } elseif ($method === 'benh-nhan' && $http_method === 'PUT' && $id) {
            $controller->updateBenhNhan();
        } elseif ($method === 'toggle-status' && $http_method === 'POST') {
            $controller->toggleStatus();
        } elseif ($method === 'reset-password' && $http_method === 'POST') {
            $controller->resetPassword();
        } elseif ($method === 'thanh-vien' && $http_method === 'GET' && !$id) {
            $controller->listThanhVien();
        } elseif ($method === 'thanh-vien' && $http_method === 'GET' && $id) {
            $controller->showThanhVien();
        } elseif ($method === 'thanh-vien' && $http_method === 'PUT' && $id) {
            $controller->updateThanhVien();
        } elseif ($method === 'hang-thanh-vien' && $http_method === 'GET' && !$id) {
            $controller->listHangTV();
        } elseif ($method === 'hang-thanh-vien' && $http_method === 'GET' && $id) {
            $controller->showHangTV();
        } elseif ($method === 'hang-thanh-vien' && $http_method === 'POST' && !$id) {
            $controller->createHangTV();
        } elseif ($method === 'hang-thanh-vien' && $http_method === 'PUT' && $id) {
            $controller->updateHangTV();
        } elseif ($method === 'hang-thanh-vien' && $http_method === 'DELETE' && $id) {
            $controller->deleteHangTV();
        } elseif ($method === 'danh-gia' && $http_method === 'GET' && !$id) {
            $controller->listDanhGia();
        } elseif ($method === 'danh-gia' && $http_method === 'DELETE' && $id) {
            $controller->deleteDanhGia();
        } elseif ($method === 'danh-gia-stats' && $http_method === 'GET') {
            $controller->danhGiaStats();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Admin endpoint không tìm thấy', 'data' => null]);
        }
        return;
    }

    // ═══ BÁC SĨ API ROUTES ═══
    if ($resource === 'bacsi') {
        if ($method === 'stats' && $http_method === 'GET') {
            $controller->stats();
        } elseif ($method === 'lich-lam-viec' && $http_method === 'GET') {
            $controller->lichLamViec();
        } elseif ($method === 'benh-nhan' && $http_method === 'GET') {
            $controller->dsBenhNhan();
        } elseif ($method === 'luong' && $http_method === 'GET') {
            $controller->luong();
        } elseif ($method === 'danh-gia' && $http_method === 'GET') {
            $controller->danhGia();
        } elseif ($method === 'thong-ke-bn' && $http_method === 'GET') {
            $controller->thongKeBN();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'BacSi endpoint không tìm thấy', 'data' => null]);
        }
        return;
    }

    // ═══ LỄ TÂN API ROUTES ═══
    if ($resource === 'letan') {
        if ($method === 'stats' && $http_method === 'GET') {
            $controller->stats();
        } elseif ($method === 'lich-hen' && $http_method === 'GET') {
            $controller->dsLichHen();
        } elseif ($method === 'lich-lam-viec' && $http_method === 'GET') {
            $controller->lichLamViec();
        } elseif ($method === 'luong' && $http_method === 'GET') {
            $controller->luong();
        } elseif ($method === 'thong-ke-lh' && $http_method === 'GET') {
            $controller->thongKeLH();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'LeTan endpoint không tìm thấy', 'data' => null]);
        }
        return;
    }

    if ($resource === 'lichhens' || $resource === 'lichhen') {
        routeResourceController($controller, $method, $id, $http_method);
        return;
    }

    if ($resource === 'booking') {
        if ($method === 'create' && $http_method === 'POST') {
            $controller->create();
        } elseif ($method === 'doctors' && $http_method === 'GET') {
            $controller->doctors();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Booking endpoint không tìm thấy', 'data' => null]);
        }
        return;
    }

    if ($resource === 'phieukham') {
        routeResourceController($controller, $method, $id, $http_method);
        return;
    }

    if ($resource === 'hoadon') {
        routeResourceController($controller, $method, $id, $http_method);
        return;
    }

    if ($resource === 'benhnhan') {
        routeResourceController($controller, $method, $id, $http_method);
        return;
    }

    if ($resource === 'danhgia') {
        if ($method === 'check' && $id && $http_method === 'GET') {
            $controller->setParam('id', $id);
            $controller->check();
        } elseif (!$method && $http_method === 'POST') {
            $controller->create();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Endpoint đánh giá không tìm thấy', 'data' => null]);
        }
        return;
    }

    if ($resource === 'thanh-vien' || $resource === 'thanhvien') {
        routeResourceController($controller, $method, $id, $http_method);
        return;
    }

    http_response_code(404);
    echo json_encode(['status' => 404, 'message' => 'Endpoint không tìm thấy', 'data' => null]);
}

/**
 * Định tuyến phương thức controller tài nguyên
 * GET    /resource          -> index()
 * GET    /resource/1        -> show()
 * GET    /resource/status/1 -> getByStatus()
 * POST   /resource          -> create()
 * PUT    /resource/1        -> update()
 * DELETE /resource/1        -> delete()
 */
function routeResourceController($controller, $method, $id, $http_method)
{
    // Lưu trữ ID trong controller để truy cập
    if ($controller instanceof \App\Controllers\ApiController) {
        $controller->setParam('id', $id);
        $controller->setParam('patient_id', $method);  // cho /benhnhan/patient/{id}
        $controller->setParam('appointment_id', $method);  // cho /phieukham/appointment/{id}
        $controller->setParam('phone', $method);  // cho /benhnhan/phone/{phone}
    }

    // Logic định tuyến
    if ($http_method === 'GET') {
        if (!$method && !$id) {
            // GET /resource -> index()
            $controller->index();
        } elseif ($method && !$id) {
            // GET /resource/{method} -> getByMethod()
            if (method_exists($controller, 'getBy' . ucfirst($method))) {
                $function = 'getBy' . ucfirst($method);
                $controller->$function();
            } elseif ($method === 'pending' && method_exists($controller, 'getPending')) {
                $controller->getPending();
            } elseif ($method === 'unpaid' && method_exists($controller, 'getUnpaid')) {
                $controller->getUnpaid();
            } elseif ($method === 'paid' && method_exists($controller, 'getPaid')) {
                $controller->getPaid();
            } elseif ($method === 'search' && method_exists($controller, 'search')) {
                $controller->search();
            } elseif (is_numeric($method)) {
                // GET /resource/123 -> show()
                $controller->setParam('id', $method);
                $controller->show();
            } else {
                http_response_code(404);
                echo json_encode(['status' => 404, 'message' => 'Phương thức không tìm thấy', 'data' => null]);
            }
        } elseif ($method && $id) {
            // GET /resource/method/id (ví dụ: /resource/123/services)
            if ($method === 'patient' && method_exists($controller, 'getByPatient')) {
                $controller->setParam('patient_id', $id);
                $controller->getByPatient();
            } elseif ($method === 'appointment' && method_exists($controller, 'getByAppointment')) {
                $controller->setParam('appointment_id', $id);
                $controller->getByAppointment();
            } elseif ($method === 'phone' && method_exists($controller, 'getByPhone')) {
                $controller->setParam('phone', $id);
                $controller->getByPhone();
            } elseif ($method === 'status' && method_exists($controller, 'getByStatus')) {
                $controller->setParam('status', $id);
                $controller->getByStatus();
            } elseif ($method === 'points' && method_exists($controller, 'getPoints')) {
                $controller->setParam('id', $id);
                $controller->getPoints();
            } elseif (in_array($method, ['services', 'medicines', 'appointments', 'consultations', 'invoices'])) {
                // GET /resource/id/services -> getRecordServices()
                $controller->setParam('id', $id);
                $function = 'getRecord' . ucfirst($method);
                if (method_exists($controller, $function)) {
                    $controller->$function();
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 404, 'message' => 'Phương thức không tìm thấy', 'data' => null]);
                }
            } else {
                http_response_code(404);
                echo json_encode(['status' => 404, 'message' => 'Endpoint không tìm thấy', 'data' => null]);
            }
        }
    } elseif ($http_method === 'POST') {
        if (!$method && !$id) {
            // POST /resource -> create()
            $controller->create();
        } elseif ($method && !$id) {
            // POST /resource/{action} (ví dụ: /resource/confirm)
            if (in_array($method, ['confirm', 'cancel'])) {
                $function = $method;
                if (method_exists($controller, $function)) {
                    $controller->$function();
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 404, 'message' => 'Hành động không tìm thấy', 'data' => null]);
                }
            } else {
                http_response_code(404);
                echo json_encode(['status' => 404, 'message' => 'Endpoint không tìm thấy', 'data' => null]);
            }
        } elseif ($method && $id) {
            // POST /resource/id/action (ví dụ: /resource/123/confirm)
            $controller->setParam('id', $method);  // method thực tế là id
            if (in_array($id, ['confirm', 'cancel'])) {
                $controller->$id();
            } else {
                http_response_code(404);
                echo json_encode(['status' => 404, 'message' => 'Hành động không tìm thấy', 'data' => null]);
            }
        }
    } elseif ($http_method === 'PUT') {
        if ($method && !$id) {
            // PUT /resource/{id} -> update()
            $controller->setParam('id', $method);
            $controller->update();
        } elseif ($method && $id) {
            // PUT /resource/{id}/action (ví dụ: /resource/123/status)
            $controller->setParam('id', $method);
            if ($id === 'status' && method_exists($controller, 'updateStatus')) {
                $controller->updateStatus();
            } elseif ($id === 'payment' && method_exists($controller, 'updatePayment')) {
                $controller->updatePayment();
            } elseif ($id === 'points' && method_exists($controller, 'updatePoints')) {
                $controller->updatePoints();
            } elseif ($id === 'services' && method_exists($controller, 'addServices')) {
                $controller->addServices();
            } else {
                http_response_code(404);
                echo json_encode(['status' => 404, 'message' => 'Hành động không tìm thấy', 'data' => null]);
            }
        }
    } elseif ($http_method === 'DELETE') {
        if ($method && !$id) {
            // DELETE /resource/{id} -> delete()
            $controller->setParam('id', $method);
            $controller->delete();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Endpoint không tìm thấy', 'data' => null]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['status' => 405, 'message' => 'Phương thức không được phép', 'data' => null]);
    }
}
