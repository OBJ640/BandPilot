<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use BandPilot\Support\SongProgress;
use InvalidArgumentException;
use PDO;

final class RehearsalReviewController
{
    private const GOALS_MET = ['yes', 'partly', 'no'];
    private const SONG_STATUSES = ['learning', 'practising', 'ready'];
    private const PROBLEM_TYPES = ['none', 'rhythm', 'coordination', 'technique', 'tone', 'memory', 'other'];

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function show(int $rehearsalId, int $userId): array
    {
        $database = $this->database();
        $rehearsal = $this->findRehearsal($rehearsalId);
        $bandController = new BandController($this->projectRoot);
        $bandController->assertMembership((int) $rehearsal['band_id'], $userId);

        $reviewStatement = $database->prepare(
            'SELECT overall_rating, goals_met, notes, completed_at, updated_at
             FROM rehearsal_reviews WHERE rehearsal_id = :rehearsal_id'
        );
        $reviewStatement->execute(['rehearsal_id' => $rehearsalId]);
        $review = $reviewStatement->fetch() ?: null;

        $songStatement = $database->prepare(
            "SELECT songs.id, songs.title, songs.artist, songs.progress_level, songs.status,
                    CASE WHEN rehearsal_songs.song_id IS NULL THEN 0 ELSE 1 END AS planned,
                    rehearsal_song_reviews.performance_rating,
                    rehearsal_song_reviews.progress_level_after,
                    rehearsal_song_reviews.status_after,
                    rehearsal_song_reviews.problem_type,
                    rehearsal_song_reviews.note AS review_note
             FROM songs
             LEFT JOIN rehearsal_songs
               ON rehearsal_songs.song_id = songs.id AND rehearsal_songs.rehearsal_id = :planned_rehearsal_id
             LEFT JOIN rehearsal_song_reviews
               ON rehearsal_song_reviews.song_id = songs.id AND rehearsal_song_reviews.rehearsal_id = :review_rehearsal_id
             WHERE songs.band_id = :band_id AND songs.archived_at IS NULL
             ORDER BY planned DESC, songs.updated_at DESC, songs.id DESC"
        );
        $songStatement->execute([
            'planned_rehearsal_id' => $rehearsalId,
            'review_rehearsal_id' => $rehearsalId,
            'band_id' => $rehearsal['band_id'],
        ]);

        $ownerStatement = $database->prepare('SELECT 1 FROM bands WHERE id = :band_id AND owner_id = :user_id');
        $ownerStatement->execute(['band_id' => $rehearsal['band_id'], 'user_id' => $userId]);

        return [
            'rehearsal' => $rehearsal,
            'review' => $review,
            'songs' => $songStatement->fetchAll(),
            'completed' => $review !== null,
            'can_edit' => (bool) $ownerStatement->fetchColumn(),
        ];
    }

