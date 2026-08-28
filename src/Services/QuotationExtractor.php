<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Best-effort extraction of quotation_no, customer_name, subject, and
 * total_cost from an uploaded quotation PDF, via the pdftotext
 * (poppler-utils) command-line tool — no OCR, no AI, no Composer
 * dependency. Only works for text-based PDFs (not scanned/photographed
 * documents), and is tuned against Cresentech's own quotation template:
 *
 *   Date      : 27th June 2026
 *   To        : Badan Pengurusan Bersama AmanSuri     <- customer_name
 *   Attn      : Mr. Haniz
 *   Our Ref   : QF0333/2026                            <- quotation_no
 *
 *   Subject : QUOTATION FOR REPLACEMENT FAULTY CCTV... <- subject
 *   ...
 *   TOTAL :                                            <- total_cost (nearby)
 *
 * Any field it can't confidently find is left null — the form always stays
 * editable, this is a convenience autofill, never a hard requirement.
 */
final class QuotationExtractor
{
    /**
     * @return array{quotation_no: ?string, customer_name: ?string, subject: ?string, total_cost: ?float}
     *
     * $pdfPath is typically a PHP upload tmp_name (e.g. "php1A2B.tmp") which
     * has no .pdf extension of its own — the caller is responsible for
     * having already checked the *original* uploaded filename ends in
     * .pdf. If this is actually some other file type, pdftotext will just
     * fail (non-zero exit) and every field comes back null, same as any
     * other extraction miss — no separate extension check needed here.
     */
    public static function extract(string $pdfPath): array
    {
        $result = ['quotation_no' => null, 'customer_name' => null, 'subject' => null, 'total_cost' => null];

        if (!is_file($pdfPath)) {
            return $result;
        }

        $layoutText = self::runPdfToText($pdfPath, true);
        if ($layoutText !== null) {
            $result['quotation_no'] = self::matchLabel($layoutText, 'Our Ref');
            $result['customer_name'] = self::matchLabel($layoutText, 'To');
            $result['subject'] = self::matchLabel($layoutText, 'Subject');
        }

        $rawText = self::runPdfToText($pdfPath, false);
        if ($rawText !== null) {
            $result['total_cost'] = self::matchTotal($rawText);
        }

        return $result;
    }

    private static function runPdfToText(string $pdfPath, bool $preserveLayout): ?string
    {
        $flag = $preserveLayout ? '-layout' : '';
        $cmd = trim(escapeshellarg(PDFTOTEXT_BINARY) . ' ' . $flag . ' ' . escapeshellarg($pdfPath) . ' -');

        $output = [];
        $exitCode = 0;
        @exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            return null;
        }

        return implode("\n", $output);
    }

    /** Finds "Label : value" on its own line, searched only in the document header (first ~1000 chars). */
    private static function matchLabel(string $text, string $label): ?string
    {
        $header = mb_substr($text, 0, 1000);
        if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*:\s*(.+)$/mi', $header, $m)) {
            $value = trim($m[1]);
            return $value !== '' ? $value : null;
        }
        return null;
    }

    /** Finds the TOTAL line, then takes the largest money-looking number within the next 200 chars. */
    private static function matchTotal(string $text): ?float
    {
        $pos = stripos($text, 'TOTAL');
        if ($pos === false) {
            return null;
        }

        $window = mb_substr($text, $pos, 200);
        if (!preg_match_all('/[\d,]+\.\d{2}/', $window, $matches) || empty($matches[0])) {
            return null;
        }

        $numbers = array_map(
            static fn (string $n): float => (float) str_replace(',', '', $n),
            $matches[0]
        );

        return max($numbers);
    }
}
