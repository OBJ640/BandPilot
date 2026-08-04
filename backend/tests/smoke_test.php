<?php

declare(strict_types=1);

use BandPilot\Controllers\HealthController;

$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';
$result = (new HealthController($projectRoot))->show();

if (($result['status'] ?? null) !== 'ok') {
    fwrite(STDERR, "Health check failed.\n");
    exit(1);
}

fwrite(STDOUT, "BandPilot backend smoke test passed.\n");