    public function save(int $rehearsalId, int $userId, array $input): array
    {
        $rehearsal = $this->findRehearsal($rehearsalId);
        (new BandController($this->projectRoot))->assertOwner((int) $rehearsal['band_id'], $userId);

        $overallRating = filter_var($input['overall_rating'] ?? null, FILTER_VALIDATE_INT);
        $goalsMet = (string) ($input['goals_met'] ?? '');
        $notes = trim((string) ($input['notes'] ?? ''));
        $songs = $input['songs'] ?? null;
        if ($overallRating === false || $overallRating < 1 || $overallRating > 5) {
            throw new InvalidArgumentException('Overall rehearsal rating must be between 1 and 5.');
        }
        if (!in_array($goalsMet, self::GOALS_MET, true)) {
            throw new InvalidArgumentException('Please choose whether the rehearsal goals were met.');
        }
        if (mb_strlen($notes) > 1000) {
            throw new InvalidArgumentException('Overall rehearsal notes must be 1000 characters or fewer.');
        }
        if (!is_array($songs) || $songs === []) {
            throw new InvalidArgumentException('Choose at least one song from this rehearsal.');
        }

        $database = $this->database();
        $allowedStatement = $database->prepare(
            'SELECT id FROM songs WHERE band_id = :band_id AND archived_at IS NULL'
        );
        $allowedStatement->execute(['band_id' => $rehearsal['band_id']]);
        $allowedSongIds = array_map('intval', $allowedStatement->fetchAll(PDO::FETCH_COLUMN));
        $validatedSongs = [];
        $seen = [];
        foreach ($songs as $song) {
            if (!is_array($song)) {
                throw new InvalidArgumentException('Each song answer must be an object.');
            }
            $songId = filter_var($song['song_id'] ?? null, FILTER_VALIDATE_INT);
            $performanceRating = filter_var($song['performance_rating'] ?? null, FILTER_VALIDATE_INT);
            $progressLevelAfter = SongProgress::validate(
                $song['progress_level_after'] ?? null,
                'Each song must have one of the five progress levels.'
            );
            $statusAfter = (string) ($song['status_after'] ?? '');
            $problemType = (string) ($song['problem_type'] ?? '');
            $note = trim((string) ($song['note'] ?? ''));
            if ($songId === false || !in_array($songId, $allowedSongIds, true) || isset($seen[$songId])) {
                throw new InvalidArgumentException('A selected song does not belong to this band or appears more than once.');
            }
            if ($performanceRating === false || $performanceRating < 1 || $performanceRating > 5) {
                throw new InvalidArgumentException('Each song rating must be between 1 and 5.');
            }
            if (!in_array($statusAfter, self::SONG_STATUSES, true)
                || !in_array($problemType, self::PROBLEM_TYPES, true)) {
                throw new InvalidArgumentException('A song answer contains an invalid choice.');
            }
            if (mb_strlen($note) > 500) {
                throw new InvalidArgumentException('Each song note must be 500 characters or fewer.');
            }
            $seen[$songId] = true;
            $validatedSongs[] = [
                'song_id' => $songId,
                'performance_rating' => $performanceRating,
                'progress_after' => SongProgress::legacyValue($progressLevelAfter),
                'progress_level_after' => $progressLevelAfter,
                'status_after' => $statusAfter,
                'problem_type' => $problemType,
                'note' => $note,
            ];
        }

        $database->beginTransaction();
        try {
            $reviewStatement = $database->prepare(
                'INSERT INTO rehearsal_reviews (rehearsal_id, overall_rating, goals_met, notes, updated_by)
                 VALUES (:rehearsal_id, :overall_rating, :goals_met, :notes, :updated_by)
                 ON CONFLICT(rehearsal_id) DO UPDATE SET
                    overall_rating = excluded.overall_rating,
                    goals_met = excluded.goals_met,
                    notes = excluded.notes,
                    updated_by = excluded.updated_by,
                    updated_at = CURRENT_TIMESTAMP'
            );
            $reviewStatement->execute([
                'rehearsal_id' => $rehearsalId,
                'overall_rating' => $overallRating,
                'goals_met' => $goalsMet,
                'notes' => $notes,
                'updated_by' => $userId,
            ]);

            $deleteStatement = $database->prepare(
                'DELETE FROM rehearsal_song_reviews WHERE rehearsal_id = :rehearsal_id'
            );
            $deleteStatement->execute(['rehearsal_id' => $rehearsalId]);

            $songReviewStatement = $database->prepare(
                'INSERT INTO rehearsal_song_reviews
                    (rehearsal_id, song_id, performance_rating, progress_after, progress_level_after, status_after, problem_type, note)
                 VALUES
                    (:rehearsal_id, :song_id, :performance_rating, :progress_after, :progress_level_after, :status_after, :problem_type, :note)'
            );
            $songUpdateStatement = $database->prepare(
                'UPDATE songs SET progress = :progress, progress_level = :progress_level, status = :status
                 WHERE id = :song_id AND band_id = :band_id'
            );
            $rehearsalSongStatement = $database->prepare(
                'INSERT OR IGNORE INTO rehearsal_songs (rehearsal_id, song_id, order_number)
                 VALUES (:rehearsal_id, :song_id, :order_number)'
            );
            foreach ($validatedSongs as $index => $song) {
                $songReviewStatement->execute(['rehearsal_id' => $rehearsalId, ...$song]);
                $rehearsalSongStatement->execute([
                    'rehearsal_id' => $rehearsalId,
                    'song_id' => $song['song_id'],
                    'order_number' => $index + 1,
                ]);
                $songUpdateStatement->execute([
                    'progress' => $song['progress_after'],
                    'progress_level' => $song['progress_level_after'],
                    'status' => $song['status_after'],
                    'song_id' => $song['song_id'],
                    'band_id' => $rehearsal['band_id'],
                ]);
            }

            $completeStatement = $database->prepare("UPDATE rehearsals SET status = 'completed' WHERE id = :id");
            $completeStatement->execute(['id' => $rehearsalId]);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        return $this->show($rehearsalId, $userId);
    }

    public function history(int $bandId, int $userId): array
    {
        (new BandController($this->projectRoot))->assertMembership($bandId, $userId);
        $statement = $this->database()->prepare(
            'SELECT rehearsals.id AS rehearsal_id, rehearsals.title AS rehearsal_title,
                    rehearsals.start_time, rehearsal_reviews.overall_rating,
                    rehearsal_reviews.goals_met, rehearsal_reviews.notes AS overall_notes,
                    songs.id AS song_id, songs.title AS song_title,
                    rehearsal_song_reviews.performance_rating,
                    rehearsal_song_reviews.progress_level_after,
                    rehearsal_song_reviews.status_after,
                    rehearsal_song_reviews.problem_type,
                    rehearsal_song_reviews.note
             FROM rehearsal_reviews
             JOIN rehearsals ON rehearsals.id = rehearsal_reviews.rehearsal_id
             LEFT JOIN rehearsal_song_reviews ON rehearsal_song_reviews.rehearsal_id = rehearsals.id
             LEFT JOIN songs ON songs.id = rehearsal_song_reviews.song_id
             WHERE rehearsals.band_id = :band_id
             ORDER BY rehearsals.start_time DESC, rehearsals.id DESC, songs.title ASC'
        );
        $statement->execute(['band_id' => $bandId]);
        return ['history' => $statement->fetchAll()];
    }

    private function findRehearsal(int $rehearsalId): array
    {
        $statement = $this->database()->prepare(
            'SELECT id, band_id, title, start_time, duration_minutes, location, goals, status
             FROM rehearsals WHERE id = :id'
        );
        $statement->execute(['id' => $rehearsalId]);
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
