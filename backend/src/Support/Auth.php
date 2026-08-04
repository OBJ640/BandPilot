<?php

declare(strict_types=1);

namespace BandPilot\Support;

use RuntimeException;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name('bandpilot_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new RuntimeException('The login session could not be started.');
        }

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function currentUserId(): ?int
    {
        self::start();
        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        return $userId === false || $userId < 1 ? null : $userId;
    }

    public static function signIn(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function signOut(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::start();
        return (string) $_SESSION['csrf_token'];
    }

    public static function requireCsrfToken(): void
    {
        $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($provided === '' || !hash_equals(self::csrfToken(), $provided)) {
            throw new HttpException(419, 'Your session expired. Please refresh the page and try again.');
        }
    }
}
