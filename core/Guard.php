<?php
declare(strict_types=1);

namespace RetroBB\Core;

/**
 * The one way a request is refused or sent home (issue #36).
 *
 * A controller says what the visitor must be, then does its work. Guard either
 * returns (the visitor qualifies) or renders the board's normal page and ends
 * the request — so a controller never writes a status code, an error-view
 * name, or `http_response_code`/`View::render`/`exit` itself.
 *
 *   Guard::requireAdmin();                 // staff page
 *   Guard::requireSuper();                 // site-critical action
 *   Guard::csrf('/admin');                 // every POST handler, before the write
 *   Guard::notFound('/admin/category/3/edit'); // a missing record
 *   Guard::flashThenReturn('/admin/structure', 'Saved.'); // after a write
 *
 * Refusals always render the board chrome and a message. A failed CSRF check
 * re-shows the submitted fields behind a fresh token, so a long form is not
 * silently thrown away.
 *
 * Guests are refused by requireAdmin() (they are not staff) with the normal
 * 403 page, so AdminCP never reveals whether a page exists. requireLogin() is
 * for member-only pages: it sends guests to the login form and brings them
 * back afterwards. Topic/report/PM/profile callers adopt these in #37/#38;
 * AdminCP is the first caller.
 *
 * Return paths: every destination goes through localPath(), so a request
 * value can never become an off-site redirect. HTTP_REFERER is never
 * consulted — pass the local path you mean.
 */
final class Guard
{
    /** Send guests to the login form, then back to $next (the current URL by default). */
    public static function requireLogin(string $next = ''): void
    {
        if (Auth::check()) {
            return;
        }
        $to = self::localPath($next !== '' ? $next : Router::currentPath());
        redirect('/login?next=' . rawurlencode($to));
    }

    /** Require staff (moderator or administrator). */
    public static function requireModerator(): void
    {
        if (!Auth::isMod()) {
            self::forbidden();
        }
    }

    /** Require an administrator. */
    public static function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            self::forbidden();
        }
    }

    /** Require a super administrator. */
    public static function requireSuper(): void
    {
        if (!Auth::isSuper()) {
            self::forbidden();
        }
    }

    /** The record does not exist: render the 404 page and stop. */
    public static function notFound(string $path = ''): never
    {
        http_response_code(404);
        View::render('errors/404', ['path' => $path]);
        exit;
    }

    /**
     * Enforce the posted form token. On failure, render a normal page that
     * repeats the submitted fields behind a fresh token so the visitor can
     * retry; the action itself never runs. $cancel is where "Cancel" goes.
     *
     * Call once, first thing in a POST handler. File uploads are not repeated
     * (HTML cannot pre-fill them); every other scalar field is kept.
     */
    public static function csrf(string $cancel = '/'): void
    {
        $token = $_POST['csrf'] ?? null;
        if (Csrf::verify(is_string($token) ? $token : null)) {
            return;
        }
        $fields = [];
        foreach ($_POST as $name => $value) {
            if ($name === 'csrf' || !is_scalar($value)) {
                continue;
            }
            $fields[(string) $name] = (string) $value;
        }
        View::render('errors/refused', [
            'pageTitle' => 'Session expired — ' . board_name(),
            'title' => 'Session expired',
            'message' => 'Your security token expired, so nothing was saved. Your input is below — send it again.',
            'action' => self::localPath(Router::currentPath()),
            'cancel' => self::localPath($cancel),
            'fields' => $fields,
        ]);
        exit;
    }

    /** Flash a message and redirect to a local path (never off-site). */
    public static function flashThenReturn(string $path, string $message, bool $ok = false): never
    {
        $_SESSION[$ok ? 'flash_ok' : 'flash_error'] = $message;
        redirect(self::localPath($path));
    }

    /**
     * Return $path when it is local (`/…`), otherwise $fallback. Rejects
     * absolute URLs, scheme-relative `//host`, backslash tricks and control
     * characters (header injection); the fallback is checked the same way and
     * finally collapses to `/`.
     */
    public static function localPath(?string $path, string $fallback = '/'): string
    {
        foreach ([$path, $fallback, '/'] as $candidate) {
            if (self::isLocal($candidate)) {
                return $candidate;
            }
        }
        return '/';
    }

    private static function isLocal(?string $path): bool
    {
        return $path !== null && $path !== ''
            && $path[0] === '/'
            && !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) === 0;
    }

    private static function forbidden(): never
    {
        http_response_code(403);
        View::render('errors/403', ['pageTitle' => 'Forbidden']);
        exit;
    }
}
