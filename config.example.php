<?php
declare(strict_types=1);

// Copy to config.php and fill in your database details (or set the env vars).
// MySQL 8+ or MariaDB 10.6+ required.
return [
    'mysql_host' => getenv('RETROBB_MYSQL_HOST') ?: '127.0.0.1',
    'mysql_port' => getenv('RETROBB_MYSQL_PORT') ?: 3306,
    'mysql_db' => getenv('RETROBB_MYSQL_DB') ?: 'retrobb',
    'mysql_user' => getenv('RETROBB_MYSQL_USER') ?: 'retrobb',
    'mysql_pass' => getenv('RETROBB_MYSQL_PASS') ?: '',
    // Subpath deployment (reverse proxy): '/forum' serves the board under
    // that prefix while the bare paths keep working. Env wins when set.
    // Leave '' for a normal standalone install.
    'base_path' => getenv('RETROBB_BASE_PATH') ?: '',
    // Canonical-host enforcement (e.g. subdomain → hub-subpath
    // consolidation): 301s foreign-Host requests to board_url. Off by
    // default. Requests carrying the trusted proxy header are exempt
    // (presence = "already routed by the proxy" — yours must send it).
    'force_board_host' => (bool) (getenv('RETROBB_FORCE_BOARD_HOST') ?: false),
    'trusted_proxy_header' => getenv('RETROBB_PROXY_HEADER') ?: '',
];
