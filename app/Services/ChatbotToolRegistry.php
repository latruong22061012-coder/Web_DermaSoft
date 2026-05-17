<?php
declare(strict_types=1);

/**
 * ChatbotToolRegistry — Tập hợp các tool READ-ONLY mà chatbot được phép gọi.
 *
 * NGUYÊN TẮC BẢO MẬT (BẮT BUỘC):
 *  1. Whitelist tên tool (xem $TOOLS). Tên không có trong whitelist => reject.
 *  2. KHÔNG nhận tham số định danh người dùng từ model (maNguoiDung, maBenhNhan,
 *     userId, email, soDienThoai, hoTen…). Luôn ghi đè bằng giá trị
 *     từ session ($userCtx) ở SERVER-SIDE.
 *  3. Mọi truy vấn dữ liệu cá nhân ràng buộc theo MaNguoiDung / MaBenhNhan của
 *     chính session — không lộ dữ liệu người khác.
 *  4. Output đi qua bộ scrubber: chỉ giữ các field tối thiểu cần cho câu trả lời;
 *     không trả MatKhau, IsDeleted, MaNguoiDung của người khác, v.v.
 */

namespace App\Services;

use App\Core\Database;
use App\Models\ThongTinPhongKham;

class ChatbotToolRegistry
{
    /** Whitelist tên tool. */
    private const TOOLS = [
        'get_clinic_info',
        'list_services',
        'list_available_doctors',
        'list_free_slots',
        'get_my_bookings',
        'get_my_membership',
    ];

    /** Các key tham số bị strip vì model không được phép truyền. */
    private const FORBIDDEN_PARAM_KEYS = [
        'manguoidung', 'mabenhnhan', 'userid', 'user_id',
        'email', 'sodienthoai', 'phone', 'hoten', 'name', 'id',
    ];

