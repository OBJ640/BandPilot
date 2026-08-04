<?php

declare(strict_types=1);

namespace BandPilot\Support;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(string $projectRoot): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configuredPath = Env::get('DB_PATH', 'backend/storage/bandpilot.sqlite');
        $databasePath = str_starts_with($configuredPath, '/')
            ? $configuredPath
            : $projectRoot . '/' . $configuredPath;

        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        self::$connection = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$connection->exec('PRAGMA foreign_keys = ON');

        return self::$connection;
    }
}
