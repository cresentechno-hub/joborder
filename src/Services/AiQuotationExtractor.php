<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Gemini-based replacement for QuotationExtractor's pdftotext+regex
 * approach — layout-independent, and (unlike QuotationExtractor) also
 * handles scanned/photographed quotations, not just text PDFs. Same
 * best-effort contract: never throws out to the caller, any field it can't
 * confidently find comes back null, the form always stays editable.
 *
 * Falls back to QuotationExtractor (PDFs only) whenever the API key isn't
 * configured, the request fails/times out, or the response can't be parsed
 * — so this is a strict enhancement over the old path, never a regression.
 */
final class AiQuotationExtractor
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You extract structured data from a company's own sales quotation
        document (PDF or a photo/scan of one). The layout is usually a
        header block with labelled fields, e.g.:

          Date      : 27th June 2026
          To        : Badan Pengurusan Bersama AmanSuri     <- customer_name
          Attn      : Mr. Haniz
          Our Ref   : QF0333/2026                            <- quotation_no

          Subject : QUOTATION FOR REPLACEMENT FAULTY CCTV...  <- subject
          ...
          TOTAL :                                             <- total_cost

        Field meanings:
        - quotation_no: the reference/quotation number (often labelled "Our
          Ref", "Ref No", "Quotation No", or similar).
        - customer_name: the company or person the quotation is addressed
          to (often labelled "To" or "Customer").
        - subject: the short description of what's being quoted for.
        - total_cost: the final GRAND TOTAL amount, not a subtotal, not a
          per-line price. If tax/discount lines exist, use the bottom-line
          total after them.

        Always call the extract_quotation tool exactly once. Use null for
        any field you cannot confidently find — never guess.
        PROMPT;

    private const TOOL_NAME = 'extract_quotation';

    /**
     * @return array{quotation_no: ?string, customer_name: ?string, subject: ?string, total_cost: ?float}
     */
    public static function extract(string $filePath, string $originalFilename): array
    {
        $fallbackNote = 'Auto-fill only works for PDF/JPG/PNG quotations — please enter the details manually.';
        $empty = ['quotation_no' => null, 'customer_name' => null, 'subject' => null, 'total_cost' => null];

        $mediaType = self::mediaType($originalFilename);
        if ($mediaType === null || !is_file($filePath)) {
            return $empty + ['note' => $fallbackNote];
        }

        try {
            $result = self::extractViaGemini($filePath, $mediaType);
            if ($result !== null) {
                return $result;
            }
        } catch (Throwable $e) {
            error_log('AiQuotationExtractor: falling back after Gemini extraction failed — ' . $e->getMessage());
        }

        // Fallback: pdftotext+regex only understands PDFs; for images there's
        // no equivalent, so degrade to manual entry exactly as before.
        if ($mediaType === 'application/pdf') {
            return QuotationExtractor::extract($filePath);
        }

        return $empty + ['note' => $fallbackNote];
    }

    /** @return array{quotation_no: ?string, customer_name: ?string, subject: ?string, total_cost: ?float}|null null means "couldn't extract, let the caller fall back" */
    private static function extractViaGemini(string $filePath, string $mediaType): ?array
    {
        $data = file_get_contents($filePath);
        if ($data === false) {
            return null;
        }

        $response = GoogleAiClient::generateContent([
            'system_instruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['inline_data' => ['mime_type' => $mediaType, 'data' => base64_encode($data)]],
                    ['text' => 'Extract the quotation details from this document.'],
                ],
            ]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                // Gemini spends a chunk of this budget on internal
                // "thinking" tokens before writing the JSON answer — too
                // low a cap truncates the actual output mid-string.
                'max_output_tokens'  => 2048,
                'response_schema'    => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'quotation_no'  => ['type' => 'STRING', 'nullable' => true],
                        'customer_name' => ['type' => 'STRING', 'nullable' => true],
                        'subject'       => ['type' => 'STRING', 'nullable' => true],
                        'total_cost'    => ['type' => 'NUMBER', 'nullable' => true],
                    ],
                    'required' => ['quotation_no', 'customer_name', 'subject', 'total_cost'],
                ],
            ],
        ]);

        $fields = GoogleAiClient::decodeJsonResponse($response);
        if ($fields === null) {
            return null;
        }

        return [
            'quotation_no'  => self::asStringOrNull($fields['quotation_no'] ?? null),
            'customer_name' => self::asStringOrNull($fields['customer_name'] ?? null),
            'subject'       => self::asStringOrNull($fields['subject'] ?? null),
            'total_cost'    => is_numeric($fields['total_cost'] ?? null) ? (float) $fields['total_cost'] : null,
        ];
    }

    private static function asStringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private static function mediaType(string $filename): ?string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf'         => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            default       => null,
        };
    }
}
