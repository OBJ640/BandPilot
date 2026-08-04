<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use InvalidArgumentException;
use PDO;

final class RehearsalController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function index(int $bandId): array
    {
        $statement = $this->database()->prepare(
            "SELECT rehearsals.id, rehearsals.band_id, rehearsals.title, rehearsals.start_time,
                    rehearsals.duration_minutes, rehearsals.location, rehearsals.goals, rehearsals.status,
                    CASE WHEN rehearsal_reviews.rehearsal_id IS NULL THEN 0 ELSE 1 END AS review_completed,
                    rehearsal_reviews.overall_rating, rehearsal_reviews.goals_met,
                    COUNT(DISTINCT rehearsal_songs.song_id) AS song_count,
                    GROUP_CONCAT(DISTINCT songs.title) AS song_titles,
                    GROUP_CONCAT(DISTINCT songs.id) AS song_ids
             FROM rehearsals
             LEFT JOIN rehearsal_reviews ON rehearsal_reviews.rehearsal_id = rehearsals.id
             LEFT JOIN rehearsal_songs ON rehearsal_songs.rehearsal_id = rehearsals.id
             LEFT JOIN songs ON songs.id = rehearsal_songs.song_id
             WHERE rehearsals.band_id = :band_id
             GROUP BY rehearsals.id
             ORDER BY rehearsals.start_time DESC, rehearsals.id DESC"
        );
        $statement->execute(['band_id' => $bandId]);
        return ['rehearsals' => $statement->fetchAll()];
    }

    public function create(int $bandId, int $userId, array $input): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        [$title, $startTime, $duration, $location, $goals, $songIds] = $this->validatedInput($bandId, $input);
        $database = $this->database();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                'INSERT INTO rehearsals (band_id, title, start_time, duration_minutes, location, goals)
                 VALUES (:band_id, :title, :start_time, :duration_minutes, :location, :goals)'
            );
            $statement->execute([
                'band_id' => $bandId, 'title' => $title, 'start_time' => $startTime,
                'duration_minutes' => $duration, 'location' => $location, 'goals' => $goals,
            ]);
            $rehearsalId = (int) $database->lastInsertId();
            $this->replaceSongs($rehearsalId, $songIds);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }
        return ['rehearsal' => $this->find($bandId, $rehearsalId)];
    }

    public function update(int $bandId, int $rehearsalId, int $userId, array $input): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        $existing = $this->find($bandId, $rehearsalId);
        if ($existing['status'] !== 'planned') {
            throw new InvalidArgumentException('Only a planned rehearsal can be edited.');
        }
        [$title, $startTime, $duration, $location, $goals, $songIds] = $this->validatedInput($bandId, $input);
        $database = $this->database();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                'UPDATE rehearsals SET title = :title, start_time = :start_time,
                    duration_minutes = :duration_minutes, location = :location, goals = :goals
                 WHERE id = :id AND band_id = :band_id AND status = \'planned\''
            );
            $statement->execute([
                'title' => $title, 'start_time' => $startTime, 'duration_minutes' => $duration,
                'location' => $location, 'goals' => $goals, 'id' => $rehearsalId, 'band_id' => $bandId,
            ]);
            $this->replaceSongs($rehearsalId, $songIds);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }
        return ['rehearsal' => $this->find($bandId, $rehearsalId)];
    }

    public function cancel(int $bandId, int $rehearsalId, int $userId): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        $statement = $this->database()->prepare(
            "UPDATE rehearsals SET status = 'cancelled'
             WHERE id = :id AND band_id = :band_id AND status = 'planned'"
        );
        $statement->execute(['id' => $rehearsalId, 'band_id' => $bandId]);
        if ($statement->rowCount() < 1) {
            throw new InvalidArgumentException('Only a planned rehearsal can be cancelled.');
        }
        return ['rehearsal' => $this->find($bandId, $rehearsalId)];
    }

    private function validatedInput(int $bandId, array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $startTime = trim((string) ($input['start_time'] ?? ''));
        $duration = filter_var($input['duration_minutes'] ?? 0, FILTER_VALIDATE_INT);
        $location = trim((string) ($input['location'] ?? ''));
        $goals = trim((string) ($input['goals'] ?? ''));
        $songIds = array_values(array_unique(array_map('intval', is_array($input['song_ids'] ?? null) ? $input['song_ids'] : [])));
        if ($title === '' || $startTime === '' || strtotime($startTime) === false) {
            throw new InvalidArgumentException('A valid title and start time are required.');
        }
        if ($duration === false || $duration < 15 || $duration > 480) {
            throw new InvalidArgumentException('Rehearsal length must be between 15 and 480 minutes.');
        }
        if (mb_strlen($title) > 120 || mb_strlen($location) > 160 || mb_strlen($goals) > 1000) {
            throw new InvalidArgumentException('Rehearsal information is too long.');
        }
        if ($songIds === [] || count($songIds) > 50 || min($songIds) < 1) {
            throw new InvalidArgumentException('Choose at least one valid song for the rehearsal.');
        }
        $placeholders = implode(',', array_fill(0, count($songIds), '?'));
        $statement = $this->database()->prepare(
            "SELECT id FROM songs WHERE band_id = ? AND archived_at IS NULL AND id IN ({$placeholders})"
        );
        $statement->execute([$bandId, ...$songIds]);
        $validIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        sort($validIds);
        $sortedInput = $songIds;
        sort($sortedInput);
        if ($validIds !== $sortedInput) {
            throw new InvalidArgumentException('One or more selected songs do not belong to this band.');
        }
        return [$title, $startTime, $duration, $location, $goals, $songIds];
    }

    private function replaceSongs(int $rehearsalId, array $songIds): void
    {
        $delete = $this->database()->prepare('DELETE FROM rehearsal_songs WHERE rehearsal_id = :id');
        $delete->execute(['id' => $rehearsalId]);
        $insert = $this->database()->prepare(
            'INSERT INTO rehearsal_songs (rehearsal_id, song_id, order_number)
             VALUES (:rehearsal_id, :song_id, :order_number)'
        );
        foreach ($songIds as $index => $songId) {
            $insert->execute(['rehearsal_id' => $rehearsalId, 'song_id' => $songId, 'order_number' => $index + 1]);
        }
    }

    private function find(int $bandId, int $rehearsalId): array
    {
        $statement = $this->database()->prepare(
            "SELECT rehearsals.id, rehearsals.band_id, rehearsals.title, rehearsals.start_time,
                    rehearsals.duration_minutes, rehearsals.location, rehearsals.goals, rehearsals.status,
                    GROUP_CONCAT(rehearsal_songs.song_id) AS song_ids
             FROM rehearsals
             LEFT JOIN rehearsal_songs ON rehearsal_songs.rehearsal_id = rehearsals.id
             WHERE rehearsals.id = :id AND rehearsals.band_id = :band_id
             GROUP BY rehearsals.id"
        );
        $statement->execute(['id' => $rehearsalId, 'band_id' => $bandId]);
        $rehearsal = $statement->fetch();
        if (!$rehearsal) {
            throw new InvalidArgumentException('Rehearsal not found.');
        }
        return $rehearsal;
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
