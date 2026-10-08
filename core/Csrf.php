<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Csrf
{
    public static function token(): string
    {
        Auth::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): bool
    {
        Auth::startSession();
        $sess = $_SESSION['csrf'] ?? '';
        return $token !== null && $sess !== '' && hash_equals($sess, $token);
    }
}
