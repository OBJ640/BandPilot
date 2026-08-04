<?php

declare(strict_types=1);

use BandPilot\Controllers\AiResultController;
use BandPilot\Controllers\BandController;
use BandPilot\Controllers\PerformanceController;
use BandPilot\Controllers\RehearsalController;
use BandPilot\Controllers\SongController;
use BandPilot\Controllers\UserController;
use BandPilot\Support\Database;

$databasePath = tempnam(sys_get_temp_dir(), 'bandpilot-test-');
if ($databasePath === false) {
    fwrite(STDERR, "Could not create the test database.\n");
    exit(1);
}

putenv('DB_PATH=' . $databasePath);
$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';
$database = Database::connection($projectRoot);
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$seed = file_get_contents(dirname(__DIR__) . '/database/seed.sql');

try {
    if ($schema === false || $seed === false) {
        throw new RuntimeException('Test database files could not be read.');
    }
    $database->exec($schema);
    $database->exec($seed);

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $bandController = new BandController($projectRoot);
    $userController = new UserController($projectRoot);

    $initialBands = $bandController->index(1)['bands'] ?? [];
    $assert(count($initialBands) === 1, 'The seeded user should start with one band.');
    $assert(($initialBands[0]['name'] ?? null) === 'Neon Birds', 'The band list did not return the seeded band.');

    $createdBand = $bandController->create(1, [
        'name' => 'Controller Test Band',
        'description' => 'Created by the interaction test',
    ])['band'] ?? null;
    $assert(is_array($createdBand), 'The band controller did not return the created band.');
    $createdBandId = (int) ($createdBand['id'] ?? 0);
    $assert($createdBandId > 1, 'The created band did not receive an ID.');
    $assert((int) ($createdBand['owner_id'] ?? 0) === 1, 'The current user was not saved as band owner.');
    $assert(($createdBand['user_role'] ?? null) === 'owner', 'The created band did not return the owner role.');
    $assert((int) ($createdBand['member_count'] ?? 0) === 1, 'A new band should begin with one owner member.');

    $ownerStatement = $database->prepare(
        'SELECT user_id, display_name, role, instrument FROM band_members WHERE band_id = :band_id'
    );
    $ownerStatement->execute(['band_id' => $createdBandId]);
    $ownerMemberships = $ownerStatement->fetchAll();
    $assert(count($ownerMemberships) === 1, 'Band creation should create exactly one owner membership.');
    $assert((int) ($ownerMemberships[0]['user_id'] ?? 0) === 1, 'The owner membership belongs to the wrong user.');
    $assert(($ownerMemberships[0]['role'] ?? null) === 'owner', 'The created membership is not marked as owner.');

    $listedBands = $bandController->index(1)['bands'] ?? [];
    $listedBandIds = array_map(static fn (array $band): int => (int) $band['id'], $listedBands);
    $assert(in_array($createdBandId, $listedBandIds, true), 'The created band was missing from the user band list.');

    $updatedBand = $bandController->update($createdBandId, 1, [
        'name' => 'Updated Controller Band',
        'description' => 'Updated by the interaction test',
    ])['band'] ?? null;
    $assert(($updatedBand['name'] ?? null) === 'Updated Controller Band', 'The band name was not updated.');
    $assert(
        ($updatedBand['description'] ?? null) === 'Updated by the interaction test',
        'The band description was not updated.'
    );

    $updatedUser = $userController->update(1, $createdBandId, [
        'name' => 'Ricky Test',
        'email' => 'RICKY.UPDATED@EXAMPLE.COM',
        'instrument' => 'Keyboards',
    ])['user'] ?? null;
    $assert(is_array($updatedUser), 'The user controller did not return the updated profile.');
    $assert(($updatedUser['name'] ?? null) === 'Ricky Test', 'The user name was not updated.');
    $assert(
        ($updatedUser['email'] ?? null) === 'ricky.updated@example.com',
        'The updated email was not normalized and saved.'
    );
    $assert(($updatedUser['instrument'] ?? null) === 'Keyboards', 'The current-band instrument was not updated.');
    $assert(!array_key_exists('password_hash', $updatedUser), 'The profile response exposed the password hash.');

    $savedUser = $database->query('SELECT name, email FROM users WHERE id = 1')->fetch();
    $assert(($savedUser['name'] ?? null) === 'Ricky Test', 'The updated user name was not stored in the database.');
    $assert(
        ($savedUser['email'] ?? null) === 'ricky.updated@example.com',
        'The updated user email was not stored in the database.'
    );

    $memberStatement = $database->prepare(
        'SELECT band_id, display_name, instrument FROM band_members WHERE user_id = :user_id ORDER BY band_id'
    );
    $memberStatement->execute(['user_id' => 1]);
    $memberships = $memberStatement->fetchAll();
    $membershipByBand = [];
    foreach ($memberships as $membership) {
        $membershipByBand[(int) $membership['band_id']] = $membership;
    }
    $assert(
        ($membershipByBand[$createdBandId]['display_name'] ?? null) === 'Ricky Test',
        'The current-band display name was not updated.'
    );
    $assert(
        ($membershipByBand[$createdBandId]['instrument'] ?? null) === 'Keyboards',
        'The selected band membership did not receive the new instrument.'
    );
    $assert(
        ($membershipByBand[1]['instrument'] ?? null) === 'Guitar',
        'Updating an instrument changed another band membership.'
    );

    $songController = new SongController($projectRoot);
    $createdSong = $songController->create(1, [
        'title' => 'Five-level test song',
        'artist' => 'BandPilot',
        'progress_level' => 'polishing',
        'status' => 'practising',
        'problem_notes' => 'Check the five-level progress flow',
    ])['song'] ?? null;
    $assert(($createdSong['progress_level'] ?? null) === 'polishing', 'The five-level song progress was not saved.');
    $assert(!array_key_exists('progress', $createdSong ?? []), 'The song API still exposed numeric progress.');
    try {
        $songController->create(1, [
            'title' => 'Invalid progress song',
            'progress_level' => '67-percent',
            'status' => 'practising',
        ]);
        throw new RuntimeException('An invalid song progress level was accepted.');
    } catch (InvalidArgumentException) {
        // Expected: progress is limited to the five named levels.
    }

    $rehearsal = (new RehearsalController($projectRoot))->create(1, 1, [
        'title' => 'Controller test rehearsal',
        'start_time' => '2026-08-09T14:00',
        'duration_minutes' => 90,
        'location' => 'Test room',
        'goals' => 'Check the save flow',
        'song_ids' => [1, 2],
    ]);
    $performance = (new PerformanceController($projectRoot))->create(1, [
        'name' => 'Controller test show',
        'start_time' => '2026-08-24T19:00',
        'length_minutes' => 30,
        'location' => 'Test hall',
        'notes' => 'Check the save flow',
    ]);
    $aiResult = (new AiResultController($projectRoot))->approve(1, [
        'result_type' => 'test_plan',
        'content' => ['title' => 'Test plan', 'items' => [['title' => 'Warm up']]],
    ]);

    if (!isset($rehearsal['rehearsal']['id'], $performance['performance']['id'], $aiResult['result']['id'])) {
        throw new RuntimeException('One or more interaction controllers did not save data.');
    }

    fwrite(STDOUT, "BandPilot interaction test passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    unset($database);
    if (is_file($databasePath)) {
        unlink($databasePath);
    }
}
