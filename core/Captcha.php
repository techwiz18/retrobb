<?php
declare(strict_types=1);

namespace RetroBB\Core;

/**
 * Pluggable CAPTCHA for registration (and later: guest posting).
 * Providers: honeypot (default, invisible), builtin (math question),
 * turnstile | hcaptcha | recaptcha (external, needs sitekey + secret).
 */
class Captcha
{
    public static function provider(): string
    {
        $p = setting('captcha_provider', 'honeypot');
        return in_array($p, ['honeypot', 'builtin', 'turnstile', 'hcaptcha', 'recaptcha'], true) ? $p : 'honeypot';
    }

    public static function isExternal(): bool
    {
        return in_array(self::provider(), ['turnstile', 'hcaptcha', 'recaptcha'], true);
    }

    public static function sitekey(): string
    {
        return setting('captcha_sitekey', '');
    }

    /** HTML to embed in the form. Call on GET (generates builtin challenge). */
    public static function widget(): string
    {
        $p = self::provider();
        // Honeypot is always on, whatever else is configured.
        $html = '<input type="text" name="website" value="" style="display:none!important" tabindex="-1" autocomplete="off" aria-hidden="true">';
        if ($p === 'builtin') {
            $a = random_int(2, 9);
            $b = random_int(2, 9);
            Auth::startSession();
            $_SESSION['captcha_answer'] = $a + $b;
            $html .= '<label>Human check: what is ' . $a . ' + ' . $b . '?<br><input type="text" name="captcha_answer" required inputmode="numeric" style="max-width:120px"></label><br><br>';
        } elseif (self::isExternal() && self::sitekey() !== '') {
            $key = htmlspecialchars(self::sitekey(), ENT_QUOTES, 'UTF-8');
            if ($p === 'turnstile') {
                $html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
                $html .= '<div class="cf-turnstile" data-sitekey="' . $key . '"></div><br>';
            } elseif ($p === 'hcaptcha') {
                $html .= '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
                $html .= '<div class="h-captcha" data-sitekey="' . $key . '"></div><br>';
            } else {
                $html .= '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
                $html .= '<div class="g-recaptcha" data-sitekey="' . $key . '"></div><br>';
            }
        }
        return $html;
    }

    /** @return array{ok: bool, error?: string} */
    public static function verify(array $post): array
    {
        // Honeypot: bots fill it, humans never see it.
        if (trim((string) ($post['website'] ?? '')) !== '') {
            return ['ok' => false, 'error' => 'Registration rejected.'];
        }
        $p = self::provider();
        if ($p === 'builtin') {
            Auth::startSession();
            $expected = $_SESSION['captcha_answer'] ?? null;
            unset($_SESSION['captcha_answer']);
            if ($expected === null || (int) ($post['captcha_answer'] ?? -1) !== (int) $expected) {
                return ['ok' => false, 'error' => 'Wrong answer to the human check.'];
            }
            return ['ok' => true];
        }
        if (self::isExternal()) {
            $secret = setting('captcha_secret', '');
            if ($secret === '') {
                return ['ok' => false, 'error' => 'CAPTCHA is misconfigured (missing secret).'];
            }
            $token = (string) ($post['cf-turnstile-response'] ?? $post['h-captcha-response'] ?? $post['g-recaptcha-response'] ?? '');
            if ($token === '') {
                return ['ok' => false, 'error' => 'Please complete the CAPTCHA.'];
            }
            return self::verifyExternal($p, $secret, $token);
        }
        return ['ok' => true];
    }

    private static function verifyExternal(string $provider, string $secret, string $token): array
    {
        $urls = [
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'hcaptcha' => 'https://api.hcaptcha.com/siteverify',
            'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
        ];
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 8,
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]),
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $raw = @file_get_contents($urls[$provider], false, $ctx);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'CAPTCHA check unavailable, try again.'];
        }
        $data = json_decode($raw, true);
        if (is_array($data) && ($data['success'] ?? false) === true) {
            return ['ok' => true];
        }
        return ['ok' => false, 'error' => 'CAPTCHA verification failed.'];
    }
}
