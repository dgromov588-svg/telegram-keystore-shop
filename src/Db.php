<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Db
{
    private PDO $pdo;

    public function __construct(private readonly Config $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config->dbHost,
            $this->config->dbPort,
            $this->config->dbName,
            $this->config->dbCharset,
        );

        $this->pdo = new PDO($dsn, $this->config->dbUser, $this->config->dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $statements = [
            "CREATE TABLE IF NOT EXISTS users (
                telegram_user_id BIGINT PRIMARY KEY,
                username VARCHAR(255) NULL,
                full_name VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                price_cents INT NOT NULL,
                provider_product_id INT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_products_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS carts (
                telegram_user_id BIGINT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                PRIMARY KEY (telegram_user_id, product_id),
                INDEX idx_carts_user (telegram_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_user_id BIGINT NOT NULL,
                total_cents INT NOT NULL,
                status VARCHAR(50) NOT NULL,
                payment_provider VARCHAR(50) NULL,
                payment_invoice_uuid VARCHAR(255) NULL,
                payment_url TEXT NULL,
                payment_status VARCHAR(50) NULL,
                payment_payload_json JSON NULL,
                provider_order_id BIGINT NULL,
                provider_status VARCHAR(50) NULL,
                idempotence_id VARCHAR(255) NOT NULL,
                delivery_payload LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                delivered_at DATETIME NULL,
                UNIQUE KEY uniq_orders_idempotence (idempotence_id),
                INDEX idx_orders_user (telegram_user_id),
                INDEX idx_orders_status (status),
                INDEX idx_orders_payment_status (payment_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                unit_price_cents INT NOT NULL,
                quantity INT NOT NULL,
                provider_product_id INT NOT NULL,
                INDEX idx_order_items_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50) NOT NULL,
                order_id INT NOT NULL,
                payload_json JSON NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'queued',
                attempts INT NOT NULL DEFAULT 0,
                max_attempts INT NOT NULL DEFAULT 5,
                last_error TEXT NULL,
                available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                locked_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_jobs_type_order (type, order_id),
                INDEX idx_jobs_status_available (status, available_at),
                INDEX idx_jobs_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $sql) {
            $this->pdo->exec($sql);
        }
    }
}
