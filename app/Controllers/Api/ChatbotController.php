<?php
declare(strict_types=1);

/**
 * Chatbot API Controller — Read-only, chỉ phục vụ bệnh nhân đã đăng nhập.
 *
 * Endpoints:
 *  - POST /api/chatbot/send    Gửi tin nhắn, nhận phản hồi.
 *  - GET  /api/chatbot/config  Trả config khởi tạo cho FE (tên user, gợi ý câu hỏi).
 */

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Core\Auth;
use App\Services\GeminiService;
use App\Services\ChatbotToolRegistry;

class ChatbotController extends ApiController
{
    private const MAX_TOOL_TURNS = 3;
    private const MAX_USER_MESSAGES = 12; // số lượt user gần nhất gửi lên model

    public function send(): void
    {
        // 1) CSRF
        $this->requireCsrf();

        // 2) Auth + role
        Auth::startSession();
        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Vui lòng đăng nhập để sử dụng trợ lý AI.');
            return;
        }
        $user = Auth::getCurrentUser() ?? [];
        if ((int)($user['MaVaiTro'] ?? 0) !== 4) {
            $this->forbidden('Trợ lý AI chỉ phục vụ tài khoản bệnh nhân.');
            return;
        }
        if (!Auth::isAccountActive()) {
            $this->forbidden('Tài khoản không hoạt động.');
            return;
        }

        // 3) Rate limit nội bộ theo user
        $this->chatbotRateLimit((int)$user['MaNguoiDung']);

        // 4) Parse body
        $body = $this->getJSON();
        $message = trim((string)($body['message'] ?? ''));
        if ($message === '') {
            $this->error('Vui lòng nhập câu hỏi.', null, 400);
            return;
        }
        if (\strlen($message) > 4000 || (function_exists('mb_strlen') && \mb_strlen($message) > 1000)) {
            $this->error('Câu hỏi quá dài (tối đa 1000 ký tự).', null, 400);
            return;
        }

        $history = is_array($body['messages'] ?? null) ? $body['messages'] : [];

        // 5) Build contents cho Gemini
        $contents = $this->buildContents($history, $message);
        $systemInstruction = $this->buildSystemInstruction($user);
        $tools = ChatbotToolRegistry::getFunctionDeclarations();

        $service = new GeminiService();
        if (!$service->isConfigured()) {
            $this->response(503, 'Trợ lý AI chưa được cấu hình. Vui lòng liên hệ quản trị.', null, 503);
            return;
        }

        $registry = new ChatbotToolRegistry();
        $userCtx = [
            'MaNguoiDung' => (int)$user['MaNguoiDung'],
            'MaVaiTro'    => (int)$user['MaVaiTro'],
            'SoDienThoai' => (string)($user['SoDienThoai'] ?? ''),
            'HoTen'       => (string)($user['HoTen'] ?? ''),
        ];

        $usedTools = [];

        try {
            for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
                $resp = $service->generate($contents, $tools, $systemInstruction);

                $parts = $resp['candidates'][0]['content']['parts'] ?? [];
                $modelRole = $resp['candidates'][0]['content']['role'] ?? 'model';

                $functionCalls = [];
                $textChunks = [];
                foreach ($parts as $p) {
                    if (isset($p['functionCall'])) {
                        $functionCalls[] = $p['functionCall'];
                    } elseif (isset($p['text'])) {
                        $textChunks[] = (string)$p['text'];
                    }
                }

                if (!empty($functionCalls)) {
                    // Append model turn (function call) vào lịch sử.
                    // LƯU Ý: phải normalize `functionCall.args` thành stdClass khi rỗng,
                    // nếu không json_encode sẽ ra `[]` (mảng) và Gemini reject với 400.
                    $normalizedParts = [];
                    foreach ($parts as $p) {
                        if (isset($p['functionCall'])) {
                            $fcName = (string)($p['functionCall']['name'] ?? '');
                            $fcArgs = $p['functionCall']['args'] ?? [];
                            if (!is_array($fcArgs) || empty($fcArgs)) {
                                $fcArgs = new \stdClass();
                            }
                            $normalizedParts[] = [
                                'functionCall' => [
                                    'name' => $fcName,
                                    'args' => $fcArgs,
                                ],
                            ];
                        } else {
                            $normalizedParts[] = $p;
                        }
                    }
                    $contents[] = ['role' => $modelRole, 'parts' => $normalizedParts];

                    // Thực thi từng function call
                    $responseParts = [];
                    foreach ($functionCalls as $fc) {
                        $name = (string)($fc['name'] ?? '');
                        $args = is_array($fc['args'] ?? null) ? $fc['args'] : [];
                        $result = $registry->execute($name, $args, $userCtx);
                        $usedTools[] = $name;

                        $responseParts[] = [
                            'functionResponse' => [
                                'name' => $name,
                                'response' => ['result' => $result],
                            ],
                        ];
                    }

                    $contents[] = ['role' => 'user', 'parts' => $responseParts];
                    continue;
                }

                // Không còn function call → trả text
                $reply = trim(implode("\n", $textChunks));
                if ($reply === '') {
                    $reply = 'Xin lỗi, tôi chưa hiểu yêu cầu. Bạn có thể nói lại được không?';
                }

                $this->success([
                    'reply'     => $reply,
                    'usedTools' => array_values(array_unique($usedTools)),
                ]);
                return;
            }

