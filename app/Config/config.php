<?php
/**
 * Cấu hình kết nối cơ sở dữ liệu SQL Server
 * Database Configuration for SQL Server DERMASOFT
 * 
 * Credentials được đọc từ file .env (không commit vào Git)
 */

// Đọc file .env nếu tồn tại
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (!getenv($key)) {
            putenv("$key=$val");
        }
    }
}

// Cấu hình SQL Server — đọc từ environment variables
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'DERMASOFT');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'sa');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_DRIVER')) define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlsrv');
if (!defined('DB_USE_WINDOWS_AUTH')) define('DB_USE_WINDOWS_AUTH', (getenv('DB_USE_WINDOWS_AUTH') ?: 'false') === 'true');

// Xây dựng DSN cho SQL Server
// Thêm TrustServerCertificate=yes để bypass SSL certificate validation
// Encrypt=yes để sử dụng encryption (optional, nhưng giúp nếu server yêu cầu)
$dsn = sprintf(
    '%s:Server=%s;Database=%s;TrustServerCertificate=yes;Encrypt=no;',
    DB_DRIVER,
    DB_HOST,
    DB_NAME
);

// PDO Options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Export DSN và Options
return [
    'dsn' => $dsn,
    'options' => $options,
];
