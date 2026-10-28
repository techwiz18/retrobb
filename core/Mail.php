<?php
declare(strict_types=1);

namespace RetroBB\Core;

/** Outgoing mail (password resets). Plain PHP mail(); shared hosts vary. */
class Mail
{
    public static function from(): string
    {
        $cfg = trim(setting('mail_from', ''));
        if ($cfg !== '' && filter_var($cfg, FILTER_VALIDATE_EMAIL)) {
            return $cfg;
        }
        // Never derive the sender domain from the request Host (H1): only the
        // configured board_url may supply it.
        $host = (string) (parse_url(setting('board_url', ''), PHP_URL_HOST) ?: '');
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host !== '' && !str_contains($host, ' ')) {
            return 'noreply@' . $host;
        }
        return 'noreply@localhost';
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = mb_substr(str_replace(["\r", "\n"], '', $subject), 0, 120);
        $headers = 'From: ' . self::from() . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
        $ok = @mail($to, $subject, $body, $headers);
        if (!$ok) {
            error_log('RetroBB mail failed to ' . $to . ' subj ' . $subject);
        }
        // Local testing only: when storage/dev-mail.php exists, mirror every
        // mail to storage/mail.log. Both live under storage/, which is
        // gitignored AND export-ignored from release zips — this path cannot
        // ship to a live build (no flag file, no logging, ever).
        if (is_file(dirname(__DIR__) . '/storage/dev-mail.php')) {
            @file_put_contents(
                dirname(__DIR__) . '/storage/mail.log',
                date('Y-m-d H:i:s') . " TO $to SUBJ $subject\n$body\n---\n",
                FILE_APPEND
            );
        }
        return $ok;
    }

    /** '' when board_url is unset — callers must not send the reset mail. */
    public static function resetLink(string $token): string
    {
        return canonical_absolute_url('/reset-password?token=' . $token);
    }
}
