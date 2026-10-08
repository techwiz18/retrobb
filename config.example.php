<?php
declare(strict_types=1);

return [
    'db_driver' => getenv('RETROBB_DB') ?: 'sqlite',
    // sqlite file (relative to project root if not absolute)
    'sqlite_path' => __DIR__ . '/storage/retrobb.sqlite',
    'mysql_host' => getenv('RETROBB_MYSQL_HOST') ?: '127.0.0.1',
    'mysql_port' => getenv('RETROBB_MYSQL_PORT') ?: 3306,
    'mysql_db' => getenv('RETROBB_MYSQL_DB') ?: 'retrobb',
    'mysql_user' => getenv('RETROBB_MYSQL_USER') ?: 'retrobb',
    'mysql_pass' => getenv('RETROBB_MYSQL_PASS') ?: 'retrobb',
];
