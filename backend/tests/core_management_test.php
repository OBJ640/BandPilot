<?php

declare(strict_types=1);

use BandPilot\Controllers\AvailabilityController;
use BandPilot\Controllers\MemberController;
use BandPilot\Controllers\RehearsalController;
use BandPilot\Controllers\RehearsalReviewController;
use BandPilot\Controllers\SongController;
use BandPilot\Support\Database;

$databasePath = tempnam(sys_get_temp_dir(), 'bandpilot-core-management-');
if ($databasePath === false) {
    fwrite(STDERR, "Could not create the core management test database.\n");
    exit(1);
}

putenv('DB_PATH=' . $databasePath);
$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';
$database = Database::connection($projectRoot);

try {
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    $seed = file_get_contents(dirname(__DIR__) . '/database/seed.sql');
    if ($schema === false || $seed === false) throw new RuntimeException('Test database files could not be read.');
    $database->exec($schema);
    $database->exec($seed);
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $invalid = static function (callable $callback, string $message): void {
        try { $callback(); } catch (InvalidArgumentException) { return; }
        throw new RuntimeException($message);
    };

    $members = new MemberController($projectRoot);
    $initialMembers = $members->index(1, 1);
    $assert(count($initialMembers['members']) === 4, 'Seed members were not listed.');
    $assert(count($initialMembers['role_options']) === 34, 'The full band-role option list is incomplete.');
    foreach (['Lead vocals', 'Drums', 'Keyboards', 'Violin', 'Saxophone', 'Trumpet', 'Producer', 'Band manager', 'Other'] as $role) {
        $assert(in_array($role, $initialMembers['role_options'], true), "Missing band role: {$role}");
    }
    $createdMembers = $members->create(1, 1, ['display_name' => 'Taylor', 'band_role' => 'Saxophone']);
    $taylor = array_values(array_filter($createdMembers['members'], static fn (array $member): bool => $member['display_name'] === 'Taylor'))[0] ?? null;
    $assert(($taylor['band_role'] ?? null) === 'Saxophone', 'Member role was not saved.');
    $taylorId = (int) $taylor['id'];
    $updatedMembers = $members->update(1, $taylorId, 1, ['display_name' => 'Taylor J', 'band_role' => 'Producer']);
    $updatedTaylor = array_values(array_filter($updatedMembers['members'], static fn (array $member): bool => (int) $member['id'] === $taylorId))[0] ?? null;
    $assert(($updatedTaylor['band_role'] ?? null) === 'Producer', 'Member edit did not save the selected role.');
    $invalid(static fn () => $members->create(1, 1, ['display_name' => 'Bad role', 'band_role' => 'Anything']), 'Invalid role was accepted.');
    $invalid(static fn () => $members->remove(1, 1, 1), 'The band owner was removed.');
    $members->remove(1, $taylorId, 1);
    $assert((int) $database->query("SELECT COUNT(*) FROM band_members WHERE id = {$taylorId}")->fetchColumn() === 0, 'Member was not removed.');

    $songs = new SongController($projectRoot);
    $createdSong = $songs->create(1, [
        'title' => 'Management Test Song', 'artist' => 'BandPilot',
        'progress_level' => 'learning', 'status' => 'learning', 'problem_notes' => 'First note',
    ])['song'];
    $songId = (int) $createdSong['id'];
    $updatedSong = $songs->update(1, $songId, 1, [
        'title' => 'Updated Management Song', 'artist' => 'BandPilot',
        'progress_level' => 'polishing', 'status' => 'practising', 'problem_notes' => 'Updated note',
    ])['song'];
    $assert($updatedSong['progress_level'] === 'polishing' && $updatedSong['problem_notes'] === 'Updated note', 'Song edit was not saved.');
    $songs->archive(1, $songId, 1);
    $assert((string) $database->query("SELECT archived_at FROM songs WHERE id = {$songId}")->fetchColumn() !== '', 'Song was not archived.');

    $rehearsals = new RehearsalController($projectRoot);
    $createdRehearsal = $rehearsals->create(1, 1, [
        'title' => 'Selected-song rehearsal', 'start_time' => '2026-08-12T18:00',
        'duration_minutes' => 90, 'location' => 'Room A', 'goals' => 'Practise selected songs',
        'song_ids' => [1, 2],
    ])['rehearsal'];
    $rehearsalId = (int) $createdRehearsal['id'];
    $linked = array_map('intval', $database->query("SELECT song_id FROM rehearsal_songs WHERE rehearsal_id = {$rehearsalId} ORDER BY order_number")->fetchAll(PDO::FETCH_COLUMN));
    $assert($linked === [1, 2], 'Rehearsal song selection was not saved.');
    $rehearsals->update(1, $rehearsalId, 1, [
        'title' => 'Edited selected-song rehearsal', 'start_time' => '2026-08-12T19:00',
        'duration_minutes' => 120, 'location' => 'Room B', 'goals' => 'New goal',
        'song_ids' => [2, 3],
    ]);
    $linked = array_map('intval', $database->query("SELECT song_id FROM rehearsal_songs WHERE rehearsal_id = {$rehearsalId} ORDER BY order_number")->fetchAll(PDO::FETCH_COLUMN));
    $assert($linked === [2, 3], 'Rehearsal edit did not replace the song selection.');
    $invalid(static fn () => $rehearsals->create(1, 1, [
        'title' => 'No songs', 'start_time' => '2026-08-13T19:00', 'duration_minutes' => 60,
        'location' => '', 'goals' => '', 'song_ids' => [],
    ]), 'A rehearsal without selected songs was accepted.');

    $availability = new AvailabilityController($projectRoot);
    $availabilityView = $availability->show($rehearsalId, 1);
    $assert(count($availabilityView['members']) === 4 && $availabilityView['can_edit_all'] === true, 'Owner availability view is incomplete.');
    $availability->save($rehearsalId, 2, 1, ['status' => 'available', 'note' => 'Available after class']);
    $assert($database->query("SELECT status FROM availability WHERE rehearsal_id = {$rehearsalId} AND member_id = 2")->fetchColumn() === 'available', 'Availability was not saved.');
    $rehearsals->cancel(1, $rehearsalId, 1);
    $assert($database->query("SELECT status FROM rehearsals WHERE id = {$rehearsalId}")->fetchColumn() === 'cancelled', 'Rehearsal was not cancelled.');

    $reviewController = new RehearsalReviewController($projectRoot);
    $reviewController->save(2, 1, [
        'overall_rating' => 4, 'goals_met' => 'partly', 'notes' => 'History test',
        'songs' => [[
            'song_id' => 1, 'performance_rating' => 3, 'progress_level_after' => 'rehearsing',
            'status_after' => 'practising', 'problem_type' => 'rhythm', 'note' => 'Count-in needs work',
        ]],
    ]);
    $history = $reviewController->history(1, 1)['history'];
    $assert(count($history) === 1 && $history[0]['song_title'] === 'Little Wing', 'Survey history did not return the per-song review.');
    $assert($history[0]['problem_type'] === 'rhythm', 'Survey history lost the problem filter value.');

    fwrite(STDOUT, "BandPilot core management test passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    unset($database);
    if (is_file($databasePath)) unlink($databasePath);
}
