<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Services\AiService;
use BandPilot\Support\Database;

final class AiController
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly AiService $aiService = new AiService()
    ) {
    }

    public function rehearsalPlan(int $rehearsalId): array
    {
        $database = Database::connection($this->projectRoot);
        $rehearsalStatement = $database->prepare(
            'SELECT id, band_id, title, start_time, duration_minutes, location, goals
             FROM rehearsals WHERE id = :id'
        );
        $rehearsalStatement->execute(['id' => $rehearsalId]);
        $rehearsal = $rehearsalStatement->fetch();

        if (!$rehearsal) {
            throw new \InvalidArgumentException('Rehearsal not found.');
        }

        $songStatement = $database->prepare(
            'SELECT songs.id, songs.title, songs.progress_level, songs.problem_notes
             FROM rehearsal_songs
             JOIN songs ON songs.id = rehearsal_songs.song_id
             WHERE rehearsal_songs.rehearsal_id = :rehearsal_id
             ORDER BY rehearsal_songs.order_number'
        );
        $songStatement->execute(['rehearsal_id' => $rehearsalId]);

        $questionnaireStatement = $database->prepare(
            'SELECT genres, experience_level, main_goal, rehearsal_frequency, session_minutes, main_challenge, notes
             FROM band_questionnaires WHERE band_id = :band_id'
        );
        $questionnaireStatement->execute(['band_id' => $rehearsal['band_id']]);
        $questionnaire = $questionnaireStatement->fetch() ?: null;

        $plan = $this->aiService->createRehearsalPlan([
            'rehearsal' => $rehearsal,
            'songs' => $songStatement->fetchAll(),
            'band_setup' => $questionnaire,
        ]);

        return [
            'status' => 'waiting_for_approval',
            'plan' => $plan,
        ];
    }
}
