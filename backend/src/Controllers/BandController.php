<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use InvalidArgumentException;
use PDO;

final class BandController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function index(int $userId): array
    {
        $statement = $this->database()->prepare(
            "SELECT DISTINCT bands.id, bands.name, bands.description, bands.owner_id, bands.created_at,
                    CASE WHEN bands.owner_id = :role_user_id THEN 'owner' ELSE 'member' END AS user_role,
                    (SELECT COUNT(*) FROM band_members WHERE band_members.band_id = bands.id) AS member_count
             FROM bands
             LEFT JOIN band_members ON band_members.band_id = bands.id AND band_members.user_id = :member_user_id
             WHERE bands.archived_at IS NULL
               AND (bands.owner_id = :owner_user_id OR band_members.user_id = :joined_user_id)
             ORDER BY bands.created_at ASC, bands.id ASC"
        );
        $statement->execute([
            'role_user_id' => $userId,
            'member_user_id' => $userId,
            'owner_user_id' => $userId,
            'joined_user_id' => $userId,
        ]);

        return ['bands' => $statement->fetchAll()];
    }

    public function show(int $bandId, int $userId): array
    {
        $this->assertMembership($bandId, $userId);
        return ['band' => $this->find($bandId, $userId)];
    }

    public function create(int $userId, array $input): array
    {
        [$name, $description] = $this->validatedInput($input);
        $database = $this->database();
        $userStatement = $database->prepare(
            "SELECT users.name,
                    COALESCE((SELECT instrument FROM band_members WHERE user_id = users.id AND instrument != '' LIMIT 1), '') AS instrument
             FROM users WHERE users.id = :id"
        );
        $userStatement->execute(['id' => $userId]);
        $user = $userStatement->fetch();
        if (!$user) {
            throw new InvalidArgumentException('Current user was not found.');
        }

        $database->beginTransaction();
        try {
            $bandStatement = $database->prepare(
                'INSERT INTO bands (name, description, owner_id) VALUES (:name, :description, :owner_id)'
            );
            $bandStatement->execute(['name' => $name, 'description' => $description, 'owner_id' => $userId]);
            $bandId = (int) $database->lastInsertId();

            $memberStatement = $database->prepare(
                "INSERT INTO band_members (band_id, user_id, display_name, role, instrument)
                 VALUES (:band_id, :user_id, :display_name, 'owner', :instrument)"
            );
            $memberStatement->execute([
                'band_id' => $bandId,
                'user_id' => $userId,
                'display_name' => $user['name'],
                'instrument' => $user['instrument'],
            ]);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        return ['band' => $this->find($bandId, $userId)];
    }

    public function update(int $bandId, int $userId, array $input): array
    {
        $this->assertOwner($bandId, $userId);
        [$name, $description] = $this->validatedInput($input);
        $statement = $this->database()->prepare(
            'UPDATE bands SET name = :name, description = :description WHERE id = :id AND owner_id = :owner_id'
        );
        $statement->execute([
            'name' => $name,
            'description' => $description,
            'id' => $bandId,
            'owner_id' => $userId,
        ]);

        return ['band' => $this->find($bandId, $userId)];
    }

    private function validatedInput(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Band name is required.');
        }
        if (mb_strlen($name) > 120 || mb_strlen($description) > 500) {
            throw new InvalidArgumentException('Band information is too long.');
        }
        return [$name, $description];
    }

    private function find(int $bandId, int $userId): array
    {
        $statement = $this->database()->prepare(
            "SELECT bands.id, bands.name, bands.description, bands.owner_id, bands.created_at,
                    CASE WHEN bands.owner_id = :user_id THEN 'owner' ELSE 'member' END AS user_role,
                    (SELECT COUNT(*) FROM band_members WHERE band_members.band_id = bands.id) AS member_count
             FROM bands WHERE bands.id = :band_id AND bands.archived_at IS NULL"
        );
        $statement->execute(['user_id' => $userId, 'band_id' => $bandId]);
        $band = $statement->fetch();
        if (!$band) {
            throw new InvalidArgumentException('Band not found.');
        }
        return $band;
    }

    public function assertMembership(int $bandId, int $userId): void
    {
        $statement = $this->database()->prepare(
            'SELECT 1 FROM bands
             LEFT JOIN band_members ON band_members.band_id = bands.id
             WHERE bands.id = :band_id AND bands.archived_at IS NULL
               AND (bands.owner_id = :owner_id OR band_members.user_id = :member_id)
             LIMIT 1'
        );
        $statement->execute(['band_id' => $bandId, 'owner_id' => $userId, 'member_id' => $userId]);
        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException('You do not have access to this band.');
        }
    }

    public function assertOwner(int $bandId, int $userId): void
    {
        $statement = $this->database()->prepare(
            'SELECT 1 FROM bands WHERE id = :band_id AND owner_id = :user_id AND archived_at IS NULL'
        );
        $statement->execute(['band_id' => $bandId, 'user_id' => $userId]);
        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException('Only the band owner can edit this band.');
        }
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
