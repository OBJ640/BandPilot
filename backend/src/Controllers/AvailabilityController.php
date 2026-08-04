<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use InvalidArgumentException;
use PDO;

final class AvailabilityController
{
    private const STATUSES = ['available', 'unavailable', 'unsure'];

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function show(int $rehearsalId, int $userId): array
    {
        $rehearsal = $this->findRehearsal($rehearsalId);
        (new BandController($this->projectRoot))->assertMembership((int) $rehearsal['band_id'], $userId);
        $ownerId = $this->ownerId((int) $rehearsal['band_id']);
        $statement = $this->database()->prepare(
            "SELECT band_members.id AS member_id, band_members.display_name,
                    band_members.instrument AS band_role, band_members.user_id,
                    COALESCE(availability.status, 'unsure') AS status,
                    COALESCE(availability.note, '') AS note
             FROM band_members
             LEFT JOIN availability ON availability.member_id = band_members.id
               AND availability.rehearsal_id = :rehearsal_id
             WHERE band_members.band_id = :band_id
             ORDER BY band_members.role = 'owner' DESC, band_members.id ASC"
        );
        $statement->execute(['rehearsal_id' => $rehearsalId, 'band_id' => $rehearsal['band_id']]);
        $members = $statement->fetchAll();
        foreach ($members as &$member) {
            $member['can_edit'] = $userId === $ownerId || (int) ($member['user_id'] ?? 0) === $userId;
        }
        unset($member);
        return ['rehearsal' => $rehearsal, 'members' => $members, 'can_edit_all' => $userId === $ownerId];
    }

    public function save(int $rehearsalId, int $memberId, int $userId, array $input): array
    {
        $rehearsal = $this->findRehearsal($rehearsalId);
        (new BandController($this->projectRoot))->assertMembership((int) $rehearsal['band_id'], $userId);
        $memberStatement = $this->database()->prepare(
            'SELECT user_id FROM band_members WHERE id = :member_id AND band_id = :band_id'
        );
        $memberStatement->execute(['member_id' => $memberId, 'band_id' => $rehearsal['band_id']]);
        $memberUserId = $memberStatement->fetchColumn();
        if ($memberUserId === false) {
            throw new InvalidArgumentException('Band member not found.');
        }
        if ($userId !== $this->ownerId((int) $rehearsal['band_id']) && (int) $memberUserId !== $userId) {
            throw new InvalidArgumentException('You can only update your own availability.');
        }
        $status = (string) ($input['status'] ?? '');
        $note = trim((string) ($input['note'] ?? ''));
        if (!in_array($status, self::STATUSES, true) || mb_strlen($note) > 300) {
            throw new InvalidArgumentException('Availability choice or note is not valid.');
        }
        $statement = $this->database()->prepare(
            'INSERT INTO availability (rehearsal_id, member_id, status, note)
             VALUES (:rehearsal_id, :member_id, :status, :note)
             ON CONFLICT(rehearsal_id, member_id) DO UPDATE SET status = excluded.status, note = excluded.note'
        );
        $statement->execute([
            'rehearsal_id' => $rehearsalId,
            'member_id' => $memberId,
            'status' => $status,
            'note' => $note,
        ]);
        return $this->show($rehearsalId, $userId);
    }

    private function findRehearsal(int $rehearsalId): array
    {
        $statement = $this->database()->prepare(
            'SELECT id, band_id, title, start_time, status FROM rehearsals WHERE id = :id'
        );
        $statement->execute(['id' => $rehearsalId]);
        $rehearsal = $statement->fetch();
        if (!$rehearsal) {
            throw new InvalidArgumentException('Rehearsal not found.');
        }
        return $rehearsal;
    }

    private function ownerId(int $bandId): int
    {
        $statement = $this->database()->prepare('SELECT owner_id FROM bands WHERE id = :id');
        $statement->execute(['id' => $bandId]);
        return (int) $statement->fetchColumn();
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
