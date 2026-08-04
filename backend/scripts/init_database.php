<?php

declare(strict_types=1);

use BandPilot\Support\Database;

$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';
$database = Database::connection($projectRoot);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$seed = file_get_contents(dirname(__DIR__) . '/database/seed.sql');

if ($schema === false || $seed === false) {
    fwrite(STDERR, "Database files could not be read.\n");
    exit(1);
}

$database->beginTransaction();
try {
    $database->exec($schema);

    $songColumns = $database->query('PRAGMA table_info(songs)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('progress_level', $songColumns, true)) {
        $database->exec(
            "ALTER TABLE songs ADD COLUMN progress_level TEXT NOT NULL DEFAULT 'starting'
             CHECK (progress_level IN ('starting', 'learning', 'rehearsing', 'polishing', 'ready'))"
        );
        $database->exec(
            "UPDATE songs SET progress_level = CASE
                WHEN progress >= 81 THEN 'ready'
                WHEN progress >= 61 THEN 'polishing'
                WHEN progress >= 41 THEN 'rehearsing'
                WHEN progress >= 21 THEN 'learning'
                ELSE 'starting' END"
        );
    }

    $reviewColumns = $database->query('PRAGMA table_info(rehearsal_song_reviews)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('progress_level_after', $reviewColumns, true)) {
        $database->exec(
            "ALTER TABLE rehearsal_song_reviews ADD COLUMN progress_level_after TEXT NOT NULL DEFAULT 'starting'
             CHECK (progress_level_after IN ('starting', 'learning', 'rehearsing', 'polishing', 'ready'))"
        );
        $database->exec(
            "UPDATE rehearsal_song_reviews SET progress_level_after = CASE
                WHEN progress_after >= 81 THEN 'ready'
                WHEN progress_after >= 61 THEN 'polishing'
                WHEN progress_after >= 41 THEN 'rehearsing'
                WHEN progress_after >= 21 THEN 'learning'
                ELSE 'starting' END"
        );
    }

    $database->exec($seed);
    $database->commit();
    fwrite(STDOUT, "BandPilot database is ready.\n");
} catch (Throwable $exception) {
    $database->rollBack();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
