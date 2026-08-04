<?php

declare(strict_types=1);

namespace BandPilot\Support;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function input(): array
    {
        $body = file_get_contents('php://input');
        if ($body === false || trim($body) === '') {
            return [];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            self::json(['error' => 'Request body must be valid JSON.'], 400);
        }

        return $data;
    }
}
