<?php

declare(strict_types=1);

namespace ChatGptTelegramBot;

final class Bot
{
    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly OpenAiClient $openAi,
        private readonly ConversationStore $store,
    ) {
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['message']) && is_array($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text = trim((string) ($message['text'] ?? ''));
        if ($chatId === 0) {
            return;
        }

        if ($text === '/start' || $text === '/help') {
            $this->telegram->sendMessage($chatId, $this->helpText(), Keyboard::main());
            return;
        }

        if ($text === '/new' || $text === '/reset') {
            $this->store->reset($chatId);
            $this->telegram->sendMessage($chatId, 'Готово. Я начал новый диалог — напишите следующее сообщение.', Keyboard::main());
            return;
        }

        if ($text === '') {
            $this->telegram->sendMessage($chatId, 'Пока я умею отвечать только на текстовые сообщения.', Keyboard::main());
            return;
        }

        $this->answerWithChatGpt($chatId, $text);
    }

    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $message = $callback['message'] ?? [];
        $chatId = (int) ($message['chat']['id'] ?? 0);

        if ($data === 'reset') {
            $this->store->reset($chatId);
            $this->telegram->sendMessage($chatId, 'Готово. Контекст очищен, можно начать новый диалог.', Keyboard::main());
            $this->answerCallback($callbackId, 'Диалог очищен');
            return;
        }

        if ($data === 'help') {
            $this->telegram->sendMessage($chatId, $this->helpText(), Keyboard::main());
            $this->answerCallback($callbackId);
            return;
        }

        $this->answerCallback($callbackId, 'Неизвестное действие');
    }

    private function answerWithChatGpt(int $chatId, string $text): void
    {
        $this->telegram->sendChatAction($chatId);

        try {
            $answer = $this->openAi->chat($text, $this->store->getLastResponseId($chatId));
            $this->store->remember($chatId, $answer['response_id']);
            foreach ($this->splitTelegramMessage($answer['text']) as $part) {
                $this->telegram->sendMessage($chatId, $part, Keyboard::main());
            }
        } catch (\Throwable $e) {
            error_log((string) $e);
            $this->telegram->sendMessage($chatId, 'Не получилось получить ответ от ChatGPT. Попробуйте ещё раз чуть позже.', Keyboard::main());
        }
    }

    private function answerCallback(string $callbackId, string $text = ''): void
    {
        if ($callbackId === '') {
            return;
        }

        $this->telegram->answerCallbackQuery($callbackId, $text);
    }

    /**
     * @return string[]
     */
    private function splitTelegramMessage(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ChatGPT вернул пустой ответ. Попробуйте переформулировать вопрос.'];
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return str_split($text, 3500);
        }

        $chunks = [];
        $chunk = '';
        foreach ($characters as $character) {
            if (strlen($chunk . $character) > 3500) {
                $chunks[] = $chunk;
                $chunk = '';
            }
            $chunk .= $character;
        }

        if ($chunk !== '') {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function helpText(): string
    {
        return "Привет! Я Telegram-бот для общения с ChatGPT.\n\n" 
            . "Просто отправьте мне текстовый вопрос, а я отвечу в этом чате.\n\n"
            . "Команды:\n/start — показать помощь\n/new — начать новый диалог\n/reset — очистить контекст";
    }
}
