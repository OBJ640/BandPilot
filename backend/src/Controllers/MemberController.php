<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\BandRole;
use BandPilot\Support\Database;
use InvalidArgumentException;
use PDO;

final class MemberController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function index(int $bandId, int $userId): array
    {
        $bandController = new BandController($this->projectRoot);
        $bandController->assertMembership($bandId, $userId);
        $statement = $this->database()->prepare(
            'SELECT band_members.id, band_members.user_id, band_members.display_name,
                    band_members.role AS access_role, band_members.instrument AS band_role,
                    band_members.joined_at,
                    CASE WHEN bands.owner_id = band_members.user_id THEN 1 ELSE 0 END AS is_owner
             FROM band_members
             JOIN bands ON bands.id = band_members.band_id
             WHERE band_members.band_id = :band_id
             ORDER BY is_owner DESC, band_members.joined_at ASC, band_members.id ASC'
        );
        $statement->execute(['band_id' => $bandId]);
        return [
            'members' => $statement->fetchAll(),
            'can_edit' => $this->isOwner($bandId, $userId),
            'role_options' => BandRole::OPTIONS,
        ];
    }

    public function create(int $bandId, int $userId, array $input): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        [$name, $bandRole] = $this->validatedInput($input);
        $statement = $this->database()->prepare(
            "INSERT INTO band_members (band_id, display_name, role, instrument)
             VALUES (:band_id, :display_name, 'member', :instrument)"
        );
        $statement->execute(['band_id' => $bandId, 'display_name' => $name, 'instrument' => $bandRole]);
        return $this->index($bandId, $userId);
    }

    public function update(int $bandId, int $memberId, int $userId, array $input): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        [$name, $bandRole] = $this->validatedInput($input);
        $statement = $this->database()->prepare(
            'UPDATE band_members SET display_name = :display_name, instrument = :instrument
             WHERE id = :id AND band_id = :band_id'
        );
        $statement->execute([
            'display_name' => $name,
            'instrument' => $bandRole,
            'id' => $memberId,
            'band_id' => $bandId,
        ]);
        if ($statement->rowCount() < 1 && !$this->memberExists($bandId, $memberId)) {
            throw new InvalidArgumentException('Band member not found.');
        }
        return $this->index($bandId, $userId);
    }

    public function remove(int $bandId, int $memberId, int $userId): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        $statement = $this->database()->prepare(
            'SELECT band_members.user_id, bands.owner_id
             FROM band_members JOIN bands ON bands.id = band_members.band_id
             WHERE band_members.id = :member_id AND band_members.band_id = :band_id'
        );
        $statement->execute(['member_id' => $memberId, 'band_id' => $bandId]);
        $member = $statement->fetch();
        if (!$member) {
            throw new InvalidArgumentException('Band member not found.');
        }
        if ($member['user_id'] !== null && (int) $member['user_id'] === (int) $member['owner_id']) {
            throw new InvalidArgumentException('The band owner cannot be removed.');
        }
        $delete = $this->database()->prepare('DELETE FROM band_members WHERE id = :id AND band_id = :band_id');
        $delete->execute(['id' => $memberId, 'band_id' => $bandId]);
        return $this->index($bandId, $userId);
    }

    private function validatedInput(array $input): array
    {
        $name = trim((string) ($input['display_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Member name is required and must be 120 characters or fewer.');
        }
        return [$name, BandRole::validate($input['band_role'] ?? '')];
    }

    private function memberExists(int $bandId, int $memberId): bool
    {
        $statement = $this->database()->prepare('SELECT 1 FROM band_members WHERE id = :id AND band_id = :band_id');
        $statement->execute(['id' => $memberId, 'band_id' => $bandId]);
        return (bool) $statement->fetchColumn();
    }

    private function isOwner(int $bandId, int $userId): bool
    {
        $statement = $this->database()->prepare('SELECT 1 FROM bands WHERE id = :band_id AND owner_id = :user_id');
        $statement->execute(['band_id' => $bandId, 'user_id' => $userId]);
        return (bool) $statement->fetchColumn();
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
