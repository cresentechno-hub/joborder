<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Thin wrapper around the vendored PHPMailer (src/Vendor/PHPMailer/) for
 * SMTP delivery. Every send is best-effort: failures are logged via
 * error_log() and return false, never thrown out to the caller — a down
 * mail server must never block a job order save or the reminder cron.
 * Renders views/emails/*.php the same ob_start()/require()/ob_get_clean()
 * way Controller::view() renders regular views, so email templates stay
 * plain PHP like every other view in this app.
 */
final class Mailer
{
    /** @param array<string, mixed> $data */
    public static function send(string $toEmail, string $toName, string $subject, string $template, array $data = []): bool
    {
        if (SMTP_USERNAME === '' || SMTP_HOST === '') {
            return false; // not configured — silently skip, this is a convenience feature
        }

        try {
            $html = self::render($template, $data);

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout = 10; // seconds — a hung mail server shouldn't hang the request for long
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)));

            return $mail->send();
        } catch (Throwable $e) {
            error_log('Mailer: failed to send "' . $subject . '" to ' . $toEmail . ' — ' . $e->getMessage());
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    private static function render(string $template, array $data): string
    {
        $viewFile = ROOT_PATH . '/views/emails/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new PHPMailerException("Email template not found: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        return (string) ob_get_clean();
    }
}
