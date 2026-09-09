<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Thin cURL wrapper around the Google Gemini (generateContent) API — no
 * Composer SDK, consistent with this project having no package manager at
 * all. Callers are responsible for catching failures and falling back; this
 * class never degrades gracefully on its own, it just throws.
 */
final class GoogleAiClient
{
    private const CONNECT_TIMEOUT_SECONDS = 5;
    // Gemini's "thinking" models can take noticeably longer than a plain
    // completion call, especially under load — give it more headroom than
    // the old Anthropic client needed before falling back. Kept under
    // PHP's common 30s default max_execution_time so a slow call still
    // hits this catchable timeout instead of a hard script kill that would
    // bypass the try/catch fallback entirely.
    private const TOTAL_TIMEOUT_SECONDS = 25;

    /**
     * @param array $payload the generateContent request body (contents, system_instruction, generationConfig, etc.)
     * @return array the decoded JSON response body
     * @throws RuntimeException if the key is missing, the request fails, or the response isn't valid JSON
     */
    public static function generateContent(array $payload): array
    {
        if (GOOGLE_AI_API_KEY === '') {
            throw new RuntimeException('GOOGLE_AI_API_KEY is not configured.');
        }

        $url = GOOGLE_AI_API_URL . '/models/' . GOOGLE_AI_MODEL . ':generateContent';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER     => [
                'x-goog-api-key: ' . GOOGLE_AI_API_KEY,
                'content-type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException("Google AI API request failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Google AI API returned HTTP {$status}: " . substr((string) $body, 0, 500));
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Google AI API returned an unparseable response.');
        }

        return $decoded;
    }

    /**
     * Pulls the model's JSON text out of a generateContent response and
     * decodes it. Returns null if the response doesn't have the expected
     * shape (e.g. the request was blocked by a safety filter).
     */
    public static function decodeJsonResponse(array $response): ?array
    {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || $text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
