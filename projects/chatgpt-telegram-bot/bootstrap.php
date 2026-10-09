<?php

declare(strict_types=1);

use ChatGptTelegramBot\Bot;
use ChatGptTelegramBot\Config;
use ChatGptTelegramBot\ConversationStore;
use ChatGptTelegramBot\Env;
use ChatGptTelegramBot\OpenAiClient;
use ChatGptTelegramBot\TelegramApi;

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__ . '/.env');

$config = new Config(__DIR__);
$telegram = new TelegramApi($config->telegramToken);
$openAi = new OpenAiClient(
    apiKey: $config->openAiApiKey,
    model: $config->openAiModel,
    instructions: $config->openAiInstructions,
    maxOutputTokens: $config->openAiMaxOutputTokens,
);
$store = new ConversationStore($config->conversationStore);

return [
    'config' => $config,
    'telegram' => $telegram,
    'bot' => new Bot($telegram, $openAi, $store),
];
