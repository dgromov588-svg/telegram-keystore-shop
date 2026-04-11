<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function seedDemoIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO products (title, description, price_cents, provider_product_id) VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            'Demo product 1',
            'Digital product issued after successful payment.',
            1990,
            1,
        ]);

        $stmt->execute([
            'Demo product 2',
            'Second demo item for testing catalog, checkout, and delivery.',
            2990,
            2,
        ]);
    }

    public function allActive(): array
    {
        return $this->pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
