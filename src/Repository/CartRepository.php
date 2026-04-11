<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CartRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(int $telegramUserId, int $productId, int $quantity = 1): void
    {
        $stmt = $this->pdo->prepare('SELECT quantity FROM carts WHERE telegram_user_id = ? AND product_id = ?');
        $stmt->execute([$telegramUserId, $productId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = $this->pdo->prepare('UPDATE carts SET quantity = quantity + ? WHERE telegram_user_id = ? AND product_id = ?');
            $upd->execute([$quantity, $telegramUserId, $productId]);
            return;
        }

        $ins = $this->pdo->prepare('INSERT INTO carts (telegram_user_id, product_id, quantity) VALUES (?, ?, ?)');
        $ins->execute([$telegramUserId, $productId, $quantity]);
    }

    public function remove(int $telegramUserId, int $productId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM carts WHERE telegram_user_id = ? AND product_id = ?');
        $stmt->execute([$telegramUserId, $productId]);
    }

    public function clear(int $telegramUserId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM carts WHERE telegram_user_id = ?');
        $stmt->execute([$telegramUserId]);
    }

    public function getDetailed(int $telegramUserId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.product_id, c.quantity, p.title, p.description, p.price_cents, p.provider_product_id
             FROM carts c
             JOIN products p ON p.id = c.product_id
             WHERE c.telegram_user_id = ?
             ORDER BY p.id ASC'
        );
        $stmt->execute([$telegramUserId]);
        return $stmt->fetchAll();
    }
}
