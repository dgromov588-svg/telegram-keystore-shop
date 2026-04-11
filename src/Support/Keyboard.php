<?php

declare(strict_types=1);

namespace App\Support;

final class Keyboard
{
    public static function main(): array
    {
        return [
            'inline_keyboard' => [
                [['text' => '🛍 Каталог', 'callback_data' => 'catalog']],
                [['text' => '🧺 Корзина', 'callback_data' => 'cart']],
                [['text' => '📦 Мои заказы', 'callback_data' => 'orders']],
            ],
        ];
    }

    public static function catalog(array $products, string $symbol): array
    {
        $rows = [];
        foreach ($products as $product) {
            $rows[] = [[
                'text' => sprintf('%s — %s', $product['title'], self::money((int) $product['price_cents'], $symbol)),
                'callback_data' => 'product:' . $product['id'],
            ]];
        }
        $rows[] = [['text' => '⬅️ Назад', 'callback_data' => 'home']];
        return ['inline_keyboard' => $rows];
    }

    public static function product(int $productId): array
    {
        return [
            'inline_keyboard' => [
                [['text' => '➕ В корзину', 'callback_data' => 'add:' . $productId]],
                [['text' => '🧺 Корзина', 'callback_data' => 'cart']],
                [['text' => '⬅️ К каталогу', 'callback_data' => 'catalog']],
            ],
        ];
    }

    public static function cart(array $cart): array
    {
        $rows = [];
        foreach ($cart as $item) {
            $rows[] = [[
                'text' => '❌ Удалить ' . $item['title'],
                'callback_data' => 'remove:' . $item['product_id'],
            ]];
        }

        if ($cart !== []) {
            $rows[] = [['text' => '💳 Перейти к оплате', 'callback_data' => 'checkout']];
            $rows[] = [['text' => '🗑 Очистить корзину', 'callback_data' => 'clear_cart']];
        }

        $rows[] = [['text' => '⬅️ Назад', 'callback_data' => 'home']];
        return ['inline_keyboard' => $rows];
    }

    public static function pay(string $paymentUrl, int $orderId): array
    {
        return [
            'inline_keyboard' => [
                [['text' => '💸 Оплатить', 'url' => $paymentUrl]],
                [['text' => '🔄 Проверить оплату', 'callback_data' => 'checkpay:' . $orderId]],
                [['text' => '⬅️ В меню', 'callback_data' => 'home']],
            ],
        ];
    }

    public static function backToHome(): array
    {
        return ['inline_keyboard' => [[['text' => '⬅️ В меню', 'callback_data' => 'home']]]];
    }

    public static function money(int $cents, string $symbol): string
    {
        return $symbol . number_format($cents / 100, 2, '.', ' ');
    }
}
