<?php
declare(strict_types=1);

namespace RetroBB\Controllers\Admin;

use RetroBB\Core\Auth;
use RetroBB\Core\Db;
use RetroBB\Core\Guard;
use RetroBB\Core\Settings;
use RetroBB\Core\View;

/** Dashboard, settings pages, mod log and the repair/upgrade tools
 *  (issue #47; #16 extends this area). */
class MaintenanceAdminController
{
    /** @return array */
    private function settingsRows(): array
    {
        return Db::pdo()->query('SELECT * FROM settings')->fetchAll();
    }

    public function index(): void
    {
        Guard::requireAdmin();
        $active = \RetroBB\Models\Moderation::activeBanCount();
        View::render('admin/dashboard', [
            'stats' => \RetroBB\Models\Board::stats(),
            'openReports' => \RetroBB\Models\Report::openCount(),
            'activeBans' => $active,
            'installPresent' => is_file(dirname(__DIR__, 2) . '/public/install.php'),
            // Older installs renamed rather than deleted the wizard; that copy
            // is still executable, so keep nagging until it is gone.
            'installDisabledPresent' => is_file(dirname(__DIR__, 2) . '/public/install.disabled.php'),
            'update' => update_check(),
            // Board URL is required for safe absolute links: without it
            // canonicals fall back to relative URLs, the sitemap omits locs,
            // and password-reset mail refuses to send. Always warn.
            'canonicalUnset' => setting('board_url', '') === '',
            'pageTitle' => 'AdminCP — ' . board_name(),
        ]);
    }

    public function settingsPage(): void
    {
        Guard::requireSuper();
        View::render('admin/settings', ['settings' => $this->settingsRows(), 'pageTitle' => 'Settings — AdminCP']);
    }

    public function featuresPage(): void
    {
        Guard::requireSuper();
        View::render('admin/features', ['settings' => $this->settingsRows(), 'pageTitle' => 'Features — AdminCP']);
    }

    public function spamPage(): void
    {
        Guard::requireSuper();
        View::render('admin/spam', ['settings' => $this->settingsRows(), 'pageTitle' => 'Spam protection — AdminCP']);
    }

