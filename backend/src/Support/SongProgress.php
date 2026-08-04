<?php

declare(strict_types=1);

namespace BandPilot\Support;

use InvalidArgumentException;

final class SongProgress
{
    public const LEVELS = ['starting', 'learning', 'rehearsing', 'polishing', 'ready'];

    public static function validate(mixed $value, string $message = 'Song progress level is not valid.'): string
    {
        $level = (string) $value;
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException($message);
        }
        return $level;
    }

    public static function legacyValue(string $level): int
    {
        return match ($level) {
            'starting' => 0,
            'learning' => 25,
            'rehearsing' => 50,
            'polishing' => 75,
            'ready' => 100,
            default => throw new InvalidArgumentException('Song progress level is not valid.'),
        };
    }
}
