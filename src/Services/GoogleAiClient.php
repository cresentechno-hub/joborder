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
    // completion call, especially under load. Split across up to two
    // attempts (see generateContent()) rather than one long wait, so a
    // transient blip gets a fresh connection instead of just running out
    // the clock. Total worst case (both attempts + the retry delay) stays
    // under PHP's common 30s default max_execution_time, so a slow call
    // still hits this catchable timeout instead of a hard script kill that
    // would bypass the try/catch fallback entirely.
    //
    // TEMP DIAGNOSTIC (2026-09-10): bumped from 12/2 attempts to 20/1
    // attempt to test whether production timeouts ("0 bytes received" at
    // ~12000ms on every call) are just slow, or a hard network block from
    // this host to generativelanguage.googleapis.com. Revert to 12/2 once
    // confirmed either way.
    private const PER_ATTEMPT_TIMEOUT_SECONDS = 20;
    private const MAX_ATTEMPTS = 1;
    private const RETRY_DELAY_SECONDS = 1;
    // Transient conditions worth one retry: rate-limited or the model
    // temporarily overloaded/unavailable — both observed in testing.
    // Anything else (bad key, bad request, etc.) is permanent, so retrying
    // it would just waste the extra round trip.
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

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

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $result = self::attempt($payload);

            if ($result['errno'] === 0 && $result['status'] >= 200 && $result['status'] < 300) {
                $decoded = json_decode((string) $result['body'], true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Google AI API returned an unparseable response.');
                }
                return $decoded;
            }

            $isRetryable = $result['errno'] !== 0 || in_array($result['status'], self::RETRYABLE_STATUSES, true);
            if ($attempt >= self::MAX_ATTEMPTS || !$isRetryable) {
                if ($result['errno'] !== 0) {
                    throw new RuntimeException("Google AI API request failed: {$result['error']}");
                }
                throw new RuntimeException("Google AI API returned HTTP {$result['status']}: " . substr((string) $result['body'], 0, 500));
            }

            sleep(self::RETRY_DELAY_SECONDS);
        }

        throw new RuntimeException('Google AI API request failed after retrying.');
    }

    /** @return array{errno: int, error: string, status: int, body: string|false} */
    private static function attempt(array $payload): array
    {
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
            CURLOPT_TIMEOUT        => self::PER_ATTEMPT_TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['errno' => $errno, 'error' => $error, 'status' => $status, 'body' => $body];
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
