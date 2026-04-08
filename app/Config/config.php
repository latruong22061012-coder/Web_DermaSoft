<?php
/**
 * Cấu hình kết nối cơ sở dữ liệu SQL Server
 * Database Configuration for SQL Server DERMASOFT
 */

// Cấu hình SQL Server
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');            // TCP/IP (đã enable)
if (!defined('DB_NAME')) define('DB_NAME', 'DERMASOFT');
if (!defined('DB_USER')) define('DB_USER', 'sa');                   // SQL Server Authentication
if (!defined('DB_PASS')) define('DB_PASS', 'DarmaSoft2026');        // Password (đã set trước đó)
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlsrv');
if (!defined('DB_USE_WINDOWS_AUTH')) define('DB_USE_WINDOWS_AUTH', false);      // Sử dụng SQL Server Authentication

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
