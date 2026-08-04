<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use BandPilot\Support\BandRole;
use InvalidArgumentException;
use PDO;

final class UserController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function show(int $userId, ?int $bandId = null): array
    {
        if ($bandId === null) {
            $statement = $this->database()->prepare(
                "SELECT users.id, users.name, users.email, users.created_at, '' AS instrument
                 FROM users WHERE users.id = :user_id"
            );
            $statement->execute(['user_id' => $userId]);
        } else {
            $statement = $this->database()->prepare(
                "SELECT users.id, users.name, users.email, users.created_at,
                        COALESCE(band_members.instrument, '') AS instrument
                 FROM users
                 LEFT JOIN band_members ON band_members.user_id = users.id AND band_members.band_id = :band_id
                 WHERE users.id = :user_id"
            );
            $statement->execute(['band_id' => $bandId, 'user_id' => $userId]);
        }
        $user = $statement->fetch();
        if (!$user) {
            throw new InvalidArgumentException('User not found.');
        }
        return ['user' => $user];
    }

    public function update(int $userId, int $bandId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $instrument = BandRole::validate($input['instrument'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid name and email are required.');
        }
        if (mb_strlen($name) > 120 || mb_strlen($email) > 190 || mb_strlen($instrument) > 120) {
            throw new InvalidArgumentException('Profile information is too long.');
        }

        $database = $this->database();
        $emailStatement = $database->prepare('SELECT 1 FROM users WHERE lower(email) = :email AND id != :id');
        $emailStatement->execute(['email' => $email, 'id' => $userId]);
        if ($emailStatement->fetchColumn()) {
            throw new InvalidArgumentException('This email is already used by another account.');
        }

        $database->beginTransaction();
        try {
            $userStatement = $database->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
            $userStatement->execute(['name' => $name, 'email' => $email, 'id' => $userId]);

            $nameStatement = $database->prepare('UPDATE band_members SET display_name = :name WHERE user_id = :user_id');
            $nameStatement->execute(['name' => $name, 'user_id' => $userId]);

            $instrumentStatement = $database->prepare(
                'UPDATE band_members SET instrument = :instrument WHERE user_id = :user_id AND band_id = :band_id'
            );
            $instrumentStatement->execute([
                'instrument' => $instrument,
                'user_id' => $userId,
                'band_id' => $bandId,
            ]);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        return $this->show($userId, $bandId);
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