    /**
     * Schema function declarations gửi tới Gemini.
     */
    public static function getFunctionDeclarations(): array
    {
        return [[
            'functionDeclarations' => [
                [
                    'name' => 'get_clinic_info',
                    'description' => 'Lấy thông tin chung của phòng khám (tên, địa chỉ, giờ mở/đóng cửa, hotline, email, mô tả).',
                    'parameters' => ['type' => 'OBJECT', 'properties' => new \stdClass()],
                ],
                [
                    'name' => 'list_services',
                    'description' => 'Lấy danh sách dịch vụ phòng khám đang cung cấp kèm đơn giá. Có thể lọc theo từ khoá tên dịch vụ.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'keyword' => [
                                'type' => 'STRING',
                                'description' => 'Từ khoá để lọc tên dịch vụ (không bắt buộc).',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'list_available_doctors',
                    'description' => 'Liệt kê các bác sĩ còn nhận khám trong một ngày cụ thể (định dạng YYYY-MM-DD).',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'ngay' => [
                                'type' => 'STRING',
                                'description' => 'Ngày cần tra (YYYY-MM-DD).',
                            ],
                        ],
                        'required' => ['ngay'],
                    ],
                ],
                [
                    'name' => 'list_free_slots',
                    'description' => 'Liệt kê khung giờ còn trống của một bác sĩ trong một ngày. Truyền maBacSi lấy từ kết quả list_available_doctors.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'ngay'    => ['type' => 'STRING', 'description' => 'Ngày YYYY-MM-DD.'],
                            'maBacSi' => ['type' => 'INTEGER', 'description' => 'Mã bác sĩ (lấy từ list_available_doctors).'],
                        ],
                        'required' => ['ngay', 'maBacSi'],
                    ],
                ],
                [
                    'name' => 'get_my_bookings',
                    'description' => 'Lấy danh sách lịch hẹn của CHÍNH bệnh nhân đang đăng nhập. Không nhận tham số nhận dạng người dùng.',
                    'parameters' => ['type' => 'OBJECT', 'properties' => new \stdClass()],
                ],
                [
                    'name' => 'get_my_membership',
                    'description' => 'Lấy hạng thành viên và điểm tích luỹ của CHÍNH bệnh nhân đang đăng nhập.',
                    'parameters' => ['type' => 'OBJECT', 'properties' => new \stdClass()],
                ],
            ],
        ]];
    }

    /**
     * Thực thi một tool.
     *
     * @param string $name    Tên tool do model trả về.
     * @param array  $args    Tham số model truyền vào.
     * @param array  $userCtx ['MaNguoiDung'=>int, 'MaVaiTro'=>int, 'SoDienThoai'=>string, 'HoTen'=>string]
     * @return array          Kết quả (sẽ được encode thành JSON gửi lại Gemini).
     */
    public function execute(string $name, array $args, array $userCtx): array
    {
        // (1) Whitelist
        if (!in_array($name, self::TOOLS, true)) {
            $this->audit($userCtx, $name, 'denied_unknown_tool');
            return ['error' => 'unknown_tool'];
        }

        // (2) Strip mọi field định danh người dùng
        $args = $this->sanitizeArgs($args);

        // (3) Vai trò bắt buộc: bệnh nhân (MaVaiTro = 4)
        if ((int)($userCtx['MaVaiTro'] ?? 0) !== 4) {
            $this->audit($userCtx, $name, 'denied_role');
            return ['error' => 'forbidden_role'];
        }

        try {
            switch ($name) {
                case 'get_clinic_info':         $out = $this->getClinicInfo(); break;
                case 'list_services':           $out = $this->listServices($args['keyword'] ?? null); break;
                case 'list_available_doctors':  $out = $this->listAvailableDoctors($args['ngay'] ?? ''); break;
                case 'list_free_slots':         $out = $this->listFreeSlots($args['ngay'] ?? '', (int)($args['maBacSi'] ?? 0)); break;
                case 'get_my_bookings':         $out = $this->getMyBookings($userCtx); break;
                case 'get_my_membership':       $out = $this->getMyMembership($userCtx); break;
                default:                        $out = ['error' => 'unknown_tool'];
            }
            $this->audit($userCtx, $name, 'ok');
            return $out;
        } catch (\Throwable $e) {
            error_log('[Chatbot] tool=' . $name . ' error=' . $e->getMessage());
            $this->audit($userCtx, $name, 'error');
            return ['error' => 'internal_error'];
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Tool implementations
    // ──────────────────────────────────────────────────────────────────

    private function getClinicInfo(): array
    {
        $info = (new ThongTinPhongKham())->getInfo() ?? [];

        return [
            'tenPhongKham' => $info['TenPhongKham'] ?? 'DarmaSoft Clinic',
            'diaChi'       => $info['DiaChi'] ?? null,
            'hotline'      => $info['SoDienThoai'] ?? null,
            'email'        => $info['Email'] ?? null,
            'website'      => $info['Website'] ?? null,
            'gioMoCua'     => isset($info['GioMoCua'])  ? substr((string)$info['GioMoCua'], 0, 5)  : null,
            'gioDongCua'   => isset($info['GioDongCua']) ? substr((string)$info['GioDongCua'], 0, 5) : null,
            'lichLamViec'  => $info['LichLamViecHangTuan'] ?? null,
            'slogan'       => $info['Slogan'] ?? null,
            'moTa'         => $info['MoTa'] ?? null,
        ];
    }

    private function listServices(?string $keyword): array
    {
        $keyword = $keyword !== null ? trim($keyword) : '';
        if ($keyword !== '') {
            $rows = Database::fetchAll(
                "SELECT MaDichVu, TenDichVu, DonGia
                 FROM DichVu
                 WHERE TenDichVu LIKE ?
                 ORDER BY TenDichVu",
                ['%' . $keyword . '%']
            );
        } else {
            $rows = Database::fetchAll(
                "SELECT MaDichVu, TenDichVu, DonGia FROM DichVu ORDER BY TenDichVu"
            );
        }

        return [
            'count' => count($rows),
            'items' => array_map(static function (array $r): array {
                return [
                    'maDichVu'  => (int)($r['MaDichVu'] ?? 0),
                    'tenDichVu' => (string)($r['TenDichVu'] ?? ''),
                    'donGia'    => isset($r['DonGia']) ? (float)$r['DonGia'] : null,
                ];
            }, $rows),
        ];
    }

    private function listAvailableDoctors(string $ngay): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            return ['error' => 'invalid_date'];
        }

        $today = (new \DateTime())->format('Y-m-d');
        $isToday = ($ngay === $today);
        $nowTime = (new \DateTime())->format('H:i:s');

        // Lấy TOÀN BỘ phân công ca theo ngày — KHÔNG lọc theo giờ hiện tại,
        // để khách hỏi "hôm nay có bác sĩ nào nhận khám" thấy được cả ca chiều/tối.
        $rows = Database::fetchAll(
            "SELECT nd.MaNguoiDung, nd.HoTen,
                    c.TenCa, c.GioBatDau, c.GioKetThuc,
                    ISNULL(bn_count.SoBN, 0) AS SoBN
             FROM PhanCongCa pc
             INNER JOIN NguoiDung nd ON pc.MaNguoiDung = nd.MaNguoiDung
             INNER JOIN CaLamViec c ON pc.MaCa = c.MaCa
             LEFT JOIN (
                 SELECT MaNguoiDung, COUNT(*) AS SoBN
                 FROM LichHen
                 WHERE TrangThai IN (0, 1)
                   AND CAST(ThoiGianHen AS DATE) = ?
                 GROUP BY MaNguoiDung
             ) bn_count ON bn_count.MaNguoiDung = nd.MaNguoiDung
             WHERE nd.MaVaiTro = 2
               AND nd.TrangThaiTK = 1
               AND nd.IsDeleted = 0
               AND pc.NgayLamViec = ?
             ORDER BY c.GioBatDau, nd.HoTen",
            [$ngay, $ngay]
        );

        $items = [];
        foreach ($rows as $r) {
            $soBN = (int)($r['SoBN'] ?? 0);
            $gioBatDau  = (string)($r['GioBatDau'] ?? '');
            $gioKetThuc = (string)($r['GioKetThuc'] ?? '');

            // Cờ trạng thái ca — chỉ có ý nghĩa khi $isToday
            $trangThaiCa = 'sapDienRa';
            if ($isToday) {
                if ($nowTime >= $gioBatDau && $nowTime < $gioKetThuc) {
                    $trangThaiCa = 'dangDienRa';
                } elseif ($nowTime >= $gioKetThuc) {
                    $trangThaiCa = 'daKetThuc';
                }
            }

            $items[] = [
                // maBacSi giữ lại để model có thể tra list_free_slots,
                // nhưng đã yêu cầu (qua system instruction) KHÔNG hiển thị cho user.
                'maBacSi'      => (int)($r['MaNguoiDung'] ?? 0),
                'hoTen'        => (string)($r['HoTen'] ?? ''),
                'vaiTro'       => 'Bác sĩ',
                'tenCa'        => (string)($r['TenCa'] ?? ''),
                'gioBatDau'    => substr($gioBatDau, 0, 5),
                'gioKetThuc'   => substr($gioKetThuc, 0, 5),
                'trangThaiCa'  => $trangThaiCa,
                'soBNHienTai'  => $soBN,
                'gioiHanBNNgay'=> 8,
                'conNhanKhach' => $soBN < 8,
            ];
        }

        return [
            'ngay'      => $ngay,
            'laHomNay'  => $isToday,
            'gioHienTai'=> $isToday ? substr($nowTime, 0, 5) : null,
            'count'     => count($items),
            'items'     => $items,
        ];
    }

    private function listFreeSlots(string $ngay, int $maBacSi): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
            return ['error' => 'invalid_date'];
        }
        if ($maBacSi <= 0) {
            return ['error' => 'invalid_doctor'];
        }

        $doctor = Database::fetchOne(
            "SELECT MaNguoiDung, HoTen
             FROM NguoiDung
             WHERE MaNguoiDung = ? AND MaVaiTro = 2 AND TrangThaiTK = 1 AND IsDeleted = 0",
            [$maBacSi]
        );
        if (!$doctor) {
            return ['error' => 'doctor_not_found'];
        }

        // Khung giờ phòng khám
        $info = (new ThongTinPhongKham())->getInfo() ?? [];
        $openMin = $this->timeToMinutes($info['GioMoCua']  ?? '08:00:00');
        $closeMin = $this->timeToMinutes($info['GioDongCua'] ?? '17:00:00');
        $clinicLastSlot = $closeMin - 60;

        // Ca làm việc của bác sĩ trong ngày
        $shifts = Database::fetchAll(
            "SELECT c.TenCa, c.GioBatDau, c.GioKetThuc
             FROM PhanCongCa pc
             INNER JOIN CaLamViec c ON pc.MaCa = c.MaCa
             WHERE pc.MaNguoiDung = ? AND pc.NgayLamViec = ?
             ORDER BY c.GioBatDau",
            [$maBacSi, $ngay]
        );

        // Slot đã đặt
        $bookedRows = Database::fetchAll(
            "SELECT CONVERT(VARCHAR(5), ThoiGianHen, 108) AS GioHen
             FROM LichHen
             WHERE MaNguoiDung = ? AND CAST(ThoiGianHen AS DATE) = ? AND TrangThai IN (0, 1)",
            [$maBacSi, $ngay]
        );
        $booked = [];
        foreach ($bookedRows as $row) {
            if (!empty($row['GioHen'])) $booked[(string)$row['GioHen']] = true;
        }

        $today = (new \DateTime())->format('Y-m-d');
        $currentMin = null;
        if ($ngay === $today) {
            $now = new \DateTime();
            $currentMin = ((int)$now->format('H') * 60) + (int)$now->format('i');
        }

        $slots = [];
        foreach ($shifts as $shift) {
            $startMin = max($openMin, $this->timeToMinutes((string)($shift['GioBatDau'] ?? '00:00:00')));
            $lastSlot = min($clinicLastSlot, $this->timeToMinutes((string)($shift['GioKetThuc'] ?? '00:00:00')) - 60);

            for ($m = $startMin; $m <= $lastSlot; $m += 30) {
                if ($m >= 720 && $m < 780) continue; // nghỉ trưa
                if ($currentMin !== null && $m <= $currentMin) continue;
                $value = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                if (isset($booked[$value]) || isset($slots[$value])) continue;
                $slots[$value] = ['gio' => $value, 'caLam' => (string)($shift['TenCa'] ?? '')];
            }
        }
        ksort($slots);

        return [
            'ngay'   => $ngay,
            'bacSi'  => (string)$doctor['HoTen'],
            'count'  => count($slots),
            'slots'  => array_values($slots),
        ];
    }

    private function getMyBookings(array $userCtx): array
    {
        $maNguoiDung = (int)($userCtx['MaNguoiDung'] ?? 0);
        if ($maNguoiDung <= 0) {
            return ['error' => 'unauthenticated'];
        }

        // Tìm các lịch hẹn của bệnh nhân — join qua BenhNhan theo SoDienThoai
        // (NguoiDung và BenhNhan liên kết qua SoDienThoai duy nhất).
        $rows = Database::fetchAll(
            "SELECT TOP 5
                    lh.MaLichHen,
                    CONVERT(VARCHAR(19), lh.ThoiGianHen, 120) AS ThoiGianHen,
                    lh.TrangThai,
                    lh.GhiChu,
                    bs.HoTen AS TenBacSi
             FROM LichHen lh
             INNER JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
             INNER JOIN NguoiDung me ON me.SoDienThoai = bn.SoDienThoai AND me.MaNguoiDung = ?
             LEFT JOIN NguoiDung bs ON bs.MaNguoiDung = lh.MaNguoiDung
             ORDER BY lh.ThoiGianHen DESC",
            [$maNguoiDung]
        );

        $statusLabel = [0 => 'Chờ xác nhận', 1 => 'Đã xác nhận', 2 => 'Hoàn thành', 3 => 'Đã huỷ'];

        return [
            'count' => count($rows),
            'items' => array_map(static function (array $r) use ($statusLabel): array {
                $st = (int)($r['TrangThai'] ?? -1);
                return [
                    'maLichHen'   => (int)($r['MaLichHen'] ?? 0),
                    'thoiGianHen' => (string)($r['ThoiGianHen'] ?? ''),
                    'trangThai'   => $statusLabel[$st] ?? 'Không rõ',
                    'tenBacSi'    => $r['TenBacSi'] ?? null,
                    'ghiChu'      => $r['GhiChu'] ?? null,
                ];
            }, $rows),
        ];
    }

    private function getMyMembership(array $userCtx): array
    {
        $maNguoiDung = (int)($userCtx['MaNguoiDung'] ?? 0);
        if ($maNguoiDung <= 0) {
            return ['error' => 'unauthenticated'];
        }

        $row = Database::fetchOne(
            "SELECT TOP 1
                    tv.DiemTichLuy,
                    htv.TenHang,
                    htv.DiemToiThieu
             FROM NguoiDung nd
             INNER JOIN BenhNhan bn   ON bn.SoDienThoai = nd.SoDienThoai
             LEFT  JOIN ThanhVienInfo tv  ON tv.MaBenhNhan = bn.MaBenhNhan
             LEFT  JOIN HangThanhVien htv ON htv.MaHang = (
                 SELECT TOP 1 MaHang FROM HangThanhVien
                 WHERE DiemToiThieu <= ISNULL(tv.DiemTichLuy, 0)
                 ORDER BY DiemToiThieu DESC
             )
             WHERE nd.MaNguoiDung = ?",
            [$maNguoiDung]
        );

        if (!$row) {
            return ['diemTichLuy' => 0, 'tenHang' => null];
        }

        return [
            'diemTichLuy' => (int)($row['DiemTichLuy'] ?? 0),
            'tenHang'     => $row['TenHang'] ?? null,
            'diemToiThieuHang' => isset($row['DiemToiThieu']) ? (int)$row['DiemToiThieu'] : null,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /** Bỏ mọi key nhạy cảm / định danh người dùng do model truyền. */
    private function sanitizeArgs(array $args): array
    {
        $clean = [];
        foreach ($args as $k => $v) {
            if (in_array(strtolower((string)$k), self::FORBIDDEN_PARAM_KEYS, true)) {
                continue;
            }
            $clean[$k] = $v;
        }
        return $clean;
    }

    private function timeToMinutes(string $time): int
    {
        $p = explode(':', $time);
        return ((int)($p[0] ?? 0) * 60) + (int)($p[1] ?? 0);
    }

    private function audit(array $userCtx, string $tool, string $result): void
    {
        $uid = (int)($userCtx['MaNguoiDung'] ?? 0);
        error_log("[Chatbot] user={$uid} tool={$tool} {$result}");
    }
}
