<?php

declare(strict_types=1);

namespace ChatGptTelegramBot;

final class Config
{
    public string $telegramToken;
    public ?string $telegramWebhookSecret;
    public ?string $appUrl;
    public string $openAiApiKey;
    public string $openAiModel;
    public string $openAiInstructions;
    public int $openAiMaxOutputTokens;
    public string $conversationStore;

    public function __construct(string $basePath)
    {
        $this->telegramToken = $this->requireEnv('TELEGRAM_BOT_TOKEN');
        $this->telegramWebhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
        $this->appUrl = getenv('APP_URL') ?: null;
        $this->openAiApiKey = $this->requireEnv('OPENAI_API_KEY');
        $this->openAiModel = getenv('OPENAI_MODEL') ?: 'gpt-5.2';
        $this->openAiInstructions = getenv('OPENAI_INSTRUCTIONS') ?: 'Ты ChatGPT в Telegram. Отвечай на русском языке понятно, полезно и дружелюбно. Если вопрос опасный или незаконный — откажись и предложи безопасную альтернативу.';
        $this->openAiMaxOutputTokens = max(64, (int) (getenv('OPENAI_MAX_OUTPUT_TOKENS') ?: 1200));
        $this->conversationStore = $this->pathFromBase($basePath, getenv('CONVERSATION_STORE') ?: 'storage/conversations.json');
    }

    private function requireEnv(string $key): string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }

        return trim($value);
    }

    private function pathFromBase(string $basePath, string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }
}
