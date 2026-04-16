# office-report-bot

Монорепо-копия проекта офисных отчётов.

## Что внутри

- `bot.py` — основной Telegram-бот
- `webhook_hq10.py` — webhook entrypoint для shared hosting
- `passenger_wsgi.py` — entrypoint для cPanel / Passenger
- `requirements-hq10.txt` — зависимости под HOSTiQ / Python App

## Что не включено в эту папку

- `office_reports_template.xlsx` не переносился в эту папку через `create_file`, потому что это бинарный файл.
  Он остаётся доступен в исходном репозитории `office-report-bot`.

## Запуск

Локальный polling:

```bash
python bot.py
```

Webhook / cPanel:

- startup file: `passenger_wsgi.py`
- entry point: `application`
