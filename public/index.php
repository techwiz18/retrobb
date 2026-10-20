<?php
declare(strict_types=1);
// Front controller. All pretty URLs rewrite here.
$root = dirname(__DIR__);
require_once $root . '/core/helpers.php';
require_once $root . '/core/Db.php';
require_once $root . '/core/Slug.php';
require_once $root . '/core/Hooks.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/Csrf.php';
require_once $root . '/core/Passwords.php';
require_once $root . '/core/Mail.php';
require_once $root . '/core/BBCode.php';
require_once $root . '/core/HtmlRepair.php';
require_once $root . '/core/Mentions.php';
require_once $root . '/core/Import/Base.php';
require_once $root . '/core/Import/Phpbb.php';
require_once $root . '/core/Import/Smf.php';
require_once $root . '/core/View.php';
require_once $root . '/core/Router.php';
require_once $root . '/core/Plugins.php';
require_once $root . '/core/Modlog.php';
require_once $root . '/core/Captcha.php';
require_once $root . '/models/User.php';
require_once $root . '/models/Board.php';
require_once $root . '/models/Topic.php';
require_once $root . '/models/Post.php';
require_once $root . '/models/Report.php';
require_once $root . '/models/Moderation.php';
require_once $root . '/models/PasswordReset.php';
require_once $root . '/models/Search.php';
require_once $root . '/models/TopicRead.php';
require_once $root . '/models/Notification.php';
require_once $root . '/models/Reaction.php';
require_once $root . '/models/Pm.php';
require_once $root . '/controllers/HomeController.php';
require_once $root . '/controllers/ForumController.php';
require_once $root . '/controllers/TopicController.php';
require_once $root . '/controllers/ReportController.php';
require_once $root . '/controllers/ModController.php';
require_once $root . '/controllers/AuthController.php';
require_once $root . '/controllers/ProfileController.php';
require_once $root . '/controllers/AdminController.php';
require_once $root . '/controllers/ImportController.php';
require_once $root . '/controllers/SitemapController.php';
require_once $root . '/controllers/SearchController.php';
require_once $root . '/controllers/AlertController.php';
require_once $root . '/controllers/ReactionController.php';
require_once $root . '/controllers/PmController.php';

use RetroBB\Core\Auth;
use RetroBB\Core\Plugins;
use RetroBB\Core\Router;

// Never print fatals to visitors (paths, SQL, versions); the handler above
// logs them server-side instead. error_log is untouched, so docker logs
// and the PHP error log keep working.
@ini_set('display_errors', '0');

// Last-resort error page: never leak a stack trace to visitors.
set_exception_handler(function (Throwable $e) {
    error_log('RetroBB fatal: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    try {
        \RetroBB\Core\View::render('errors/500', ['pageTitle' => 'Error — ' . board_name()]);
    } catch (Throwable) {
        echo '<h1>Something broke.</h1>';
    }
    exit;
});

// Baseline security headers (CSP deliberately left to site owners: external
// CAPTCHA providers need their own script allowlists).
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

Auth::validateSession();

// DB must exist and have tables — else send to installer. (validateSession
// runs first but only touches the DB when a session cookie is present.)
// NOTE: plain require, never require_once — config.php returns a value,
// and require_once would hand us `true` on second inclusion.
// currentPath() is raw (base prefix intact) — accept the installer file
// with or without the subpath prefix so /forum/install.php doesn't loop.
// Matched tightly: '/installer' and friends must fall through to the
// installer redirect, not into a 500 on the missing config.
$reqPath = Router::currentPath();
$bp = base_path();
$reqStripped = ($bp !== '' && ($reqPath === $bp || str_starts_with($reqPath, $bp . '/')))
    ? (substr($reqPath, strlen($bp)) ?: '/') : $reqPath;
