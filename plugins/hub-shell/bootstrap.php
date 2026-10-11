<?php
declare(strict_types=1);
// Hub shell: shared masthead + footer with the techwiz.dad hub so the
// forum feels like the same site. Markup mirrors the hub's Base.astro
// layout (header.site-header / footer.site-footer); refine against the
// hub side if that layout changes.
//
// Strategy: PREPEND the hub masthead above the forum topbar (forum
// login/userbox, mod queue, and skin switcher stay fully functional
// below it) and REPLACE the stock footer with the hub footer.
// Notes:
// - The hub's skin/theme switcher buttons are intentionally NOT mirrored:
//   the hub stores them in localStorage while the forum uses cookies +
//   server-rendered classes; mirroring the buttons would look functional
//   but change nothing. The forum's own switcher stays in its nav row.
// - The masthead spans the forum content column (it renders inside
//   .page-wrap); full-bleed would need layout surgery — v2 if wanted.
use RetroBB\Core\Hooks;

Hooks::add_action('head', function () {
    echo '<style>'
        . '.site-header{background:#1c2b4a;color:#f5e9b8}'
        . '.site-header .head-main{display:flex;align-items:center;gap:14px;padding:10px 16px;flex-wrap:wrap}'
        . '.site-header .brand{display:flex;align-items:center;gap:8px;color:#f5e9b8;text-decoration:none;font-weight:bold;font-size:16px}'
        . '.site-header .brand .floppy{display:inline-block;width:15px;height:15px;background:#7ea4d6;border:2px solid #dfe9f7;border-top-width:4px;border-radius:2px}'
        . '.site-header .mainnav{display:flex;gap:14px;margin-left:auto;align-items:center}'
        . '.site-header .mainnav a{color:#f5e9b8;font-size:13px;text-decoration:none}'
        . '.site-header .mainnav a:hover{text-decoration:underline}'
        . '.site-footer{font-size:12px;color:#667;padding:22px 16px 26px;text-align:center}'
        . '.site-footer a{color:#1a3f7a}'
        . '@media(max-width:640px){.site-header .mainnav{margin-left:0}}'
        . '</style>';
});

Hooks::add_action('header', function () {
    echo '<header class="site-header"><div class="head-main">'
        . '<a class="brand" href="https://techwiz.dad"><span class="floppy" aria-hidden="true"></span> techwiz.dad</a>'
        . '<nav class="mainnav" aria-label="Main">'
        . '<a href="https://techwiz.dad/projects/">Projects</a>'
        . '<a href="https://github.com/techwiz18" target="_blank" rel="noopener">GitHub</a>'
        . '<a href="https://techwiz.dad/forum/">Forum</a>'
        . '</nav></div></header>';
});

Hooks::add_filter('show_default_footer', static fn() => false);

Hooks::add_action('footer', function () {
    echo '<div>Powered by curiosity · <strong>techwiz.dad</strong> · Old-school soul, modern code · ' . date('Y') . '</div>';
    echo '<div><a href="https://techwiz.dad">Hub</a> · <a href="https://techwiz.dad/forum/">Forum</a> · Powered by RetroBB</div>';
});
