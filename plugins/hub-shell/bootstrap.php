<?php
declare(strict_types=1);
// Hub shell: shared masthead + footer with the techwiz.dad hub so the
// forum feels like the same site. STARTER markup — refine links, labels,
// and styling against the hub side; structure (slots + filters) is stable.
//
// Strategy: PREPEND the hub masthead above the forum topbar (forum
// login/userbox, mod queue, and skin switcher stay fully functional
// below it) and REPLACE the stock footer with the hub footer.
// Full header replacement is possible via the 'show_default_header'
// filter, but then this plugin must re-render the auth-dependent userbox
// itself — not worth it for v1.
use RetroBB\Core\Hooks;

Hooks::add_action('head', function () {
    echo '<style>'
        . '.hubbar{display:flex;align-items:center;gap:14px;padding:8px 12px;background:#232c40;color:#fff;font-size:12px}'
        . '.hubbar a{color:#ffe9a8;text-decoration:none}'
        . '.hubbar a:hover{text-decoration:underline}'
        . '.hubbar .hub-brand{font-weight:bold;font-size:13px}'
        . '.hubbar .hub-sep{color:#5a6a8c}'
        . '</style>';
});

Hooks::add_action('header', function () {
    echo '<div class="hubbar">'
        . '<a class="hub-brand" href="https://techwiz.dad">techwiz.dad</a>'
        . '<span class="hub-sep">|</span>'
        . '<a href="https://techwiz.dad">Home</a>'
        . '<a href="https://techwiz.dad/forum/">Forum</a>'
        . '</div>';
});

Hooks::add_filter('show_default_footer', static fn() => false);

Hooks::add_action('footer', function () {
    echo '<div>Powered by <b>RetroBB</b> by <a href="https://techwiz.dad">techwiz.dad</a> · Old-school soul, modern code · ' . date('Y') . '</div>';
    echo '<div style="margin-top:4px"><a href="https://techwiz.dad">techwiz.dad</a> · <a href="https://techwiz.dad/forum/">Forum</a></div>';
});
