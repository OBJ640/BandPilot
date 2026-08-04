<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use BandPilot\Support\HttpException;
use InvalidArgumentException;
use PDO;
use PDOException;

final class AuthController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function register(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Please enter a name of 120 characters or fewer.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new InvalidArgumentException('Please enter a valid email address.');
        }
        if (mb_strlen($password) < 8 || mb_strlen($password) > 128) {
            throw new InvalidArgumentException('Password must be between 8 and 128 characters.');
        }

        $database = $this->database();
        $existing = $database->prepare('SELECT 1 FROM users WHERE lower(email) = :email');
        $existing->execute(['email' => $email]);
        if ($existing->fetchColumn()) {
            throw new HttpException(409, 'An account with this email already exists.');
        }

        try {
            $statement = $database->prepare(
                'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new HttpException(409, 'An account with this email already exists.');
            }
            throw $exception;
        }

        return ['user' => $this->findUser((int) $database->lastInsertId())];
    }

    public function login(array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new HttpException(401, 'The email or password is incorrect.');
        }

        $statement = $this->database()->prepare(
            'SELECT id, name, email, password_hash, created_at FROM users WHERE lower(email) = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException(401, 'The email or password is incorrect.');
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $update = $this->database()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $update->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
        }

        unset($user['password_hash']);
        return ['user' => $user];
    }

    public function session(int $userId): array
    {
        return ['user' => $this->findUser($userId)];
    }

    private function findUser(int $userId): array
    {
        $statement = $this->database()->prepare(
            'SELECT id, name, email, created_at FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!$user) {
            throw new HttpException(401, 'Please sign in to continue.');
        }
        return $user;
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
