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
