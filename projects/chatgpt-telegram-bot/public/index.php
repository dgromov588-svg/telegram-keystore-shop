<?php

declare(strict_types=1);

use ChatGptTelegramBot\Bot;
use ChatGptTelegramBot\Config;

$container = require dirname(__DIR__) . '/bootstrap.php';

/** @var Config $config */
$config = $container['config'];
/** @var Bot $bot */
$bot = $container['bot'];

if ($config->telegramWebhookSecret !== null) {
    $incomingSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    if ($incomingSecret !== $config->telegramWebhookSecret) {
        http_response_code(403);
        echo 'Forbidden';
        return;
    }
}

$raw = file_get_contents('php://input') ?: '{}';
$update = json_decode($raw, true);
if (!is_array($update)) {
    http_response_code(400);
    echo 'Bad JSON';
    return;
}

try {
    $bot->handleUpdate($update);
    echo 'OK';
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo 'Error';
}