    public function saveSettings(): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        // The page targeted by the form owns its keys (Settings::DEFS): a
        // settings POST can never write captcha rows, nor spam a board name.
        $page = ($_POST['return'] ?? '') === 'spam' ? 'spam' : 'settings';
        $pdo = Db::pdo();
        $upsert = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        foreach (array_keys(Settings::forPage($page)) as $k) {
            if (!isset($_POST[$k])) {
                continue;
            }
            $v = Settings::coerce($k, (string) $_POST[$k]);
            if ($v === null) {
                if ($k === 'board_url') {
                    $_SESSION['flash_error'] = 'Board URL was not saved — use a full address like https://example.com (path must match the install subpath, if any).';
                }
                continue;
            }
            $upsert->execute([$k, $v]);
        }
        redirect($this->settingsReturn());
    }

    public function saveFeatures(): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        $vals = [];
        foreach (Settings::forPage('features') as $k => $def) {
            // Checkboxes are absent when unticked; typed rules coerce the rest.
            $vals[$k] = empty($def['bool'])
                ? (Settings::coerce($k, (string) ($_POST[$k] ?? '')) ?? (string) $def['default'])
                : (isset($_POST[$k]) ? '1' : '0');
        }
        // At least one theme mode must stay on, or members get a broken picker.
        if ($vals['theme_light'] !== '1' && $vals['theme_dark'] !== '1' && $vals['theme_auto'] !== '1') {
            $vals['theme_auto'] = '1';
        }
        $effectiveThemes = array_values(array_filter(
            ['light', 'dark', 'auto'],
            fn($m) => $vals['theme_' . $m] === '1'
        ));
        // A default nobody may pick is a contradiction: coerce it to an
        // allowed mode (prefer auto) and tell the admin it happened.
        $fixedTheme = false;
        if (!in_array($vals['default_theme'], $effectiveThemes, true)) {
            foreach (['auto', 'light', 'dark'] as $m) {
                if (in_array($m, $effectiveThemes, true)) {
                    $vals['default_theme'] = $m;
                    break;
                }
            }
            $fixedTheme = true;
        }
        $upsert = Db::pdo()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        foreach ($vals as $k => $v) {
            $upsert->execute([$k, $v]);
        }
        redirect('/admin/features?saved=1' . ($fixedTheme ? '&fixed-theme=1' : ''));
    }

    /** Where a settings POST should land: /admin/settings or /admin/spam. */
    private function settingsReturn(): string
    {
        $to = (string) ($_POST['return'] ?? 'settings');
        return $to === 'spam' ? '/admin/spam?saved=1' : '/admin/settings?saved=1';
    }

    public function modlogPage(): void
    {
        Guard::requireAdmin();
        $modpage = max(1, (int) ($_GET['modpage'] ?? 1));
        $modlog = \RetroBB\Core\Modlog::latest($modpage, 50);
        View::render('admin/modlog', [
            'modlog' => $modlog['entries'], 'modtotal' => $modlog['total'],
            'modpage' => $modpage, 'modpages' => max(1, (int) ceil($modlog['total'] / 50)),
            'pageTitle' => 'Mod log — AdminCP',
        ]);
    }

    /**
     * Super-only: re-render stored post/PM HTML from BBCode (C1 remediation).
     * The live board deploys through cPanel without guaranteed SSH, so this is
     * the no-CLI path to `php bin/rebuild-html.php`. Resumable: the scan cursor
     * lives in the session, so repeated clicks continue where the last stopped.
     * Plugins were loaded by the front controller, so `post_body_html` filters
     * apply exactly as on the web.
     */
    public function repairHtml(): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        try {
            @set_time_limit(120);
            $cursors = $_SESSION['html_repair_cursor'] ?? [];
            $stats = \RetroBB\Core\HtmlRepair::run(0, false, false, 500, 2000, is_array($cursors) ? $cursors : []);
            if (!empty($stats['next']['done'])) {
                unset($_SESSION['html_repair_cursor']);
            } else {
                $_SESSION['html_repair_cursor'] = $stats['next'];
            }
            $msg = sprintf(
                'Re-rendered %d stored post/PM %s (scanned %d, skipped %d, conflicted %d).',
                $stats['changed'],
                $stats['changed'] === 1 ? 'body' : 'bodies',
                $stats['scanned'],
                $stats['skipped'],
                $stats['conflicted']
            );
            if (!empty($stats['more'])) {
                $msg .= ' Click again to continue.';
            }
            if ($stats['failed'] > 0) {
                $_SESSION['flash_error'] = $msg . sprintf(' %d row(s) failed — see the PHP error log.', $stats['failed']);
            } else {
                $_SESSION['flash_ok'] = $msg;
            }
        } catch (\Throwable $t) {
            error_log('RetroBB html repair failed: ' . $t->getMessage());
            $_SESSION['flash_error'] = 'Repair failed — see the PHP error log.';
        }
        redirect('/admin');
    }

    /** One-click database updates after a file upgrade (mirrors bin/migrate.php). */
    public function runMigrate(): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        try {
            @set_time_limit(120);
            if (!defined('RETROBB_INTERNAL_MIGRATE')) {
                define('RETROBB_INTERNAL_MIGRATE', true);
            }
            // Don't let a query string become $argv (register_argc_argv=On):
            // the web path always means a plain migrate, never --seed/--force.
            $argv = ['migrate.php'];
            ob_start();
            require dirname(__DIR__, 2) . '/bin/migrate.php';
            ob_end_clean();
        } catch (\Throwable $t) {
            ob_end_clean();
            error_log('RetroBB web migrate failed: ' . $t->getMessage());
            Guard::flashThenReturn('/admin', 'Database updates failed — see the PHP error log, or run php bin/migrate.php by hand.');
        }
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'migrate', 'board', 0, 'database updates via AdminCP');
        redirect('/admin?migrated=1');
    }
}
