<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Claude-based extraction of the invoice number AND the customer's PO
 * No./Ref No. from an uploaded invoice document (PDF or a photo/scan of
 * one) — backs the "Read Invoice & Auto-Fill" button on the Add Comment
 * form (views/job_orders/_add_comment.php) and the standalone Edit Comment
 * page (views/job_orders/comment_edit.php). Same best-effort contract as
 * AiQuotationExtractor: never throws out to the caller. invoice_no falls
 * back to a filename-derived guess (the original, client-side-only
 * behavior this replaces) whenever the API key isn't configured or the
 * request fails; po_no has no sensible fallback and just comes back null
 * in that case — both fields always stay manually editable either way.
 */
final class AiInvoiceExtractor
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You extract two fields from a company's own sales invoice document
        (PDF or a photo/scan of one). The layout is typically a header
        block near a large "Invoice" heading, e.g.:

          Invoice
          No.  :  IN2608/0004                  <- invoice_no
          Date :  26/08/2026

          Debtor Code :   3000-P0004
          Contact Person :
          PO No./Ref No. :  ER-00351  QF0037/2026   <- po_no

        - invoice_no: the value labelled just "No." (or "Invoice No",
          "Invoice Number", "INV No.") directly under/beside the "Invoice"
          heading — usually starts with letters like "IN" followed by
          digits and a slash, e.g. IN2608/0004.
        - po_no: the value labelled "PO No.", "PO No./Ref No.", or
          "Purchase Order No." — this is the CUSTOMER's own reference, not
          the invoice number. If more than one reference is shown there
          (e.g. both a PO number and a quotation number, like
          "ER-00351 QF0037/2026"), return the whole label's value as one
          string exactly as printed.

        Do not confuse invoice_no with "Debtor Code" (an internal customer
        account code, e.g. "3000-P0004") — that is neither field.

        Always call the extract_invoice_fields tool exactly once. Use null
        for either field you cannot confidently find — never guess.
        PROMPT;

    private const TOOL_NAME = 'extract_invoice_fields';

    /** @return array{invoice_no: ?string, po_no: ?string, note?: string} */
    public static function extract(string $filePath, string $originalFilename): array
    {
        $mediaType = self::mediaType($originalFilename);
        $fallback = ['invoice_no' => self::filenameGuess($originalFilename), 'po_no' => null];

        if ($mediaType === null || !is_file($filePath)) {
            return $fallback;
        }

        try {
            $fields = self::extractViaClaude($filePath, $mediaType);
            if ($fields !== null && ($fields['invoice_no'] !== null || $fields['po_no'] !== null)) {
                return $fields;
            }
        } catch (Throwable $e) {
            error_log('AiInvoiceExtractor: falling back after Claude extraction failed — ' . $e->getMessage());
        }

        return $fallback + ['note' => 'Could not confidently read the invoice — using the file name for Invoice No. Please check/correct both fields.'];
    }

    /** null means "couldn't extract via Claude, let the caller fall back" @return array{invoice_no: ?string, po_no: ?string}|null */
    private static function extractViaClaude(string $filePath, string $mediaType): ?array
    {
        $data = file_get_contents($filePath);
        if ($data === false) {
            return null;
        }

        $sourceBlock = $mediaType === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($data)]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($data)]];

        $response = AnthropicClient::createMessage([
            'model'      => ANTHROPIC_MODEL,
            'max_tokens' => 256,
            'system'     => self::SYSTEM_PROMPT,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    $sourceBlock,
                    ['type' => 'text', 'text' => 'Extract the invoice number and PO No. from this document.'],
                ],
            ]],
            'tools' => [[
                'name'         => self::TOOL_NAME,
                'description'  => 'Records the extracted invoice number and PO No.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'invoice_no' => ['type' => ['string', 'null']],
                        'po_no'      => ['type' => ['string', 'null']],
                    ],
                    'required' => ['invoice_no', 'po_no'],
                ],
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
        ]);

        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::TOOL_NAME) {
                $input = $block['input'] ?? [];
                return [
                    'invoice_no' => self::asStringOrNull($input['invoice_no'] ?? null),
                    'po_no'      => self::asStringOrNull($input['po_no'] ?? null),
                ];
            }
        }

        return null;
    }

    private static function asStringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    /** The original behavior this feature replaces: strip the extension off the uploaded filename. */
    private static function filenameGuess(string $filename): ?string
    {
        $guess = pathinfo($filename, PATHINFO_FILENAME);
        return $guess !== '' ? $guess : null;
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
