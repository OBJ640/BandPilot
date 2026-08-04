<?php

declare(strict_types=1);

use BandPilot\Controllers\RehearsalReviewController;
use BandPilot\Support\Database;

$databasePath = tempnam(sys_get_temp_dir(), 'bandpilot-rehearsal-review-test-');
if ($databasePath === false) {
    fwrite(STDERR, "Could not create the rehearsal review test database.\n");
    exit(1);
}

putenv('DB_PATH=' . $databasePath);
$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';
$database = Database::connection($projectRoot);
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$seed = file_get_contents(dirname(__DIR__) . '/database/seed.sql');

try {
    if ($schema === false || $seed === false) {
        throw new RuntimeException('Rehearsal review test database files could not be read.');
    }
    $database->exec($schema);
    $database->exec($seed);

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $assertInvalid = static function (callable $callback, string $message): void {
        try {
            $callback();
        } catch (InvalidArgumentException) {
            return;
        }
        throw new RuntimeException($message);
    };

    $snapshot = static function () use ($database): array {
        return [
            'review' => $database->query(
                'SELECT rehearsal_id, overall_rating, goals_met, notes, updated_by, completed_at
                 FROM rehearsal_reviews WHERE rehearsal_id = 2'
            )->fetch() ?: null,
            'song_reviews' => $database->query(
                'SELECT rehearsal_id, song_id, performance_rating, progress_level_after, status_after, problem_type, note
                 FROM rehearsal_song_reviews WHERE rehearsal_id = 2 ORDER BY song_id'
            )->fetchAll(),
            'songs' => $database->query(
                'SELECT id, progress_level, status FROM songs WHERE id IN (1, 2, 3) ORDER BY id'
            )->fetchAll(),
            'rehearsal_status' => $database->query(
                'SELECT status FROM rehearsals WHERE id = 2'
            )->fetchColumn(),
        ];
    };

    $controller = new RehearsalReviewController($projectRoot);

    $initial = $controller->show(2, 1);
    $assert(array_key_exists('review', $initial), 'The initial response should include the review field.');
    $assert($initial['review'] === null, 'A rehearsal without a review should return null review data.');
    $assert(($initial['completed'] ?? null) === false, 'A rehearsal without a review was marked reviewed.');
    $assert(($initial['can_edit'] ?? null) === true, 'The band owner should be able to edit the review.');
    $assert(count($initial['songs'] ?? []) === 6, 'The review did not return the band song list.');
    $plannedSongs = array_values(array_filter(
        $initial['songs'],
        static fn (array $song): bool => (int) ($song['planned'] ?? 0) === 1
    ));
    $assert(count($plannedSongs) === 3, 'The review did not mark the rehearsal songs as planned.');
    foreach ($initial['songs'] as $song) {
        $assert(
            ($song['performance_rating'] ?? null) === null,
            'An unanswered song unexpectedly contained review data.'
        );
    }

    $firstInput = [
        'overall_rating' => 4,
        'goals_met' => 'partly',
        'notes' => 'Good rehearsal with two songs reviewed.',
        'songs' => [
            [
                'song_id' => 1,
                'performance_rating' => 3,
                'progress_level_after' => 'rehearsing',
                'status_after' => 'practising',
                'problem_type' => 'coordination',
                'note' => 'The chorus entry still needs work.',
            ],
            [
                'song_id' => 2,
                'performance_rating' => 5,
                'progress_level_after' => 'ready',
                'status_after' => 'ready',
                'problem_type' => 'none',
                'note' => 'Full run was steady.',
            ],
        ],
    ];

    $saved = $controller->save(2, 1, $firstInput);
    $assert(($saved['completed'] ?? null) === true, 'A saved rehearsal review was not marked complete.');
    $assert((int) (($saved['review'] ?? [])['overall_rating'] ?? 0) === 4, 'The overall rating was not saved.');
    $assert((($saved['review'] ?? [])['goals_met'] ?? null) === 'partly', 'The goals result was not saved.');
    $assert((($saved['review'] ?? [])['notes'] ?? null) === $firstInput['notes'], 'The overall notes were not saved.');

    $reviewRows = $database->query(
        'SELECT song_id, performance_rating, progress_level_after, status_after, problem_type, note
         FROM rehearsal_song_reviews WHERE rehearsal_id = 2 ORDER BY song_id'
    )->fetchAll();
    $assert(count($reviewRows) === 2, 'The first save did not create one answer for each selected song.');
    $assert((int) $reviewRows[0]['song_id'] === 1, 'The first song answer belongs to the wrong song.');
    $assert($reviewRows[0]['progress_level_after'] === 'rehearsing', 'The first song progress level was not saved.');
    $assert($reviewRows[1]['status_after'] === 'ready', 'The second song status answer was not saved.');

    $songOne = $database->query('SELECT progress_level, status FROM songs WHERE id = 1')->fetch();
    $songTwo = $database->query('SELECT progress_level, status FROM songs WHERE id = 2')->fetch();
    $assert(($songOne['progress_level'] ?? null) === 'rehearsing', 'Saving the review did not sync the first song progress.');
    $assert(($songOne['status'] ?? null) === 'practising', 'Saving the review did not sync the first song status.');
    $assert(($songTwo['progress_level'] ?? null) === 'ready', 'Saving the review did not sync the second song progress.');
    $assert(($songTwo['status'] ?? null) === 'ready', 'Saving the review did not sync the second song status.');
    $assert(
        $database->query('SELECT status FROM rehearsals WHERE id = 2')->fetchColumn() === 'completed',
        'Saving the review did not mark the rehearsal completed.'
    );

    $completedAt = (string) $database->query(
        'SELECT completed_at FROM rehearsal_reviews WHERE rehearsal_id = 2'
    )->fetchColumn();
    $editedInput = [
        'overall_rating' => 5,
        'goals_met' => 'yes',
        'notes' => 'The second review replaces the first answers.',
        'songs' => [
            [
                'song_id' => 1,
                'performance_rating' => 4,
                'progress_level_after' => 'polishing',
                'status_after' => 'practising',
                'problem_type' => 'rhythm',
                'note' => 'Count-ins are more consistent.',
            ],
            [
                'song_id' => 3,
                'performance_rating' => 4,
                'progress_level_after' => 'polishing',
                'status_after' => 'practising',
                'problem_type' => 'tone',
                'note' => 'Balance improved after the sound check.',
            ],
        ],
    ];

    $edited = $controller->save(2, 1, $editedInput);
    $assert((int) (($edited['review'] ?? [])['overall_rating'] ?? 0) === 5, 'The edited overall rating was not saved.');
    $assert((($edited['review'] ?? [])['goals_met'] ?? null) === 'yes', 'The edited goals result was not saved.');
    $reviewCount = (int) $database->query(
        'SELECT COUNT(*) FROM rehearsal_reviews WHERE rehearsal_id = 2'
    )->fetchColumn();
    $songReviewCount = (int) $database->query(
        'SELECT COUNT(*) FROM rehearsal_song_reviews WHERE rehearsal_id = 2'
    )->fetchColumn();
    $assert($reviewCount === 1, 'Editing created a duplicate rehearsal review.');
    $assert($songReviewCount === 2, 'Editing created duplicate or stale song answers.');
    $editedSongIds = array_map(
        'intval',
        $database->query(
            'SELECT song_id FROM rehearsal_song_reviews WHERE rehearsal_id = 2 ORDER BY song_id'
        )->fetchAll(PDO::FETCH_COLUMN)
    );
    $assert($editedSongIds === [1, 3], 'Editing did not replace the previous per-song answer set.');
    $assert(
        (string) $database->query('SELECT completed_at FROM rehearsal_reviews WHERE rehearsal_id = 2')->fetchColumn()
            === $completedAt,
        'Editing changed the original review completion time.'
    );
    $assert($database->query('SELECT progress_level FROM songs WHERE id = 1')->fetchColumn() === 'polishing', 'Editing did not resync song 1.');
    $assert($database->query('SELECT progress_level FROM songs WHERE id = 3')->fetchColumn() === 'polishing', 'Editing did not sync song 3.');

    $stableState = $snapshot();

    $invalidInput = $editedInput;
    $invalidInput['overall_rating'] = 2;
    $invalidInput['songs'][0]['progress_level_after'] = 'almost-there';
    $assertInvalid(
        static fn () => $controller->save(2, 1, $invalidInput),
        'A song answer with an invalid progress level was accepted.'
    );
    $assert($snapshot() === $stableState, 'Invalid song data partially changed the saved review or songs.');

    $database->exec(
        "INSERT INTO bands (name, description, owner_id) VALUES ('Other Band', 'Cross-band test', 1)"
    );
    $otherBandId = (int) $database->lastInsertId();
    $otherSongStatement = $database->prepare(
        "INSERT INTO songs (band_id, title, artist, progress, progress_level, status)
         VALUES (:band_id, 'Other Band Song', 'Test Artist', 25, 'learning', 'learning')"
    );
    $otherSongStatement->execute(['band_id' => $otherBandId]);
    $otherSongId = (int) $database->lastInsertId();

    $crossBandInput = $editedInput;
    $crossBandInput['overall_rating'] = 1;
    $crossBandInput['notes'] = 'This request must roll back completely.';
    $crossBandInput['songs'][] = [
        'song_id' => $otherSongId,
        'performance_rating' => 5,
        'progress_level_after' => 'ready',
        'status_after' => 'ready',
        'problem_type' => 'none',
        'note' => 'This song belongs to another band.',
    ];
    $assertInvalid(
        static fn () => $controller->save(2, 1, $crossBandInput),
        'A song from another band was accepted by the rehearsal review.'
    );
    $assert($snapshot() === $stableState, 'A cross-band song request partially changed the review or songs.');
    $assert(
        $database->query("SELECT progress_level FROM songs WHERE id = {$otherSongId}")->fetchColumn() === 'learning',
        'The cross-band song was changed by a rejected review.'
    );

    $userStatement = $database->prepare(
        'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
    );
    $userStatement->execute([
        'name' => 'Review Member',
        'email' => 'review.member@example.com',
        'password_hash' => password_hash('ReviewMember123!', PASSWORD_DEFAULT),
    ]);
    $memberUserId = (int) $database->lastInsertId();
    $membershipStatement = $database->prepare(
        "INSERT INTO band_members (band_id, user_id, display_name, role, instrument)
         VALUES (1, :user_id, 'Review Member', 'member', 'Drums')"
    );
    $membershipStatement->execute(['user_id' => $memberUserId]);

    $memberView = $controller->show(2, $memberUserId);
    $assert(($memberView['completed'] ?? null) === true, 'A band member could not read the saved rehearsal review.');
    $assert(($memberView['can_edit'] ?? null) === false, 'A normal member was incorrectly given review edit permission.');
    $assert((int) (($memberView['review'] ?? [])['overall_rating'] ?? 0) === 5, 'The member saw the wrong review data.');
    $assertInvalid(
        static fn () => $controller->save(2, $memberUserId, $firstInput),
        'A normal member was allowed to save the rehearsal review.'
    );
    $assert($snapshot() === $stableState, 'A member save attempt changed the review or song data.');

    fwrite(STDOUT, "BandPilot rehearsal review test passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    unset($database);
    if (is_file($databasePath)) {
        unlink($databasePath);
    }
}