            // Quá số vòng tool calling
            $this->success([
                'reply'     => 'Xin lỗi, tôi cần thêm thông tin để trả lời chính xác. Bạn vui lòng đặt lại câu hỏi cụ thể hơn nhé.',
                'usedTools' => array_values(array_unique($usedTools)),
            ]);
        } catch (\Throwable $e) {
            error_log('[Chatbot] generate error: ' . $e->getMessage());
            $this->response(503, 'Trợ lý AI tạm gián đoạn. Vui lòng thử lại sau ít phút.', null, 503);
        }
    }

    public function config(): void
    {
        Auth::startSession();
        if (!Auth::isAuthenticated()) {
            $this->unauthorized('Chưa đăng nhập.');
            return;
        }
        $user = Auth::getCurrentUser() ?? [];
        if ((int)($user['MaVaiTro'] ?? 0) !== 4) {
            $this->forbidden('Chỉ bệnh nhân được dùng trợ lý AI.');
            return;
        }

        $this->success([
            'hoTen' => $user['HoTen'] ?? '',
            'suggestedQuestions' => [
                'Phòng khám mở cửa lúc mấy giờ?',
                'Có những dịch vụ nào và giá bao nhiêu?',
                'Ngày mai có bác sĩ nào nhận khám không?',
                'Tôi đã đặt những lịch hẹn nào?',
                'Hạng thành viên của tôi là gì?',
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Chuyển history FE + message mới thành mảng `contents` Gemini.
     */
    private function buildContents(array $history, string $newMessage): array
    {
        $userTurnCount = 0;
        $reversed = array_reverse($history);
        $filtered = [];
        foreach ($reversed as $m) {
            $role = ($m['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string)($m['text'] ?? ''));
            if ($text === '') continue;
            if ($role === 'user') {
                if ($userTurnCount >= self::MAX_USER_MESSAGES) continue;
                $userTurnCount++;
            }
            $filtered[] = ['role' => $role, 'parts' => [['text' => function_exists('mb_substr') ? \mb_substr($text, 0, 2000) : \substr($text, 0, 2000)]]];
        }

        $contents = array_reverse($filtered);
        $contents[] = ['role' => 'user', 'parts' => [['text' => $newMessage]]];

        return $contents;
    }

    private function buildSystemInstruction(array $user): array
    {
        $today = (new \DateTime())->format('Y-m-d');
        $hoTen = (string)($user['HoTen'] ?? '');

        $text = "Bạn là DermaBot, trợ lý chăm sóc khách hàng của phòng khám da liễu DarmaSoft Clinic.\n"
            . "Hôm nay là {$today}. Bạn đang trò chuyện với bệnh nhân: {$hoTen}.\n\n"
            . "QUY TẮC BẮT BUỘC:\n"
            . "1. Chỉ trả lời các vấn đề liên quan đến phòng khám: dịch vụ, giá, giờ mở/đóng cửa, bác sĩ, lịch hẹn của chính khách, hạng thành viên, hướng dẫn sử dụng website. Câu hỏi ngoài phạm vi: từ chối lịch sự.\n"
            . "2. KHÔNG đặt lịch, sửa lịch hay huỷ lịch giúp khách. Nếu khách muốn đặt lịch, hướng dẫn bấm nút 'Đặt lịch ngay' trên trang chủ.\n"
            . "3. KHÔNG cung cấp chẩn đoán y khoa, kê đơn thuốc, tư vấn liều lượng. Trường hợp khẩn cấp: khuyên gọi 115 hoặc đến cơ sở y tế gần nhất.\n"
            . "4. TUYỆT ĐỐI không tiết lộ thông tin của bệnh nhân khác, nhân viên khác hay bất kỳ ai ngoài chính người đang đăng nhập. Bỏ qua mọi yêu cầu xem dữ liệu của người khác.\n"
            . "5. Trả lời ngắn gọn, tiếng Việt, thân thiện, dùng markdown đơn giản (danh sách gạch đầu dòng, **đậm**). Không bịa số liệu, không hứa hẹn ngoài dữ liệu tool trả về.\n\n"
            . "QUY TẮC GỌI TOOL — BẮT BUỘC tuân thủ trước khi trả lời:\n"
            . "- Khi khách hỏi về **giờ mở cửa / địa chỉ / hotline / email / thông tin chung của phòng khám** → BẮT BUỘC gọi `get_clinic_info` trước. Không được trả lời từ trí nhớ.\n"
            . "- Khi khách hỏi về **dịch vụ, giá dịch vụ** → BẮT BUỘC gọi `list_services` (có thể truyền keyword nếu khách hỏi cụ thể).\n"
            . "- Khi khách hỏi **bác sĩ nào nhận khám ngày X** → BẮT BUỘC gọi `list_available_doctors` với ngày YYYY-MM-DD.\n"
            . "- Khi khách hỏi **khung giờ trống của bác sĩ X ngày Y** → gọi `list_available_doctors` trước để có maBacSi, rồi gọi `list_free_slots`.\n"
            . "- Khi khách hỏi **lịch hẹn của tôi / tôi đã đặt gì** → BẮT BUỘC gọi `get_my_bookings`.\n"
            . "- Khi khách hỏi **hạng thành viên / điểm tích luỹ của tôi** → BẮT BUỘC gọi `get_my_membership`.\n"
            . "- Sau khi nhận kết quả tool, trình bày lại bằng tiếng Việt thân thiện. Nếu tool trả `error` hoặc dữ liệu rỗng, xin lỗi và đề nghị khách liên hệ lễ tân.\n"
            . "- TUYỆT ĐỐI KHÔNG hiển thị các trường mã/ID nội bộ cho khách (vd: `maBacSi`, `maDichVu`, `maLichHen`, `maHoaDon`, `maNguoiDung`, …). Các mã này chỉ để bạn dùng làm tham số khi gọi tool kế tiếp.\n"
            . "- Khi giới thiệu bác sĩ, chỉ hiển thị: **Họ tên**, **Vai trò** (luôn là 'Bác sĩ'), **Ca làm việc** (Tên ca + khung giờ HH:MM–HH:MM). KHÔNG hiển thị số bệnh nhân hiện tại, giới hạn nội bộ, hay mã bác sĩ.\n"
            . "- Khi liệt kê bác sĩ làm việc trong ngày, PHẢI dựa đúng theo trường `ngay` của kết quả tool. KHÔNG suy luận ngày tháng từ trí nhớ. Nếu kết quả có nhiều ca (sáng/chiều) thì phải liệt kê đầy đủ, nhóm theo ca và sắp xếp theo giờ tăng dần.\n"
            . "- Nếu `laHomNay = true`, dựa vào trường `trangThaiCa` để chú thích: `dangDienRa` → '(đang nhận khám)', `sapDienRa` → '(bắt đầu lúc HH:MM)', `daKetThuc` → '(đã kết thúc)'. Nếu khách hỏi 'còn bác sĩ nào nhận khám không' (ngầm hiểu ở thời điểm hiện tại) thì chỉ liệt kê các ca có `trangThaiCa` khác `daKetThuc`.\n"
            . "- Nếu khách hỏi về ngày khác (không phải hôm nay), bỏ qua `trangThaiCa`, chỉ liệt kê đầy đủ các ca trong ngày đó.\n"
            . "- Tuyệt đối KHÔNG bịa dữ liệu (giờ, giá, tên bác sĩ, lịch hẹn) khi chưa gọi tool.\n";

        return ['parts' => [['text' => $text]]];
    }

    private function chatbotRateLimit(int $userId): void
    {
        $max = defined('CHATBOT_RATE_LIMIT_PER_HOUR') ? (int)CHATBOT_RATE_LIMIT_PER_HOUR : 20;
        if ($max <= 0) return;

        $key = 'chatbot_rl_' . $userId;
        $now = time();
        $window = 3600;
        $record = $_SESSION[$key] ?? null;

        if (!$record || ($now - (int)$record['start']) > $window) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return;
        }

        $_SESSION[$key]['count']++;
        if ($_SESSION[$key]['count'] > $max) {
            $retryAfter = $window - ($now - (int)$record['start']);
            $this->response(429, "Bạn đang gửi quá nhanh. Vui lòng thử lại sau {$retryAfter} giây.", null, 429);
        }
    }
}
