<?php
declare(strict_types=1);
// Example plugin — shows off add_action / add_filter / template hooks.

use RetroBB\Core\Hooks;

Hooks::add_action('footer', function () {
    echo '<div style="margin-top:6px;font-size:11px">Hello from the <b>hello-world</b> plugin — hook/filter API works.</div>';
});

Hooks::add_filter('post_body_html', function ($html) {
    // Gently highlight "RetroBB" mentions in posts.
    return str_replace('RetroBB', '<b style="color:#b00">RetroBB</b>', (string) $html);
});

// ---- Plugin API cookbook (issues #17-#20). Uncomment to try; all no-ops
// when unregistered, so core output is unchanged without a plugin. ----

// #19 routes: serve pages inside core routing (canonical-host 301s, method
// matching, and the shared 404 come free; core routes win collisions).
// Hooks::add_action('routes', function ($r) {
//     $r->get('#^/hello$#', function () { echo 'hi'; });
// });

// #17 display hooks: render inside the board chrome. The partial receives
// $post, $profile, or $user depending on the location.
// Hooks::add_template_hook('nav_links', __DIR__ . '/views/nav.php');
// Hooks::add_template_hook('userbox', __DIR__ . '/views/chip.php');
// Hooks::add_template_hook('profile_after', __DIR__ . '/views/card.php');

// #18 postbit: author-block content server-side (no JS injection), plus a
// username-HTML filter that now fires on EVERY username site-wide (helper
// username_html() in core/helpers.php — keep escaping with e(); the user
// row is 2nd arg).
// Hooks::add_template_hook('postbit_user', __DIR__ . '/views/flair.php');
// Hooks::add_filter('post_username_html', function ($html, $user) { return $html; });

// #20 lifecycle: seed on enable, clean up on disable (replaces
// every-request CREATE TABLE). Tables live in migrations/*.sql and apply
// once via bin/migrate.php; reads via plugin_setting('hello-world', $key).
// Hooks::add_action('plugin_enabled', function ($name) {});
// Hooks::add_action('plugin_disabled', function ($name) {});
// #22 uninstall: super-confirmed button on AdminCP → Plugins fires this;
// drop your tables + clear settings here (core then forgets the row,
// prefix settings, and deletes the plugin directory).
// Hooks::add_action('plugin_uninstalled', function ($name) {});

// #17 admin spots: link into the AdminCP nav + summary widgets on the
// dashboard (partials emit their own <a> links / .dash-card blocks).
// Hooks::add_template_hook('admin_nav', __DIR__ . '/views/adminlink.php');
// Hooks::add_template_hook('admin_dashboard', __DIR__ . '/views/admincard.php');
