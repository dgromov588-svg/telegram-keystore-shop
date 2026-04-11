<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use Throwable;

final class OrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createFromCart(int $telegramUserId, array $cartRows): int
    {
        if ($cartRows === []) {
            throw new \InvalidArgumentException('Cart is empty');
        }

        $total = 0;
        foreach ($cartRows as $row) {
            $total += ((int) $row['price_cents']) * ((int) $row['quantity']);
        }

        $idempotenceId = sprintf('local-order-%d-%s', $telegramUserId, bin2hex(random_bytes(8)));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO orders (telegram_user_id, total_cents, status, idempotence_id) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$telegramUserId, $total, 'awaiting_payment', $idempotenceId]);
            $orderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, title, unit_price_cents, quantity, provider_product_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($cartRows as $row) {
                $itemStmt->execute([
                    $orderId,
                    (int) $row['product_id'],
                    (string) $row['title'],
                    (int) $row['price_cents'],
                    (int) $row['quantity'],
                    (int) $row['provider_product_id'],
                ]);
            }

            $this->pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function find(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIdempotenceId(string $idempotenceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE idempotence_id = ? LIMIT 1');
        $stmt->execute([$idempotenceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function items(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function recentByUser(int $telegramUserId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE telegram_user_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $telegramUserId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function attachPaymentInvoice(int $orderId, string $provider, array $invoice): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders
             SET payment_provider = ?, payment_invoice_uuid = ?, payment_url = ?, payment_status = ?, payment_payload_json = CAST(? AS JSON)
             WHERE id = ?'
        );
        $stmt->execute([
            $provider,
            (string) ($invoice['uuid'] ?? ''),
            (string) ($invoice['url'] ?? ''),
            (string) ($invoice['payment_status'] ?? ($invoice['status'] ?? 'check')),
            json_encode($invoice, JSON_UNESCAPED_UNICODE),
            $orderId,
        ]);
    }

    public function updatePaymentStatus(int $orderId, string $paymentStatus, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET payment_status = ?, payment_payload_json = CAST(? AS JSON) WHERE id = ?'
        );
        $stmt->execute([
            $paymentStatus,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $orderId,
        ]);
    }

    public function markPaid(int $orderId): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = ?, paid_at = NOW() WHERE id = ?');
        $stmt->execute(['paid', $orderId]);
    }

    public function markDeliveryQueued(int $orderId): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute(['delivery_queued', $orderId]);
    }

    public function markDelivering(int $orderId): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute(['delivering', $orderId]);
    }

    public function markProviderCreated(int $orderId, int $providerOrderId, string $providerStatus): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET provider_order_id = ?, provider_status = ? WHERE id = ?');
        $stmt->execute([$providerOrderId, $providerStatus, $orderId]);
    }

    public function updateProviderStatus(int $orderId, string $providerStatus): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET provider_status = ? WHERE id = ?');
        $stmt->execute([$providerStatus, $orderId]);
    }

    public function markDelivered(int $orderId, string $providerStatus, string $deliveryPayload): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET status = ?, provider_status = ?, delivery_payload = ?, delivered_at = NOW() WHERE id = ?'
        );
        $stmt->execute(['delivered', $providerStatus, $deliveryPayload, $orderId]);
    }

    public function countByStatus(): array
    {
        $rows = $this->pdo->query('SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['status']] = (int) $row['cnt'];
        }
        return $result;
    }
}
