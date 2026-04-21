<?php
/**
 * Database Connection Wrapper using PDO
 * Kết nối cơ sở dữ liệu sử dụng PDO
 */

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    /**
     * Kết nối tới SQL Server
     * @return PDO
     * @throws PDOException
     */
    public static function connect(): PDO
    {
        if (self::$connection === null) {
            try {
                $config = require __DIR__ . '/../Config/config.php';
                
                // Kết nối sử dụng SQL Server Authentication (sa account)
                self::$connection = new PDO(
                    $config['dsn'],
                    DB_USER ?? 'sa',
                    DB_PASS ?? 'DarmaSoft2026',
                    $config['options']
                );

                // Test connection
                self::$connection->query('SELECT 1');
                
            } catch (PDOException $e) {
                error_log('Database Connection Error: ' . $e->getMessage());
                throw new PDOException('Kết nối cơ sở dữ liệu thất bại: ' . $e->getMessage(), 0, $e);
            }
        }

        return self::$connection;
    }

    /**
     * Thực thi câu lệnh SQL với prepared statement
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $conn = self::connect();
        $stmt = $conn->prepare($sql);
        // Bind từng param với kiểu dữ liệu đúng (SQL Server yêu cầu integer cho TOP/OFFSET/FETCH)
        foreach ($params as $index => $value) {
            $type = is_int($value) ? \PDO::PARAM_INT : (is_null($value) ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            if (is_string($index)) {
                // Named parameter (e.g. :SoDienThoai)
                $stmt->bindValue($index, $value, $type);
            } else {
                // Positional parameter (e.g. ?)
                $stmt->bindValue($index + 1, $value, $type);
            }
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * Lấy một bản ghi
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public static function fetchOne(string $sql, array $params = []): array|false
    {
        return self::query($sql, $params)->fetch();
    }

    /**
     * Lấy tất cả bản ghi
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Lấy số lượng bản ghi bị ảnh hưởng
     * @param string $sql
     * @param array $params
     * @return int
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Lấy ID của bản ghi vừa insert
     * @return string
     */
    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    /**
     * Đóng kết nối
     */
    public static function close(): void
    {
        self::$connection = null;
    }
}
