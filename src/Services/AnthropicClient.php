<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Thin cURL wrapper around the Anthropic Messages API — no Composer SDK,
 * consistent with this project having no package manager at all. Callers
 * are responsible for catching failures and falling back; this class never
 * degrades gracefully on its own, it just throws.
 */
final class AnthropicClient
{
    private const API_VERSION = '2023-06-01';
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const TOTAL_TIMEOUT_SECONDS = 20;

    /**
     * @param array $payload the Messages API request body (model, messages, tools, etc.)
     * @return array the decoded JSON response body
     * @throws RuntimeException if the key is missing, the request fails, or the response isn't valid JSON
     */
    public static function createMessage(array $payload): array
    {
        if (ANTHROPIC_API_KEY === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $ch = curl_init(ANTHROPIC_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: ' . self::API_VERSION,
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
            throw new RuntimeException("Anthropic API request failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Anthropic API returned HTTP {$status}: " . substr((string) $body, 0, 500));
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Anthropic API returned an unparseable response.');
        }

        return $decoded;
    }
}
