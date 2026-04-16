# Monorepo layout

Этот репозиторий теперь используется как единая точка для трёх проектов:

- `telegram-keystore-shop/` — магазин товаров в Telegram
- `projects/tg-music-bot/` — бот для поиска и публикации музыки
- `projects/office-report-bot/` — бот для офисных отчётов

## Текущее состояние

Корень репозитория уже содержит файлы `telegram-keystore-shop`.
Чтобы не ломать рабочий PHP-проект, остальные два проекта добавлены в папку `projects/`.

## Структура

```text
.
├── MONOREPO.md
├── composer.json
├── public/
├── src/
├── deploy/
└── projects/
    ├── office-report-bot/
    └── tg-music-bot/
```

## Рекомендация по следующему шагу

Если понадобится полностью чистый monorepo-вид, можно отдельным коммитом перенести текущий корневой PHP-проект в `projects/telegram-keystore-shop/`.
Но этот шаг я не делал, чтобы не сломать существующий запуск.
