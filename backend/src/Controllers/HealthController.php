<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use Throwable;

final class HealthController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function show(): array
    {
        $databaseReady = false;
        try {
            $database = Database::connection($this->projectRoot);
            $databaseReady = (bool) $database->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'bands'"
            )->fetchColumn();
        } catch (Throwable) {
            $databaseReady = false;
        }

        return [
            'status' => 'ok',
            'app' => 'BandPilot',
            'version' => '0.1.0',
            'database_ready' => $databaseReady,
            'time' => date(DATE_ATOM),
        ];
    }
}
