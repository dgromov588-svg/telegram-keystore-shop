#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChatGptTelegramBot\Bot;
use ChatGptTelegramBot\TelegramApi;

$container = require dirname(__DIR__) . '/bootstrap.php';

/** @var TelegramApi $telegram */
$telegram = $container['telegram'];
/** @var Bot $bot */
$bot = $container['bot'];

$telegram->deleteWebhook();
$offset = 0;
echo "Polling started. Press Ctrl+C to stop.\n";

while (true) {
    $updates = $telegram->getUpdates($offset, 30);
    foreach (($updates['result'] ?? []) as $update) {
        if (!is_array($update)) {
            continue;
        }

        $offset = max($offset, ((int) ($update['update_id'] ?? 0)) + 1);
        $bot->handleUpdate($update);
    }
}
