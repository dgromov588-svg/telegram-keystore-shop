<?php

declare(strict_types=1);

namespace App\Service;

final class OpenAiService
{
    private const RESPONSES_URL = 'https://api.openai.com/v1/responses';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $instructions,
        private readonly int $maxOutputTokens,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * @return array{response_id: string, text: string}
     */
    public function ask(string $question, ?string $previousResponseId = null): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OpenAI API key is not configured');
        }

        $payload = [
            'model' => $this->model,
            'instructions' => $this->instructions,
            'input' => $question,
            'max_output_tokens' => $this->maxOutputTokens,
            'store' => true,
        ];

        if ($previousResponseId !== null && $previousResponseId !== '') {
            $payload['previous_response_id'] = $previousResponseId;
        }

        $response = $this->request($payload);
        $status = (string) ($response['status'] ?? '');
        if ($status !== '' && !in_array($status, ['completed', 'incomplete'], true)) {
            $message = is_array($response['error'] ?? null) ? (string) ($response['error']['message'] ?? $status) : $status;
            throw new \RuntimeException('OpenAI response status: ' . $message);
        }

        $responseId = (string) ($response['id'] ?? '');
        $text = $this->extractText($response);

        if ($responseId === '' || $text === '') {
            throw new \RuntimeException('OpenAI response did not contain text');
        }

        return [
            'response_id' => $responseId,
            'text' => $text,
        ];
    }

    private function request(array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $ch = curl_init(self::RESPONSES_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('OpenAI request failed: ' . $message);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            throw new \RuntimeException('OpenAI API error: ' . $raw);
        }

        return $decoded;
    }

    private function extractText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return trim($response['output_text']);
        }

        $parts = [];
        foreach (($response['output'] ?? []) as $outputItem) {
            if (!is_array($outputItem)) {
                continue;
            }

            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }

                $text = $contentItem['text'] ?? null;
                if (is_string($text) && $text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
