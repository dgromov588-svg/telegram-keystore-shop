#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChatGptTelegramBot\Config;
use ChatGptTelegramBot\TelegramApi;

$container = require dirname(__DIR__) . '/bootstrap.php';

/** @var Config $config */
$config = $container['config'];
/** @var TelegramApi $telegram */
$telegram = $container['telegram'];

if ($config->appUrl === null || $config->appUrl === '') {
    fwrite(STDERR, "APP_URL is required to install webhook.\n");
    exit(1);
}

$result = $telegram->setWebhook($config->appUrl, $config->telegramWebhookSecret);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
