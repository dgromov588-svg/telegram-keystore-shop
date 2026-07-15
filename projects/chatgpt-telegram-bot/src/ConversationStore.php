<?php

declare(strict_types=1);

namespace ChatGptTelegramBot;

final class ConversationStore
{
    public function __construct(private readonly string $path)
    {
    }

    public function getLastResponseId(int $chatId): ?string
    {
        $data = $this->read();
        $value = $data[(string) $chatId]['last_response_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function remember(int $chatId, string $responseId): void
    {
        $data = $this->read();
        $data[(string) $chatId] = [
            'last_response_id' => $responseId,
            'updated_at' => gmdate('c'),
        ];
        $this->write($data);
    }

    public function reset(int $chatId): void
    {
        $data = $this->read();
        unset($data[(string) $chatId]);
        $this->write($data);
    }

    private function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function write(array $data): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create conversation storage directory');
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($this->path, $encoded . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Cannot write conversation storage');
        }
    }
}
