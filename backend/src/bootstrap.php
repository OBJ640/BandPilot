<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

spl_autoload_register(static function (string $class): void {
    $prefix = 'BandPilot\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

BandPilot\Support\Env::load($root . '/.env');

date_default_timezone_set(BandPilot\Support\Env::get('APP_TIMEZONE', 'Asia/Shanghai'));

return $root;
