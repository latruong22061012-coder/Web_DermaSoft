<?php
/**
 * Profile API Controller
 * Xử lý cập nhật thông tin cá nhân của người dùng đã đăng nhập
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Core\Database;
use App\Models\User;

class ProfileApiController extends ApiController
{
    // ============================================================
    // POST /api/profile/update
    // ============================================================
    /**
     * Cập nhật HoTen và Email của người dùng đang đăng nhập
     *
     * Yêu cầu: { hoTen, email }
     * Phản hồi: { "success": true, "message": "..." }
     */
    public function updateInfo(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            $this->error('Bạn chưa đăng nhập', null, 401);
            return;
        }

        $data = $this->getJSON();

        $errors = $this->validate($data, [
            'hoTen' => 'required|minlen:3',
            'email' => 'required|email',
        ]);

        if (!empty($errors)) {
            $this->error('Dữ liệu không hợp lệ', $errors, 400);
            return;
        }

        $currentUser = Auth::getCurrentUser();
        $userId      = (int)($currentUser['MaNguoiDung'] ?? 0);

        if (!$userId) {
            $this->error('Không xác định được tài khoản', null, 401);
            return;
        }

        try {
            // Kiểm tra email mới đã tồn tại ở tài khoản khác chưa
            $emailVal = trim($data['email']);
            $existing = Database::fetchOne(
                "SELECT MaNguoiDung FROM NguoiDung WHERE Email = ? AND MaNguoiDung <> ?",
                [$emailVal, $userId]
            );
            if ($existing) {
                $this->error('Email này đã được sử dụng bởi tài khoản khác', null, 409);
                return;
            }

            // Gọi SP cập nhật thông tin
            Database::execute(
                "EXEC SP_CapNhatThongTinNguoiDung @MaNguoiDung = ?, @HoTen = ?, @Email = ?",
                [$userId, trim($data['hoTen']), $emailVal]
            );

            // Lấy lại thông tin mới nhất và cập nhật session
            $updatedUser = User::findByPhone($currentUser['SoDienThoai'] ?? '');
            if ($updatedUser) {
                $_SESSION['authenticated_user'] = $updatedUser;
            }

            $this->success([
                'hoTen' => trim($data['hoTen']),
                'email' => $emailVal,
            ], 'Cập nhật thông tin thành công');

        } catch (\Exception $e) {
            error_log("Lỗi updateInfo profile: " . $e->getMessage());
            $this->error('Lỗi cập nhật thông tin. Vui lòng thử lại', null, 500);
        }
    }

    // ============================================================
    // POST /api/profile/upload-avatar  (multipart/form-data)
    // ============================================================
    public function uploadAvatar(): void
    {
        Auth::startSession();

        if (!Auth::isAuthenticated()) {
            $this->error('Bạn chưa đăng nhập', null, 401);
            return;
        }

        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $this->error('Không nhận được file ảnh', null, 400);
            return;
        }

        $file    = $_FILES['avatar'];
        $maxSize = 2 * 1024 * 1024; // 2 MB

        if ($file['size'] > $maxSize) {
            $this->error('Ảnh vượt quá 2MB', null, 413);
            return;
        }

        // Validate MIME type từ nội dung file (dùng getimagesize - luôn có sẵn trong PHP)
        $imgInfo  = @getimagesize($file['tmp_name']);
        $allowed  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

        if (!$imgInfo || !array_key_exists($imgInfo[2], $allowed)) {
            $this->error('Chỉ chấp nhận file ảnh JPG, PNG, WEBP', null, 415);
            return;
        }

        $currentUser = Auth::getCurrentUser();
        $userId      = (int)($currentUser['MaNguoiDung'] ?? 0);

        if (!$userId) {
            $this->error('Không xác định được tài khoản', null, 401);
            return;
        }

        $ext      = $allowed[$imgInfo[2]];
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $saveDir  = __DIR__ . '/../../../public/assets/images/avatars/';
        $savePath = $saveDir . $filename;

        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $savePath)) {
            $this->error('Lỗi lưu file ảnh. Vui lòng thử lại', null, 500);
            return;
        }

        // Xoá avatar cũ nếu có
        $oldAvatar = $currentUser['AnhDaiDien'] ?? '';
        if ($oldAvatar) {
            $oldPath = __DIR__ . '/../../../' . ltrim($oldAvatar, '/');
            if (file_exists($oldPath) && strpos(str_replace('\\', '/', $oldPath), '/avatars/') !== false) {
                @unlink($oldPath);
            }
        }

        $relativePath = 'public/assets/images/avatars/' . $filename;

        try {
            Database::execute(
                "UPDATE NguoiDung SET AnhDaiDien = ? WHERE MaNguoiDung = ?",
                [$relativePath, $userId]
            );

            // Refresh session
            $updatedUser = User::findByPhone($currentUser['SoDienThoai'] ?? '');
            if ($updatedUser) {
                $_SESSION['authenticated_user'] = $updatedUser;
            }

            $this->success(['avatarUrl' => $relativePath], 'Cập nhật ảnh đại diện thành công');

        } catch (\Exception $e) {
            error_log("Lỗi uploadAvatar: " . $e->getMessage());
            @unlink($savePath); // rollback file nếu DB lỗi
            $this->error('Lỗi lưu thông tin ảnh. Vui lòng thử lại', null, 500);
        }
    }
}
