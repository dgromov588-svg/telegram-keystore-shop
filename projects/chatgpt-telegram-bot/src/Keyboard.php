<?php

declare(strict_types=1);

namespace ChatGptTelegramBot;

final class Keyboard
{
    public static function main(): array
    {
        return [
            'inline_keyboard' => [
                [['text' => '🧹 Новый диалог', 'callback_data' => 'reset']],
                [['text' => 'ℹ️ Помощь', 'callback_data' => 'help']],
            ],
        ];
    }
}
