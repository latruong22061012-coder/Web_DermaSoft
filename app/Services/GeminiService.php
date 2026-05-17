<?php
declare(strict_types=1);

/**
 * GeminiService — HTTP client mỏng cho Google Gemini Generative API.
 * Hỗ trợ function calling (tools) và system instruction.
 *
 * Lưu ý bảo mật:
 *  - API key đọc từ hằng GEMINI_API_KEY (set qua biến môi trường).
 *  - Không log payload của user; chỉ log mã lỗi/HTTP status để debug.
 */

namespace App\Services;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        $this->model = $model ?? (defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.0-flash');
        $this->endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode($this->model)
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Gọi Gemini generateContent.
     *
     * @param array $contents          Mảng `contents` theo schema Gemini.
     * @param array $tools             Mảng `tools` (functionDeclarations) — optional.
     * @param array|null $systemInstruction  System instruction (mảng parts) — optional.
     * @return array  Decoded response.
     * @throws \RuntimeException khi gọi thất bại.
     */
    public function generate(array $contents, array $tools = [], ?array $systemInstruction = null): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('GEMINI_API_KEY chưa được cấu hình.');
        }

        $payload = ['contents' => $contents];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            // Cho phép model gọi tool tự động
            $payload['toolConfig'] = [
                'functionCallingConfig' => ['mode' => 'AUTO'],
            ];
        }

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        $payload['generationConfig'] = [
            'temperature' => 0.4,
            'topP' => 0.9,
            'maxOutputTokens' => 1024,
        ];

        $payload['safetySettings'] = [
            ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ];

        return $this->postWithRetry($payload, 1);
    }

    private function postWithRetry(array $payload, int $retries): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $retries) {
            $attempt++;
            try {
                return $this->post($payload);
            } catch (\RuntimeException $e) {
                $lastError = $e;
                $code = $e->getCode();
                // Chỉ retry với 429 hoặc 5xx
                if ($code !== 429 && ($code < 500 || $code >= 600)) {
                    throw $e;
                }
                // backoff ngắn
                usleep(400000);
            }
        }

        throw $lastError ?? new \RuntimeException('Không gọi được Gemini.');
    }

    private function post(array $payload): array
    {
        $url = $this->endpoint . '?key=' . rawurlencode($this->apiKey);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \RuntimeException('Không serialize được payload.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            error_log('[GeminiService] cURL error: ' . $curlErr);
            throw new \RuntimeException('Không kết nối được dịch vụ AI.', 503);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            // Log status, không log toàn bộ body của user
            error_log('[GeminiService] HTTP ' . $httpCode . ' body=' . substr((string)$resp, 0, 500));
            throw new \RuntimeException('Dịch vụ AI trả lỗi.', $httpCode);
        }

        $decoded = json_decode((string)$resp, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Phản hồi AI không hợp lệ.', 502);
        }

        return $decoded;
    }
}
