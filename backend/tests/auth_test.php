<?php

declare(strict_types=1);

use BandPilot\Controllers\AuthController;
use BandPilot\Support\Auth;
use BandPilot\Support\Database;
use BandPilot\Support\HttpException;

$databasePath = tempnam(sys_get_temp_dir(), 'bandpilot-auth-test-');
if ($databasePath === false) {
    fwrite(STDERR, "Could not create the authentication test database.\n");
    exit(1);
}

putenv('DB_PATH=' . $databasePath);
$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';
$database = Database::connection($projectRoot);
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');

try {
    if ($schema === false) {
        throw new RuntimeException('The database schema could not be read.');
    }
    $database->exec($schema);

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $expectInvalidInput = static function (callable $action, string $message) use ($assert): void {
        try {
            $action();
        } catch (InvalidArgumentException) {
            return;
        }
        $assert(false, $message);
    };

    $expectHttpError = static function (
        callable $action,
        int $expectedStatus,
        string $message
    ) use ($assert): HttpException {
        try {
            $action();
        } catch (HttpException $exception) {
            $assert($exception->status() === $expectedStatus, $message . ' The status code was incorrect.');
            return $exception;
        }
        throw new RuntimeException($message);
    };

    $controller = new AuthController($projectRoot);
    $password = 'PracticeRoom42!';
    $registered = $controller->register([
        'name' => '  New Musician  ',
        'email' => 'NEW.MUSICIAN@EXAMPLE.COM',
        'password' => $password,
    ]);
    $user = $registered['user'] ?? null;

    $assert(is_array($user), 'Registration did not return a user.');
    $assert((int) ($user['id'] ?? 0) > 0, 'The registered user did not receive an ID.');
    $assert(($user['name'] ?? null) === 'New Musician', 'Registration did not trim the user name.');
    $assert(
        ($user['email'] ?? null) === 'new.musician@example.com',
        'Registration did not normalize the email to lowercase.'
    );
    $assert(isset($user['created_at']), 'Registration did not return the account creation time.');
    $assert(!array_key_exists('password_hash', $user), 'Registration exposed the password hash.');
    $assert(!array_key_exists('password', $user), 'Registration exposed the plain-text password.');

    $savedUser = $database->query(
        "SELECT id, name, email, password_hash FROM users WHERE email = 'new.musician@example.com'"
    )->fetch();
    $assert(is_array($savedUser), 'The registered user was not saved.');
    $assert($savedUser['password_hash'] !== $password, 'The password was stored as plain text.');
    $assert(password_verify($password, (string) $savedUser['password_hash']), 'The stored password hash is invalid.');

    Auth::signIn((int) $user['id']);
    $assert(Auth::currentUserId() === (int) $user['id'], 'Registration could not establish the automatic login session.');
    $sessionUser = $controller->session((int) Auth::currentUserId())['user'] ?? null;
    $assert(is_array($sessionUser), 'The automatic login session did not return the registered user.');
    $assert(!array_key_exists('password_hash', $sessionUser), 'The session response exposed the password hash.');

    $duplicateError = $expectHttpError(
        static fn (): array => $controller->register([
            'name' => 'Duplicate User',
            'email' => 'New.Musician@Example.Com',
            'password' => 'AnotherPassword42!',
        ]),
        409,
        'Registration allowed the same email with different letter casing.'
    );
    $assert(
        $duplicateError->getMessage() === 'An account with this email already exists.',
        'Duplicate registration returned an unexpected message.'
    );
    $assert((int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'Duplicate registration added a user.');

    $loggedIn = $controller->login([
        'email' => 'New.Musician@Example.Com',
        'password' => $password,
    ])['user'] ?? null;
    $assert(is_array($loggedIn), 'Correct credentials did not sign in.');
    $assert((int) ($loggedIn['id'] ?? 0) === (int) $user['id'], 'Login returned the wrong user.');
    $assert(!array_key_exists('password_hash', $loggedIn), 'Login exposed the password hash.');

    $wrongPassword = $expectHttpError(
        static fn (): array => $controller->login([
            'email' => 'new.musician@example.com',
            'password' => 'DefinitelyWrong42!',
        ]),
        401,
        'An incorrect password was accepted.'
    );
    $unknownEmail = $expectHttpError(
        static fn (): array => $controller->login([
            'email' => 'missing@example.com',
            'password' => $password,
        ]),
        401,
        'An unknown email was accepted.'
    );
    $assert(
        $wrongPassword->getMessage() === $unknownEmail->getMessage(),
        'Wrong-password and unknown-email login errors should use the same message.'
    );

    $expectInvalidInput(
        static fn (): array => $controller->register([
            'name' => 'Bad Email',
            'email' => 'not-an-email',
            'password' => 'LongEnough42!',
        ]),
        'Registration accepted an invalid email.'
    );
    $expectInvalidInput(
        static fn (): array => $controller->register([
            'name' => 'Short Password',
            'email' => 'short@example.com',
            'password' => 'short',
        ]),
        'Registration accepted a password shorter than eight characters.'
    );

    fwrite(STDOUT, "BandPilot authentication test passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        Auth::signOut();
    }
    unset($database);
    if (is_file($databasePath)) {
        unlink($databasePath);
    }
}
