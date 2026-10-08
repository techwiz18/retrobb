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
require_once $root . '/core/BBCode.php';
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
require_once $root . '/controllers/HomeController.php';
require_once $root . '/controllers/ForumController.php';
require_once $root . '/controllers/TopicController.php';
require_once $root . '/controllers/ReportController.php';
require_once $root . '/controllers/AuthController.php';
require_once $root . '/controllers/ProfileController.php';
require_once $root . '/controllers/AdminController.php';
require_once $root . '/controllers/SitemapController.php';

use RetroBB\Core\Auth;
use RetroBB\Core\Plugins;
use RetroBB\Core\Router;

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
// runs first but only touches the DB when a session cookie is present, and
// Db::pdo() refuses to conjure a missing SQLite file.)
// NOTE: plain require, never require_once — config.php returns a value,
// and require_once would hand us `true` on second inclusion.
$config = require $root . '/config.php';
$needsInstall = false;
if (($config['db_driver'] ?? 'sqlite') === 'sqlite') {
    $needsInstall = !is_file($config['sqlite_path']);
    if (!$needsInstall) {
        // File without tables (interrupted install) is still "not installed".
        try {
            \RetroBB\Core\Db::pdo()->query('SELECT 1 FROM users LIMIT 1');
        } catch (Throwable) {
            $needsInstall = true;
        }
    }
} else {
    try {
        \RetroBB\Core\Db::pdo()->query('SELECT 1 FROM settings LIMIT 1');
    } catch (Throwable) {
        $needsInstall = true;
    }
}
if ($needsInstall && !str_starts_with($_SERVER['REQUEST_URI'] ?? '/', '/install')) {
    header('Location: /install.php');
    exit;
}

Plugins::load();
\RetroBB\Core\Hooks::do_action('boot');

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
  $r->get('#^/post/(\d+)/report$#', [\RetroBB\Controllers\ReportController::class, 'reportForm']);
  $r->post('#^/post/(\d+)/report$#', [\RetroBB\Controllers\ReportController::class, 'reportSubmit']);
  $r->get('#^/mod/reports$#', [\RetroBB\Controllers\ReportController::class, 'queue']);
  $r->post('#^/mod/report/(\d+)/handle$#', [\RetroBB\Controllers\ReportController::class, 'handle']);
  $r->post('#^/mod/report/(\d+)/delete-post$#', [\RetroBB\Controllers\ReportController::class, 'deletePost']);
  $r->post('#^/mod/report/(\d+)/warn-author$#', [\RetroBB\Controllers\ReportController::class, 'warnAuthor']);
$r->get('#^/register$#', [\RetroBB\Controllers\AuthController::class, 'registerForm']);
$r->post('#^/register$#', [\RetroBB\Controllers\AuthController::class, 'registerSubmit']);
$r->get('#^/login$#', [\RetroBB\Controllers\AuthController::class, 'loginForm']);
$r->post('#^/login$#', [\RetroBB\Controllers\AuthController::class, 'loginSubmit']);
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
$r->get('#^/admin/spam$#', [\RetroBB\Controllers\AdminController::class, 'spamPage']);
$r->get('#^/admin/structure$#', [\RetroBB\Controllers\AdminController::class, 'structurePage']);
$r->get('#^/admin/bans$#', [\RetroBB\Controllers\AdminController::class, 'bansPage']);
$r->get('#^/admin/users$#', [\RetroBB\Controllers\AdminController::class, 'usersPage']);
$r->get('#^/admin/modlog$#', [\RetroBB\Controllers\AdminController::class, 'modlogPage']);
$r->post('#^/admin/settings$#', [\RetroBB\Controllers\AdminController::class, 'saveSettings']);
$r->post('#^/admin/add-forum$#', [\RetroBB\Controllers\AdminController::class, 'addForum']);
$r->post('#^/admin/add-category$#', [\RetroBB\Controllers\AdminController::class, 'addCategory']);
$r->post('#^/admin/user/(\d+)/group$#', [\RetroBB\Controllers\AdminController::class, 'setGroup']);
$r->post('#^/admin/category/(\d+)/move/(up|down)$#', [\RetroBB\Controllers\AdminController::class, 'moveCategory']);
$r->post('#^/admin/forum/(\d+)/move/(up|down)$#', [\RetroBB\Controllers\AdminController::class, 'moveForum']);
$r->post('#^/admin/unban/(\d+)$#', [\RetroBB\Controllers\AdminController::class, 'unban']);
$r->get('#^/sitemap\.xml$#', [\RetroBB\Controllers\SitemapController::class, 'xml']);
$r->get('#^/sitemap\.xsl$#', [\RetroBB\Controllers\SitemapController::class, 'xsl']);
// skin switcher + legacy compat
$r->get('#^/skin/([a-z0-9]+)$#', function (string $s) {
    if (in_array($s, ['classic', 'midnight', 'silver'], true)) {
        setcookie('retrobb_skin', $s, time() + 86400 * 365, '/');
    }
    // Referer is client-controlled: only follow local paths, never "//host".
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $bp = parse_url($back, PHP_URL_PATH) ?: '/';
    if (!str_starts_with($bp, '/') || str_starts_with($bp, '//')) {
        $bp = '/';
    }
    redirect($bp);
});
// theme switcher (light / dark / auto-follows-OS)
$r->get('#^/theme/(light|dark|auto)$#', function (string $t) {
    setcookie('retrobb_theme', $t, time() + 86400 * 365, '/');
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $bp = parse_url($back, PHP_URL_PATH) ?: '/';
    if (!str_starts_with($bp, '/') || str_starts_with($bp, '//')) {
        $bp = '/';
    }
    redirect($bp);
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
