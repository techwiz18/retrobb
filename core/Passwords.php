<?php
declare(strict_types=1);

namespace RetroBB\Core;

/**
 * Password verification across schemes. Modern accounts use password_hash();
 * imported accounts keep working with their original hash until first login,
 * when the hash is upgraded to modern (see AuthController::loginSubmit).
 *
 * Schemes in users.auth_scheme: 'modern' (default), 'phpbb' (phpass $H$),
 * 'smf' (bcrypt or SHA-1 of lowercase(username) + password).
 */
class Passwords
{
    public static function verify(string $password, array $user): bool
    {
        if ($password === '') {
            return false;
        }
        $hash = (string) ($user['password_hash'] ?? '');
        $scheme = (string) ($user['auth_scheme'] ?? 'modern');
        if ($scheme === 'phpbb' || str_starts_with($hash, '$H$')) {
            return self::phpassCheck($password, $hash);
        }
        if ($scheme === 'smf') {
            $pre = strtolower((string) $user['username']) . $password;
            if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2b$') || str_starts_with($hash, '$2a$')) {
                return password_verify($pre, $hash);
            }
            if (preg_match('/^[0-9a-f]{40}$/i', $hash)) {
                return hash_equals(strtolower($hash), sha1($pre));
            }
            return false;
        }
        return password_verify($password, $hash);
    }

    /** Portable phpass ($H$) check, as used by phpBB/WPMU-era hashes. */
    public static function phpassCheck(string $password, string $hash): bool
    {
        if (strlen($hash) !== 34 || !str_starts_with($hash, '$H$')) {
            return false;
        }
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $count = strpos($itoa64, $hash[3]);
        if ($count === false || $count < 7 || $count > 30) {
            return false;
        }
        $salt = substr($hash, 4, 8);
        $hx = md5($salt . $password, true);
        $n = 1 << $count;
        for ($i = 0; $i < $n; $i++) {
            $hx = md5($hx . $password, true);
        }
        return hash_equals($hash, '$H$' . $hash[3] . $salt . self::encode64($hx, 16));
    }

    /** Portable phpass hash (for tests and tooling — logins use verify()). */
    public static function phpassHash(string $password, string $salt8, int $countLog2 = 8): string
    {
        $salt8 = substr(str_pad($salt8, 8, '0'), 0, 8);
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $hx = md5($salt8 . $password, true);
        $n = 1 << max(7, min(30, $countLog2));
        for ($i = 0; $i < $n; $i++) {
            $hx = md5($hx . $password, true);
        }
        return '$H$' . $itoa64[max(7, min(30, $countLog2))] . $salt8 . self::encode64($hx, 16);
    }

    private static function encode64(string $input, int $count): string
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $out = '';
        $i = 0;
        do {
            $v = ord($input[$i++]);
            $out .= $itoa64[$v & 0x3f];
            if ($i < $count) {
                $v |= ord($input[$i]) << 8;
            }
            $out .= $itoa64[($v >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $v |= ord($input[$i]) << 16;
            }
            $out .= $itoa64[($v >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $out .= $itoa64[($v >> 18) & 0x3f];
        } while ($i < $count);
        return $out;
    }
}
