<?php
/**
 * Base Model Class - Cơ sở cho tất cả các Model
 * Cung cấp các phương thức CRUD cơ bản
 */

namespace App\Core;

use PDOException;

abstract class Model
{
    /**
     * Tên bảng dữ liệu (override trong các class con)
     */
    protected static string $table = '';

    /**
     * Khóa chính (mặc định là 'id')
     */
    protected static string $primaryKey = 'id';

    /**
     * Tìm kiếm một bản ghi theo ID
     * @param int $id
     * @return array|false
     */
    public static function findById(int $id): array|false
    {
        $table = static::$table;
        $pk = static::$primaryKey;
        
        $sql = "SELECT * FROM $table WHERE $pk = ?";
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * Lấy tất cả bản ghi
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function all(int $limit = 100, int $offset = 0): array
    {
        $table = static::$table;
        
        $sql = "SELECT * FROM $table ORDER BY " . static::$primaryKey . " DESC LIMIT ? OFFSET ?";
        return Database::fetchAll($sql, [$limit, $offset]);
    }

    /**
     * Tìm kiếm với điều kiện
     * @param string $column
     * @param mixed $value
     * @return array|false
     */
    public static function findBy(string $column, $value): array|false
    {
        $table = static::$table;
        
        $sql = "SELECT * FROM $table WHERE $column = ?";
        return Database::fetchOne($sql, [$value]);
    }

    /**
     * Tìm kiếm tất cả với điều kiện
     * @param string $column
     * @param mixed $value
     * @return array
     */
    public static function findAllBy(string $column, $value): array
    {
        $table = static::$table;
        
        $sql = "SELECT * FROM $table WHERE $column = ? ORDER BY " . static::$primaryKey . " DESC";
        return Database::fetchAll($sql, [$value]);
    }

    /**
     * Tạo bản ghi mới
     * @param array $data
     * @return int|false ID của bản ghi vừa tạo
     */
    public static function create(array $data): int|false
    {
        try {
            $table = static::$table;
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
            Database::execute($sql, array_values($data));
            
            // Lấy ID vừa insert
            $lastId = Database::lastInsertId();
            return $lastId ? (int)$lastId : false;
            
        } catch (PDOException $e) {
            error_log('Insert Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cập nhật bản ghi
     * @param int $id
     * @param array $data
     * @return int Số bản ghi bị ảnh hưởng
     */
    public static function update(int $id, array $data): int
    {
        try {
            $table = static::$table;
            $pk = static::$primaryKey;
            
            $updates = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                $updates[] = "$key = ?";
                $values[] = $value;
            }
            
            $values[] = $id;
            
            $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE $pk = ?";
            return Database::execute($sql, $values);
            
        } catch (PDOException $e) {
            error_log('Update Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Xóa bản ghi
     * @param int $id
     * @return int Số bản ghi bị xóa
     */
    public static function delete(int $id): int
    {
        try {
            $table = static::$table;
            $pk = static::$primaryKey;
            
            $sql = "DELETE FROM $table WHERE $pk = ?";
            return Database::execute($sql, [$id]);
            
        } catch (PDOException $e) {
            error_log('Delete Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Đếm tổng số bản ghi
     * @return int
     */
    public static function count(): int
    {
        $table = static::$table;
        $sql = "SELECT COUNT(*) as total FROM $table";
        $result = Database::fetchOne($sql);
        
        return $result['total'] ?? 0;
    }

    /**
     * Thực thi query tùy chỉnh
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function query(string $sql, array $params = []): array
    {
        return Database::fetchAll($sql, $params);
    }

    /**
     * Lấy một bản ghi từ query tùy chỉnh
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public static function queryOne(string $sql, array $params = []): array|false
    {
        return Database::fetchOne($sql, $params);
    }

    /**
     * Kiểm tra bản ghi có tồn tại không
     * @param int $id
     * @return bool
     */
    public static function exists(int $id): bool
    {
        return self::findById($id) !== false;
    }
}
