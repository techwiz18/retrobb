<?php
// Shared-hosting fallback: when the package root is the document root and
// mod_rewrite is unavailable, this boots the forum from public/.
// With mod_rewrite on, the root .htaccess routes here anyway.
declare(strict_types=1);

require __DIR__ . '/public/index.php';
