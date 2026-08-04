<?php

declare(strict_types=1);

namespace BandPilot\Support;

use InvalidArgumentException;

final class BandRole
{
    public const OPTIONS = [
        'Lead vocals', 'Backing vocals',
        'Guitar', 'Lead guitar', 'Rhythm guitar', 'Acoustic guitar',
        'Bass', 'Drums', 'Percussion',
        'Piano', 'Keyboards', 'Synthesizer', 'DJ / Electronic',
        'Violin', 'Viola', 'Cello', 'Double bass',
        'Flute', 'Clarinet', 'Saxophone',
        'Trumpet', 'Trombone', 'French horn',
        'Harmonica', 'Ukulele', 'Banjo',
        'Producer', 'Songwriter', 'Composer / Arranger', 'Music director',
        'Sound engineer', 'Band manager', 'Multi-instrumentalist', 'Other',
    ];

    public static function validate(mixed $value): string
    {
        $role = trim((string) $value);
        if (!in_array($role, self::OPTIONS, true)) {
            throw new InvalidArgumentException('Please choose a valid band role.');
        }
        return $role;
    }
}
