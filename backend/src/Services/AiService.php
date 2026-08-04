<?php

declare(strict_types=1);

namespace BandPilot\Services;

use BandPilot\Support\Env;
use RuntimeException;

final class AiService
{
    public function createRehearsalPlan(array $context): array
    {
        $url = Env::get('LLM_API_URL');
        $key = Env::get('LLM_API_KEY');
        $model = Env::get('LLM_MODEL');

        if ($url === '' || $key === '' || $model === '') {
            throw new RuntimeException('The AI service has not been configured yet.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for the AI service.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You help student bands plan practical rehearsals. Return only JSON with summary, activities, and notes.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $rawResponse = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($rawResponse === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($error !== '' ? $error : 'The AI service returned an error.');
        }

        $response = json_decode($rawResponse, true);
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new RuntimeException('The AI response did not contain a plan.');
        }

        $plan = json_decode($content, true);
        if (!is_array($plan) || !isset($plan['summary'], $plan['activities'], $plan['notes'])) {
            throw new RuntimeException('The AI plan format was not valid.');
        }

        return $plan;
    }
}
