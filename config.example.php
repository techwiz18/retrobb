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
];
