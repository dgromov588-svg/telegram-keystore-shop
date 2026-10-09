<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AiSessionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getLastResponseId(int $telegramUserId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT last_response_id FROM ai_sessions WHERE telegram_user_id = ?');
        $stmt->execute([$telegramUserId]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function rememberResponse(int $telegramUserId, string $responseId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_sessions (telegram_user_id, last_response_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE last_response_id = VALUES(last_response_id), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$telegramUserId, $responseId]);
    }

    public function reset(int $telegramUserId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ai_sessions WHERE telegram_user_id = ?');
        $stmt->execute([$telegramUserId]);
    }
}
