<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use BandPilot\Support\SongProgress;
use InvalidArgumentException;
use PDO;

final class SongController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function index(int $bandId): array
    {
        $statement = $this->database()->prepare(
            'SELECT id, title, artist, progress_level, status, problem_notes, created_at, updated_at
             FROM songs
             WHERE band_id = :band_id AND archived_at IS NULL
             ORDER BY updated_at DESC, id DESC'
        );
        $statement->execute(['band_id' => $bandId]);

        return ['songs' => $statement->fetchAll()];
    }

    public function create(int $bandId, array $input): array
    {
        [$title, $artist, $progressLevel, $status, $problemNotes] = $this->validatedInput($input);

        $database = $this->database();
        $statement = $database->prepare(
            'INSERT INTO songs (band_id, title, artist, progress, progress_level, status, problem_notes)
             VALUES (:band_id, :title, :artist, :progress, :progress_level, :status, :problem_notes)'
        );
        $statement->execute([
            'band_id' => $bandId,
            'title' => $title,
            'artist' => $artist,
            'progress' => SongProgress::legacyValue($progressLevel),
            'progress_level' => $progressLevel,
            'status' => $status,
            'problem_notes' => $problemNotes,
        ]);

        $songId = (int) $database->lastInsertId();
        $songStatement = $database->prepare(
            'SELECT id, title, artist, progress_level, status, problem_notes, created_at, updated_at
             FROM songs WHERE id = :id'
        );
        $songStatement->execute(['id' => $songId]);

        return ['song' => $songStatement->fetch()];
    }

    public function update(int $bandId, int $songId, int $userId, array $input): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        [$title, $artist, $progressLevel, $status, $problemNotes] = $this->validatedInput($input);
        $statement = $this->database()->prepare(
            'UPDATE songs SET title = :title, artist = :artist, progress = :progress,
                    progress_level = :progress_level, status = :status, problem_notes = :problem_notes
             WHERE id = :id AND band_id = :band_id AND archived_at IS NULL'
        );
        $statement->execute([
            'title' => $title,
            'artist' => $artist,
            'progress' => SongProgress::legacyValue($progressLevel),
            'progress_level' => $progressLevel,
            'status' => $status,
            'problem_notes' => $problemNotes,
            'id' => $songId,
            'band_id' => $bandId,
        ]);
        return ['song' => $this->find($bandId, $songId)];
    }

    public function archive(int $bandId, int $songId, int $userId): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        $statement = $this->database()->prepare(
            'UPDATE songs SET archived_at = CURRENT_TIMESTAMP WHERE id = :id AND band_id = :band_id AND archived_at IS NULL'
        );
        $statement->execute(['id' => $songId, 'band_id' => $bandId]);
        if ($statement->rowCount() < 1) {
            throw new InvalidArgumentException('Song not found.');
        }
        return ['archived' => true, 'song_id' => $songId];
    }

    private function validatedInput(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $artist = trim((string) ($input['artist'] ?? ''));
        $progressLevel = SongProgress::validate($input['progress_level'] ?? 'starting');
        $status = (string) ($input['status'] ?? 'learning');
        $problemNotes = trim((string) ($input['problem_notes'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Song title is required.');
        }
        if (mb_strlen($title) > 120 || mb_strlen($artist) > 120 || mb_strlen($problemNotes) > 1000) {
            throw new InvalidArgumentException('Song information is too long.');
        }
        if (!in_array($status, ['learning', 'practising', 'ready'], true)) {
            throw new InvalidArgumentException('Song status is not valid.');
        }
        return [$title, $artist, $progressLevel, $status, $problemNotes];
    }

    private function find(int $bandId, int $songId): array
    {
        $statement = $this->database()->prepare(
            'SELECT id, title, artist, progress_level, status, problem_notes, created_at, updated_at
             FROM songs WHERE id = :id AND band_id = :band_id AND archived_at IS NULL'
        );
        $statement->execute(['id' => $songId, 'band_id' => $bandId]);
        $song = $statement->fetch();
        if (!$song) {
            throw new InvalidArgumentException('Song not found.');
        }
        return $song;
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
