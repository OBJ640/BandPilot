<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use InvalidArgumentException;

final class PerformanceController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function create(int $bandId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $startTime = trim((string) ($input['start_time'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));
        $length = filter_var($input['length_minutes'] ?? 0, FILTER_VALIDATE_INT);
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($name === '' || $startTime === '') {
            throw new InvalidArgumentException('Performance name and start time are required.');
        }
        if ($length === false || $length < 1 || $length > 300) {
            throw new InvalidArgumentException('Set length must be between 1 and 300 minutes.');
        }
        if (strtotime($startTime) === false) {
            throw new InvalidArgumentException('Start time is not valid.');
        }

        $database = Database::connection($this->projectRoot);
        $statement = $database->prepare(
            'INSERT INTO performances (band_id, name, start_time, location, length_minutes, notes)
             VALUES (:band_id, :name, :start_time, :location, :length_minutes, :notes)'
        );
        $statement->execute([
            'band_id' => $bandId,
            'name' => $name,
            'start_time' => $startTime,
            'location' => $location,
            'length_minutes' => $length,
            'notes' => $notes,
        ]);

        $id = (int) $database->lastInsertId();
        $result = $database->prepare(
            'SELECT id, name, start_time, location, length_minutes, notes, status
             FROM performances WHERE id = :id'
        );
        $result->execute(['id' => $id]);

        return ['performance' => $result->fetch()];
    }
}
