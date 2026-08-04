<?php

declare(strict_types=1);

namespace BandPilot\Controllers;

use BandPilot\Support\Database;
use InvalidArgumentException;

final class AiResultController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function approve(int $bandId, array $input): array
    {
        $resultType = trim((string) ($input['result_type'] ?? 'rehearsal_plan'));
        $content = $input['content'] ?? null;

        if (!is_array($content) || $content === []) {
            throw new InvalidArgumentException('AI result content is required.');
        }

        $database = Database::connection($this->projectRoot);
        $statement = $database->prepare(
            'INSERT INTO ai_results (band_id, result_type, content, approved_at)
             VALUES (:band_id, :result_type, :content, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'band_id' => $bandId,
            'result_type' => $resultType,
            'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'result' => [
                'id' => (int) $database->lastInsertId(),
                'status' => 'approved',
                'result_type' => $resultType,
            ],
        ];
    }
}