$onInstall = $reqPath === '/install.php' || $reqStripped === '/install.php';
if (!is_file($root . '/config.php') && !$onInstall) {
    // Fresh upload: no config yet, so there is nothing to connect to.
    redirect('/install.php');
}
require $root . '/config.php';
$needsInstall = false;
try {
    \RetroBB\Core\Db::pdo()->query('SELECT 1 FROM users LIMIT 1');
} catch (Throwable) {
    $needsInstall = true;
}
if ($needsInstall && !$onInstall) {
    redirect('/install.php');
}

Plugins::load();
\RetroBB\Core\Hooks::do_action('boot');

// Canonical-host enforcement (e.g. subdomain → hub-subpath consolidation).
// Off unless enabled via config.php / RETROBB_FORCE_BOARD_HOST. When on,
// requests whose Host differs from board_url's host get a 301 to the board
// URL — except requests carrying the trusted proxy header (the reverse
// proxy must send it; presence means "I already routed this"). Lives in
// PHP rather than .htaccess so site owners never lose it to a re-upload,
// and canonical_url() keeps prefixed paths from doubling (/forum/x stays
// /forum/x instead of becoming /forum/forum/x).
$forceHost = getenv('RETROBB_FORCE_BOARD_HOST');
if ($forceHost === false) {
    try {
        $cfgHost = require $root . '/config.php';
        $forceHost = !empty($cfgHost['force_board_host']) ? '1' : '';
    } catch (Throwable) {
        $forceHost = '';
    }
}
if (PHP_SAPI !== 'cli' && $forceHost !== '' && $forceHost !== '0' && isset($_SERVER['HTTP_HOST'])) {
    $boardUrl = setting('board_url', '');
    $parts = ($boardUrl !== '' && preg_match('#^https?://#i', $boardUrl)) ? parse_url($boardUrl) : null;
    $proxyName = getenv('RETROBB_PROXY_HEADER');
    if ($proxyName === false) {
        try {
            $cfgProxy = require $root . '/config.php';
            $proxyName = $cfgProxy['trusted_proxy_header'] ?? '';
        } catch (Throwable) {
            $proxyName = '';
        }
    }
    $proxied = $proxyName !== '' && !empty($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', (string) $proxyName))]);
    $curHost = strtolower(explode(':', (string) $_SERVER['HTTP_HOST'])[0]);
    if ($parts !== null && !$proxied && strcasecmp($curHost, strtolower($parts['host'] ?? '')) !== 0) {
        $target = canonical_url(Router::currentPath());
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        redirect($target . ($qs !== '' ? '?' . $qs : ''), 301);
    }
}

$r = new Router();
$r->get('#^/$#', [\RetroBB\Controllers\HomeController::class, 'index']);
$r->get('#^/forum/([^/]+)$#', [\RetroBB\Controllers\ForumController::class, 'show']);
$r->get('#^/topic/([^/]+?)(/page-\d+)?$#', [\RetroBB\Controllers\TopicController::class, 'show']);
$r->post('#^/topic/([^/]+)/reply$#', [\RetroBB\Controllers\TopicController::class, 'reply']);
$r->get('#^/new-topic/(\d+)$#', [\RetroBB\Controllers\TopicController::class, 'newForm']);
$r->post('#^/new-topic/(\d+)$#', [\RetroBB\Controllers\TopicController::class, 'newSubmit']);
$r->post('#^/topic/(\d+)/(pinned|locked)$#', [\RetroBB\Controllers\TopicController::class, 'toggleFlag']);
  $r->post('#^/topic/(\d+)/delete$#', [\RetroBB\Controllers\TopicController::class, 'delete']);
  $r->get('#^/topic/(\d+)/move$#', [\RetroBB\Controllers\TopicController::class, 'moveForm']);
  $r->post('#^/topic/(\d+)/move$#', [\RetroBB\Controllers\TopicController::class, 'moveSubmit']);
  $r->get('#^/topic/(\d+)/split$#', [\RetroBB\Controllers\TopicController::class, 'splitForm']);
  $r->post('#^/topic/(\d+)/split$#', [\RetroBB\Controllers\TopicController::class, 'splitSubmit']);
  $r->get('#^/topic/(\d+)/merge$#', [\RetroBB\Controllers\TopicController::class, 'mergeForm']);
  $r->post('#^/topic/(\d+)/merge$#', [\RetroBB\Controllers\TopicController::class, 'mergeSubmit']);
  $r->get('#^/post/(\d+)/edit$#', [\RetroBB\Controllers\TopicController::class, 'editPostForm']);
  $r->post('#^/post/(\d+)/edit$#', [\RetroBB\Controllers\TopicController::class, 'editPostSubmit']);
  $r->post('#^/post/(\d+)/delete$#', [\RetroBB\Controllers\TopicController::class, 'deletePost']);
  $r->get('#^/post/(\d+)/report$#', [\RetroBB\Controllers\ReportController::class, 'reportForm']);
  $r->post('#^/post/(\d+)/report$#', [\RetroBB\Controllers\ReportController::class, 'reportSubmit']);
  $r->post('#^/post/(\d+)/react$#', [\RetroBB\Controllers\ReactionController::class, 'toggle']);
  $r->get('#^/alerts$#', [\RetroBB\Controllers\AlertController::class, 'index']);
  $r->get('#^/pm$#', [\RetroBB\Controllers\PmController::class, 'inbox']);
  $r->get('#^/pm/sent$#', [\RetroBB\Controllers\PmController::class, 'sent']);
  $r->get('#^/pm/trash$#', [\RetroBB\Controllers\PmController::class, 'trash']);
  $r->get('#^/pm/drafts$#', [\RetroBB\Controllers\PmController::class, 'drafts']);
  $r->get('#^/pm/draft/new$#', [\RetroBB\Controllers\PmController::class, 'draftForm']);
  $r->post('#^/pm/draft/new$#', [\RetroBB\Controllers\PmController::class, 'draftSubmit']);
  $r->get('#^/pm/draft/(\d+)$#', [\RetroBB\Controllers\PmController::class, 'draftForm']);
  $r->post('#^/pm/draft/(\d+)$#', [\RetroBB\Controllers\PmController::class, 'draftSubmit']);
  $r->post('#^/pm/draft/(\d+)/discard$#', [\RetroBB\Controllers\PmController::class, 'draftDiscard']);
  $r->get('#^/pm/new$#', [\RetroBB\Controllers\PmController::class, 'newForm']);
  $r->post('#^/pm/new$#', [\RetroBB\Controllers\PmController::class, 'newSubmit']);
  $r->get('#^/pm/(\d+)$#', [\RetroBB\Controllers\PmController::class, 'show']);
  $r->post('#^/pm/(\d+)/delete$#', [\RetroBB\Controllers\PmController::class, 'delete']);
  $r->get('#^/mod$#', [\RetroBB\Controllers\ModController::class, 'index']);
  $r->get('#^/mod/reports$#', [\RetroBB\Controllers\ReportController::class, 'queue']);
  $r->get('#^/mod/report/(\d+)/resolve$#', [\RetroBB\Controllers\ReportController::class, 'resolveForm']);
  $r->get('#^/mod/report/(\d+)/warn$#', [\RetroBB\Controllers\ReportController::class, 'warnForm']);
  $r->post('#^/mod/report/(\d+)/handle$#', [\RetroBB\Controllers\ReportController::class, 'handle']);
  $r->post('#^/mod/report/(\d+)/delete-post$#', [\RetroBB\Controllers\ReportController::class, 'deletePost']);
  $r->post('#^/mod/report/(\d+)/warn-author$#', [\RetroBB\Controllers\ReportController::class, 'warnAuthor']);
  $r->get('#^/mod/bans$#', [\RetroBB\Controllers\ModController::class, 'bans']);
$r->get('#^/register$#', [\RetroBB\Controllers\AuthController::class, 'registerForm']);
$r->post('#^/register$#', [\RetroBB\Controllers\AuthController::class, 'registerSubmit']);
$r->get('#^/login$#', [\RetroBB\Controllers\AuthController::class, 'loginForm']);
$r->post('#^/login$#', [\RetroBB\Controllers\AuthController::class, 'loginSubmit']);
$r->get('#^/forgot-password$#', [\RetroBB\Controllers\AuthController::class, 'forgotForm']);
$r->post('#^/forgot-password$#', [\RetroBB\Controllers\AuthController::class, 'forgotSubmit']);
$r->get('#^/reset-password$#', [\RetroBB\Controllers\AuthController::class, 'resetForm']);
$r->post('#^/reset-password$#', [\RetroBB\Controllers\AuthController::class, 'resetSubmit']);
$r->post('#^/logout$#', [\RetroBB\Controllers\AuthController::class, 'logout']);
$r->get('#^/members$#', [\RetroBB\Controllers\ProfileController::class, 'index']);
$r->get('#^/settings/profile$#', [\RetroBB\Controllers\ProfileController::class, 'settingsForm']);
$r->post('#^/settings/profile$#', [\RetroBB\Controllers\ProfileController::class, 'settingsSubmit']);
$r->get('#^/members/([^/]+)$#', [\RetroBB\Controllers\ProfileController::class, 'show']);
$r->post('#^/members/(\d+)/warn$#', [\RetroBB\Controllers\ProfileController::class, 'warn']);
$r->post('#^/members/(\d+)/ban$#', [\RetroBB\Controllers\ProfileController::class, 'ban']);
$r->post('#^/members/unban/(\d+)$#', [\RetroBB\Controllers\ProfileController::class, 'unban']);
$r->get('#^/admin$#', [\RetroBB\Controllers\AdminController::class, 'index']);
$r->get('#^/admin/settings$#', [\RetroBB\Controllers\AdminController::class, 'settingsPage']);
$r->get('#^/admin/features$#', [\RetroBB\Controllers\AdminController::class, 'featuresPage']);
$r->post('#^/admin/features$#', [\RetroBB\Controllers\AdminController::class, 'saveFeatures']);
$r->get('#^/admin/import$#', [\RetroBB\Controllers\ImportController::class, 'form']);
$r->get('#^/admin/plugins$#', [\RetroBB\Controllers\AdminController::class, 'pluginsPage']);
$r->post('#^/admin/plugin/([A-Za-z0-9_-]+)$#', [\RetroBB\Controllers\AdminController::class, 'pluginToggle']);
$r->post('#^/admin/import/test$#', [\RetroBB\Controllers\ImportController::class, 'test']);
$r->post('#^/admin/import/run$#', [\RetroBB\Controllers\ImportController::class, 'run']);
$r->get('#^/admin/spam$#', [\RetroBB\Controllers\AdminController::class, 'spamPage']);
$r->get('#^/admin/structure$#', [\RetroBB\Controllers\AdminController::class, 'structurePage']);
$r->get('#^/admin/bans$#', [\RetroBB\Controllers\AdminController::class, 'bansPage']);
$r->get('#^/admin/users$#', [\RetroBB\Controllers\AdminController::class, 'usersPage']);
$r->get('#^/admin/modlog$#', [\RetroBB\Controllers\AdminController::class, 'modlogPage']);
$r->post('#^/admin/settings$#', [\RetroBB\Controllers\AdminController::class, 'saveSettings']);
$r->post('#^/admin/add-forum$#', [\RetroBB\Controllers\AdminController::class, 'addForum']);
$r->post('#^/admin/add-category$#', [\RetroBB\Controllers\AdminController::class, 'addCategory']);
$r->post('#^/admin/user/(\d+)/group$#', [\RetroBB\Controllers\AdminController::class, 'setGroup']);
$r->post('#^/admin/user/(\d+)/super$#', [\RetroBB\Controllers\AdminController::class, 'superToggle']);
$r->post('#^/admin/category/(\d+)/move/(up|down)$#', [\RetroBB\Controllers\AdminController::class, 'moveCategory']);
$r->get('#^/admin/category/(\d+)/edit$#', [\RetroBB\Controllers\AdminController::class, 'editCategoryForm']);
$r->post('#^/admin/category/(\d+)/rename$#', [\RetroBB\Controllers\AdminController::class, 'renameCategory']);
$r->post('#^/admin/category/(\d+)/delete$#', [\RetroBB\Controllers\AdminController::class, 'deleteCategory']);
$r->post('#^/admin/forum/(\d+)/move/(up|down)$#', [\RetroBB\Controllers\AdminController::class, 'moveForum']);
$r->get('#^/admin/forum/(\d+)/edit$#', [\RetroBB\Controllers\AdminController::class, 'editForumForm']);
$r->post('#^/admin/forum/(\d+)/rename$#', [\RetroBB\Controllers\AdminController::class, 'renameForum']);
$r->post('#^/admin/forum/(\d+)/delete$#', [\RetroBB\Controllers\AdminController::class, 'deleteForum']);
$r->post('#^/admin/unban/(\d+)$#', [\RetroBB\Controllers\AdminController::class, 'unban']);
$r->post('#^/admin/migrate$#', [\RetroBB\Controllers\AdminController::class, 'runMigrate']);
$r->post('#^/admin/repair-html$#', [\RetroBB\Controllers\AdminController::class, 'repairHtml']);
$r->get('#^/sitemap\.xml$#', [\RetroBB\Controllers\SitemapController::class, 'xml']);
$r->get('#^/sitemap\.xsl$#', [\RetroBB\Controllers\SitemapController::class, 'xsl']);
$r->get('#^/search$#', [\RetroBB\Controllers\SearchController::class, 'index']);
// skin switcher + legacy compat. POST + CSRF: a GET let any page embed this
// URL and pin a visitor's skin (preference fixation, issue #2). The token is
// the sessionless double-submit one — guests have no session to bind to.
$r->post('#^/skin/([a-z0-9]+)$#', function (string $s) {
    if (!\RetroBB\Core\Csrf::verifyAnon($_POST['anon_csrf'] ?? null)) {
        redirect('/');
    }
    if (feature('skin_selector') && in_array($s, ['classic', 'midnight', 'silver'], true)) {
        setcookie('retrobb_skin', $s, time() + 86400 * 365, base_path() ?: '/');
    }
    // Referer is client-controlled: only follow local paths, never "//host".
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $bp = parse_url($back, PHP_URL_PATH) ?: '/';
    if (!str_starts_with($bp, '/') || str_starts_with($bp, '//')) {
        $bp = '/';
    }
    // Tell the sync bridge (hub-shell) this landing carries a fresh explicit
    // choice: typed/bookmarked switcher URLs skip the click seeder, so the
    // bridge must trust the server here, not stale localStorage.
    redirect($bp . (str_contains($bp, '?') ? '&' : '?') . 'picked=skin');
});
// theme switcher (light / dark / auto-follows-OS). Same POST + CSRF rule.
$r->post('#^/theme/(light|dark|auto)$#', function (string $t) {
    if (!\RetroBB\Core\Csrf::verifyAnon($_POST['anon_csrf'] ?? null)) {
        redirect('/');
    }
    if (in_array($t, allowed_themes(), true)) {
        setcookie('retrobb_theme', $t, time() + 86400 * 365, base_path() ?: '/');
    }
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $bp = parse_url($back, PHP_URL_PATH) ?: '/';
    if (!str_starts_with($bp, '/') || str_starts_with($bp, '//')) {
        $bp = '/';
    }
    redirect($bp . (str_contains($bp, '?') ? '&' : '?') . 'picked=theme');
});
$r->get('#^/viewtopic\.php$#', function () {
    $t = (int) ($_GET['t'] ?? 0);
    if ($t > 0) {
        $topic = \RetroBB\Models\Topic::find($t);
        if ($topic) {
            redirect(\RetroBB\Core\Slug::topicUrl($topic), 301);
        }
    }
    redirect('/');
});

$r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', Router::currentPath());
