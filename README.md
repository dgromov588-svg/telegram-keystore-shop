# telegram-keystore-shop

Telegram bot for selling digital products with Cryptomus payments, Keystore delivery, an admin dashboard, and an optional OpenAI-powered AI assistant.

## AI assistant

The bot now answers normal text messages with an AI assistant, passes the active catalog into each AI request, and keeps conversation context per Telegram user through OpenAI Responses API response IDs.

### Telegram commands

- `/start` — open the main menu.
- `/ai` — show AI assistant help.
- `/resetai` — clear the user's AI conversation context.

The main menu also contains a **🤖 AI-помощник** button. Users can ask questions by sending regular text messages.

### Environment variables

Required for AI answers:

- `OPENAI_API_KEY` — OpenAI API key. If it is missing, the bot stays online and tells users that AI is not configured.

Optional AI settings:

- `OPENAI_MODEL` — model used by the Responses API. Default: `gpt-5.2`.
- `OPENAI_INSTRUCTIONS` — system instructions for the assistant.
- `OPENAI_MAX_OUTPUT_TOKENS` — response limit. Default: `800`, minimum: `64`.

## Webhook routes

- default route — Telegram webhook receiver.
- `?action=install-webhook` — install Telegram webhook.
- `?action=cryptomus-webhook` — Cryptomus webhook receiver.
- `?action=health` — application health.
- `?action=worker-status` — delivery worker status.
- `?action=admin-dashboard` — admin dashboard.
