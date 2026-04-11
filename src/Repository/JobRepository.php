<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class JobRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function enqueueUnique(string $type, int $orderId, array $payload = [], int $maxAttempts = 5): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO jobs (type, order_id, payload_json, max_attempts, status, available_at, updated_at)
             VALUES (?, ?, CAST(? AS JSON), ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $type,
            $orderId,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $maxAttempts,
            'queued',
        ]);
    }

    public function claimNext(string $type): ?array
    {
        $this->pdo->beginTransaction();

        $select = $this->pdo->prepare(
            "SELECT * FROM jobs
             WHERE type = ?
               AND status IN ('queued', 'retry')
               AND available_at <= NOW()
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE"
        );
        $select->execute([$type]);
        $job = $select->fetch();

        if (!$job) {
            $this->pdo->commit();
            return null;
        }

        $update = $this->pdo->prepare(
            "UPDATE jobs
             SET status = 'processing',
                 attempts = attempts + 1,
                 locked_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?"
        );
        $update->execute([(int) $job['id']]);

        $this->pdo->commit();
        $job['attempts'] = (int) $job['attempts'] + 1;
        $job['status'] = 'processing';
        return $job;
    }

    public function markDone(int $jobId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE jobs
             SET status = 'done', locked_at = NULL, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$jobId]);
    }

    public function markRetryOrFailed(int $jobId, int $attempts, int $maxAttempts, string $error, int $retryDelaySeconds = 15): void
    {
        $nextStatus = $attempts >= $maxAttempts ? 'failed' : 'retry';
        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET status = ?,
                 last_error = ?,
                 available_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 locked_at = NULL,
                 updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$nextStatus, $error, $retryDelaySeconds, $jobId]);
    }

    public function failedJobs(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM jobs WHERE status = 'failed' ORDER BY updated_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function retryFailedByOrderId(string $type, int $orderId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE jobs
             SET status = 'queued', last_error = NULL, locked_at = NULL, available_at = NOW(), updated_at = NOW()
             WHERE type = ? AND order_id = ? AND status = 'failed'"
        );
        $stmt->execute([$type, $orderId]);
        return $stmt->rowCount() > 0;
    }

    public function retryFailedByJobId(int $jobId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE jobs
             SET status = 'queued', last_error = NULL, locked_at = NULL, available_at = NOW(), updated_at = NOW()
             WHERE id = ? AND status = 'failed'"
        );
        $stmt->execute([$jobId]);
        return $stmt->rowCount() > 0;
    }

    public function recent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jobs ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function stats(): array
    {
        $rows = $this->pdo->query('SELECT status, COUNT(*) AS cnt FROM jobs GROUP BY status')->fetchAll();
        $result = [
            'queued' => 0,
            'retry' => 0,
            'processing' => 0,
            'done' => 0,
            'failed' => 0,
        ];
        foreach ($rows as $row) {
            $result[(string) $row['status']] = (int) $row['cnt'];
        }
        return $result;
    }
}
