# chatgpt-telegram-bot

Отдельный Telegram-бот в формате «чат с ChatGPT». Пользователь пишет обычное сообщение в Telegram, бот отправляет его в OpenAI Responses API и возвращает ответ в этот же чат.

## Возможности

- общение с ChatGPT обычными Telegram-сообщениями;
- сохранение контекста диалога по `chat_id` через `previous_response_id`;
- команда `/new` или `/reset` для нового диалога;
- inline-кнопки «🧹 Новый диалог» и «ℹ️ Помощь»;
- запуск через webhook или long polling;
- хранение контекста в локальном JSON-файле без базы данных.

## Быстрый запуск

```bash
cd projects/chatgpt-telegram-bot
cp .env.example .env
composer install
```

Заполните `.env`:

```env
TELEGRAM_BOT_TOKEN=123456:telegram-token
OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-5.2
```

### Long polling для теста

```bash
php bin/poll.php
```

### Webhook для продакшена

1. Укажите публичный URL webhook в `.env`:

```env
APP_URL=https://example.com/chatgpt-bot/public/index.php
TELEGRAM_WEBHOOK_SECRET=long-random-secret
```

2. Установите webhook:

```bash
php bin/install-webhook.php
```

3. Настройте web server document root на папку `public/` или направьте запросы Telegram на `public/index.php`.

## Команды в Telegram

- `/start` — показать справку;
- `/help` — показать справку;
- `/new` — начать новый диалог;
- `/reset` — очистить контекст.

## Переменные окружения

| Переменная | Обязательная | Описание |
| --- | --- | --- |
| `TELEGRAM_BOT_TOKEN` | да | Токен бота из BotFather. |
| `OPENAI_API_KEY` | да | Ключ OpenAI API. |
| `OPENAI_MODEL` | нет | Модель Responses API. По умолчанию `gpt-5.2`. |
| `OPENAI_INSTRUCTIONS` | нет | Инструкции поведения ChatGPT. |
| `OPENAI_MAX_OUTPUT_TOKENS` | нет | Максимум выходных токенов. По умолчанию `1200`. |
| `APP_URL` | только webhook | Публичный URL `public/index.php`. |
| `TELEGRAM_WEBHOOK_SECRET` | нет | Secret token для защиты webhook. |
| `CONVERSATION_STORE` | нет | Путь к JSON-хранилищу контекста. По умолчанию `storage/conversations.json`. |

## Структура

```text
projects/chatgpt-telegram-bot/
├── bin/
│   ├── install-webhook.php
│   └── poll.php
├── public/
│   └── index.php
├── src/
│   ├── Bot.php
│   ├── Config.php
│   ├── ConversationStore.php
│   ├── Env.php
│   ├── Keyboard.php
│   ├── OpenAiClient.php
│   └── TelegramApi.php
├── storage/
├── .env.example
├── bootstrap.php
└── composer.json
```
