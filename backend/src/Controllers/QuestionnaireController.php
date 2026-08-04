<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use BandPilot\Support\BandRole;
use InvalidArgumentException;
use PDO;

final class QuestionnaireController
{
    private const EXPERIENCE_LEVELS = ['beginner', 'mixed', 'intermediate', 'advanced'];
    private const MAIN_GOALS = ['casual', 'performance', 'recording', 'competition'];
    private const REHEARSAL_FREQUENCIES = ['weekly', 'twice_month', 'monthly', 'as_needed'];
    private const MAIN_CHALLENGES = ['availability', 'song_learning', 'timing', 'teamwork', 'performance_prep'];
    private const SESSION_MINUTES = [60, 90, 120, 180];

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function show(int $bandId, int $userId): array
    {
        (new BandController($this->projectRoot))->assertMembership($bandId, $userId);
        $database = $this->database();
        $statement = $database->prepare(
            'SELECT genres, experience_level, main_goal, rehearsal_frequency, session_minutes,
                    main_challenge, notes, completed_at, updated_at
             FROM band_questionnaires WHERE band_id = :band_id'
        );
        $statement->execute(['band_id' => $bandId]);
        $answers = $statement->fetch() ?: null;

        $memberStatement = $database->prepare(
            "SELECT COALESCE(instrument, '') FROM band_members WHERE band_id = :band_id AND user_id = :user_id"
        );
        $memberStatement->execute(['band_id' => $bandId, 'user_id' => $userId]);
        $instrument = (string) ($memberStatement->fetchColumn() ?: '');
        if (is_array($answers)) {
            $answers['instrument'] = $instrument;
        }

        return [
            'answers' => $answers,
            'completion_percent' => $answers === null ? 0 : 100,
            'completed' => $answers !== null,
        ];
    }

    public function save(int $bandId, int $userId, array $input): array
    {
        (new BandController($this->projectRoot))->assertOwner($bandId, $userId);
        $instrument = BandRole::validate($input['instrument'] ?? '');
        $genres = $this->validatedGenres((string) ($input['genres'] ?? ''));
        $experienceLevel = (string) ($input['experience_level'] ?? '');
        $mainGoal = (string) ($input['main_goal'] ?? '');
        $rehearsalFrequency = (string) ($input['rehearsal_frequency'] ?? '');
        $mainChallenge = (string) ($input['main_challenge'] ?? '');
        $sessionMinutes = filter_var($input['session_minutes'] ?? null, FILTER_VALIDATE_INT);
        $notes = trim((string) ($input['notes'] ?? ''));

        if (!in_array($experienceLevel, self::EXPERIENCE_LEVELS, true)
            || !in_array($mainGoal, self::MAIN_GOALS, true)
            || !in_array($rehearsalFrequency, self::REHEARSAL_FREQUENCIES, true)
            || !in_array($mainChallenge, self::MAIN_CHALLENGES, true)
            || $sessionMinutes === false
            || !in_array($sessionMinutes, self::SESSION_MINUTES, true)) {
            throw new InvalidArgumentException('Please complete every questionnaire choice.');
        }
        if (mb_strlen($notes) > 800) {
            throw new InvalidArgumentException('Questionnaire notes must be 800 characters or fewer.');
        }

        $database = $this->database();
        $database->beginTransaction();
        try {
            $memberStatement = $database->prepare(
                'UPDATE band_members SET instrument = :instrument WHERE band_id = :band_id AND user_id = :user_id'
            );
            $memberStatement->execute([
                'instrument' => $instrument,
                'band_id' => $bandId,
                'user_id' => $userId,
            ]);

            $statement = $database->prepare(
                'INSERT INTO band_questionnaires
                    (band_id, genres, experience_level, main_goal, rehearsal_frequency, session_minutes,
                     main_challenge, notes, updated_by, completed_at)
                 VALUES
                    (:band_id, :genres, :experience_level, :main_goal, :rehearsal_frequency, :session_minutes,
                     :main_challenge, :notes, :updated_by, CURRENT_TIMESTAMP)
                 ON CONFLICT(band_id) DO UPDATE SET
                    genres = excluded.genres,
                    experience_level = excluded.experience_level,
                    main_goal = excluded.main_goal,
                    rehearsal_frequency = excluded.rehearsal_frequency,
                    session_minutes = excluded.session_minutes,
                    main_challenge = excluded.main_challenge,
                    notes = excluded.notes,
                    updated_by = excluded.updated_by,
                    updated_at = CURRENT_TIMESTAMP,
                    completed_at = COALESCE(band_questionnaires.completed_at, CURRENT_TIMESTAMP)'
            );
            $statement->execute([
                'band_id' => $bandId,
                'genres' => $genres,
                'experience_level' => $experienceLevel,
                'main_goal' => $mainGoal,
                'rehearsal_frequency' => $rehearsalFrequency,
                'session_minutes' => $sessionMinutes,
                'main_challenge' => $mainChallenge,
                'notes' => $notes,
                'updated_by' => $userId,
            ]);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        return $this->show($bandId, $userId);
    }

    private function validatedGenres(string $value): string
    {
        $parts = preg_split('/[,，;]/u', trim($value)) ?: [];
        $genres = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
        if (count($genres) < 1 || count($genres) > 3) {
            throw new InvalidArgumentException('Please enter between one and three music genres.');
        }
        foreach ($genres as $genre) {
            if (mb_strlen($genre) > 50) {
                throw new InvalidArgumentException('Each music genre must be 50 characters or fewer.');
            }
        }
        return implode(', ', $genres);
    }

    private function database(): PDO
    {
        return Database::connection($this->projectRoot);
    }
}
