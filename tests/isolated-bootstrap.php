<?php

// Run with --bootstrap tests/isolated-bootstrap.php. Set all three environment
// sources before Laravel/Dotenv starts, including an explicitly empty DB_URL.
// A unique missing config-cache path prevents loading the application's cache.
$auditEnvironment = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'wintools-test-'.bin2hex(random_bytes(12)).'.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
];

foreach ($auditEnvironment as $auditKey => $auditValue) {
    putenv($auditKey.'='.$auditValue);
    $_ENV[$auditKey] = $auditValue;
    $_SERVER[$auditKey] = $auditValue;
}

require dirname(__DIR__).'/vendor/autoload.php';
