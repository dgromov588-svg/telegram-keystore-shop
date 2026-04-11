<?php

declare(strict_types=1);

namespace App;

use PDO;
use App\Api\TelegramApi;
use App\Repository\CartRepository;
use App\Repository\JobRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\CryptomusService;
use App\Service\KeystoreService;
use App\Support\HttpResponse;
use App\Support\Keyboard;

final class BotApp
{
    public function __construct(
        private readonly Config $config,
        private readonly TelegramApi $telegram,
        private readonly ProductRepository $products,
        private readonly CartRepository $carts,
        private readonly OrderRepository $orders,
        private readonly JobRepository $jobs,
        private readonly CryptomusService $cryptomus,
        private readonly KeystoreService $keystore,
        private readonly PDO $pdo,
    ) {
    }

    public function installTelegramWebhook(): void
    {
        HttpResponse::json($this->telegram->setWebhook($this->config->appUrl, $this->config->telegramWebhookSecret));
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();
        return [
            'ok' => true,
            'env' => $this->config->appEnv,
            'db' => 'ok',
            'time' => gmdate('c'),
            'job_stats' => $this->jobs->stats(),
            'order_stats' => $this->orders->countByStatus(),
        ];
    }

    public function workerStatus(): array
    {
        return [
            'ok' => true,
            'job_stats' => $this->jobs->stats(),
            'failed_jobs' => $this->jobs->failedJobs(10),
        ];
    }

    public function handleTelegramWebhook(): void
    {
        if ($this->config->telegramWebhookSecret !== null) {
            $incomingSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
            if ($incomingSecret !== $this->config->telegramWebhookSecret) {
                http_response_code(403);
                echo 'Forbidden';
                return;
            }
        }

        $raw = file_get_contents('php://input');
        $update = json_decode($raw ?: '{}', true);
        if (!is_array($update)) {
            http_response_code(400);
            echo 'Bad JSON';
            return;
        }

        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
            }
            echo 'OK';
        } catch (\Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            echo 'Error';
        }
    }

    public function handleCryptomusWebhook(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $payload = $this->cryptomus->verifyWebhook($raw, $remoteIp);
            $idempotenceId = (string) ($payload['order_id'] ?? '');
            $status = (string) ($payload['status'] ?? '');
            if ($idempotenceId === '') {
                throw new \RuntimeException('Missing order_id');
            }

            $order = $this->orders->findByIdempotenceId($idempotenceId);
            if ($order === null) {
                throw new \RuntimeException('Local order not found for webhook');
            }

            $orderId = (int) $order['id'];
            $this->orders->updatePaymentStatus($orderId, $status, $payload);

            if (in_array($status, ['paid', 'paid_over'], true) && $order['status'] !== 'delivered') {
                $this->orders->markPaid($orderId);
                $this->enqueueDelivery($orderId);
                $this->telegram->sendMessage(
                    (int) $order['telegram_user_id'],
                    "<b>Оплата по заказу #{$orderId} подтверждена</b>\nВыдача поставлена в очередь."
                );
            }

            http_response_code(200);
            echo 'OK';
        } catch (\Throwable $e) {
            error_log((string) $e);
            http_response_code(400);
            echo 'ERROR';
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $user = $message['from'] ?? [];
        $telegramUserId = (int) ($user['id'] ?? 0);
        $text = trim((string) ($message['text'] ?? ''));

        $this->upsertUser(
            $telegramUserId,
            (string) ($user['username'] ?? ''),
            trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
        );

        if ($text === '/start') {
            $this->telegram->sendMessage($chatId, "Добро пожаловать в магазин цифровых товаров.\n\nВыбери действие:", Keyboard::main());
            return;
        }

        $this->telegram->sendMessage($chatId, 'Неизвестная команда. Используй /start');
    }

    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $message = $callback['message'] ?? [];
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $messageId = (int) ($message['message_id'] ?? 0);
        $from = $callback['from'] ?? [];
        $telegramUserId = (int) ($from['id'] ?? 0);

        $this->upsertUser(
            $telegramUserId,
            (string) ($from['username'] ?? ''),
            trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''))
        );

        if ($data === 'home') {
            $this->telegram->editMessageText($chatId, $messageId, 'Главное меню:', Keyboard::main());
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if ($data === 'catalog') {
            $this->telegram->editMessageText($chatId, $messageId, '<b>Каталог</b>', Keyboard::catalog($this->products->allActive(), $this->config->storeCurrencySymbol));
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if ($data === 'cart') {
            $cart = $this->carts->getDetailed($telegramUserId);
            $this->telegram->editMessageText($chatId, $messageId, $this->renderCart($cart), Keyboard::cart($cart));
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if ($data === 'orders') {
            $orders = $this->orders->recentByUser($telegramUserId);
            $this->telegram->editMessageText($chatId, $messageId, $this->renderOrders($orders), Keyboard::backToHome());
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if ($data === 'clear_cart') {
            $this->carts->clear($telegramUserId);
            $cart = $this->carts->getDetailed($telegramUserId);
            $this->telegram->editMessageText($chatId, $messageId, $this->renderCart($cart), Keyboard::cart($cart));
            $this->telegram->answerCallbackQuery($callbackId, 'Корзина очищена');
            return;
        }

        if ($data === 'checkout') {
            $cart = $this->carts->getDetailed($telegramUserId);
            if ($cart === []) {
                $this->telegram->answerCallbackQuery($callbackId, 'Корзина пуста', true);
                return;
            }

            $orderId = $this->orders->createFromCart($telegramUserId, $cart);
            $this->carts->clear($telegramUserId);
            $order = $this->orders->find($orderId);
            if ($order === null) {
                throw new \RuntimeException('Order not found after creation');
            }

            $invoice = $this->cryptomus->createInvoice(
                amount: $this->minorToDecimal((int) $order['total_cents']),
                currency: $this->config->storeCurrency,
                orderId: (string) $order['idempotence_id'],
                urlCallback: $this->config->cryptomusPaymentWebhookUrl,
                toCurrency: $this->config->cryptomusToCurrency,
                network: $this->config->cryptomusNetwork,
                urlReturn: $this->config->storeReturnUrl,
                urlSuccess: $this->config->storeSuccessUrl,
                additionalData: 'telegram_user:' . $telegramUserId,
            );

            $this->orders->attachPaymentInvoice($orderId, 'cryptomus', $invoice);
            $paymentUrl = (string) ($invoice['url'] ?? '');
            if ($paymentUrl === '') {
                throw new \RuntimeException('Cryptomus payment URL missing');
            }

            $this->telegram->editMessageText($chatId, $messageId, $this->renderPaymentOrder($orderId), Keyboard::pay($paymentUrl, $orderId));
            $this->telegram->answerCallbackQuery($callbackId, 'Счет создан');
            return;
        }

        if (str_starts_with($data, 'product:')) {
            $productId = (int) substr($data, strlen('product:'));
            $product = $this->products->findById($productId);
            if ($product === null) {
                $this->telegram->answerCallbackQuery($callbackId, 'Товар не найден', true);
                return;
            }
            $this->telegram->editMessageText($chatId, $messageId, $this->renderProduct($product), Keyboard::product($productId));
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if (str_starts_with($data, 'add:')) {
            $productId = (int) substr($data, strlen('add:'));
            $product = $this->products->findById($productId);
            if ($product === null) {
                $this->telegram->answerCallbackQuery($callbackId, 'Товар не найден', true);
                return;
            }
            $this->carts->add($telegramUserId, $productId, 1);
            $this->telegram->answerCallbackQuery($callbackId, 'Добавлено в корзину');
            return;
        }

        if (str_starts_with($data, 'remove:')) {
            $productId = (int) substr($data, strlen('remove:'));
            $this->carts->remove($telegramUserId, $productId);
            $cart = $this->carts->getDetailed($telegramUserId);
            $this->telegram->editMessageText($chatId, $messageId, $this->renderCart($cart), Keyboard::cart($cart));
            $this->telegram->answerCallbackQuery($callbackId, 'Удалено');
            return;
        }

        if (str_starts_with($data, 'checkpay:')) {
            $orderId = (int) substr($data, strlen('checkpay:'));
            $order = $this->orders->find($orderId);
            if ($order === null || (int) $order['telegram_user_id'] !== $telegramUserId) {
                $this->telegram->answerCallbackQuery($callbackId, 'Заказ не найден', true);
                return;
            }

            $invoiceUuid = (string) ($order['payment_invoice_uuid'] ?? '');
            $payment = $this->cryptomus->paymentInfo(
                invoiceUuid: $invoiceUuid !== '' ? $invoiceUuid : null,
                orderId: (string) $order['idempotence_id'],
            );
            $paymentStatus = (string) ($payment['payment_status'] ?? ($payment['status'] ?? 'unknown'));
            $this->orders->updatePaymentStatus($orderId, $paymentStatus, $payment);

            if (in_array($paymentStatus, ['paid', 'paid_over'], true) && $order['status'] !== 'delivered') {
                $this->orders->markPaid($orderId);
                $this->enqueueDelivery($orderId);
                $updated = $this->orders->find($orderId) ?: $order;
                $this->telegram->editMessageText($chatId, $messageId, $this->renderQueuedOrder($updated), Keyboard::backToHome());
                $this->telegram->answerCallbackQuery($callbackId, 'Оплата найдена, выдача в очереди');
                return;
            }

            $refreshedOrder = $this->orders->find($orderId) ?: $order;
            $this->telegram->editMessageText($chatId, $messageId, $this->renderPaymentOrder($orderId), Keyboard::pay((string) ($refreshedOrder['payment_url'] ?? ''), $orderId));
            $this->telegram->answerCallbackQuery($callbackId, 'Платеж еще не завершен');
            return;
        }

        $this->telegram->answerCallbackQuery($callbackId, 'Неизвестное действие', true);
    }

    private function enqueueDelivery(int $orderId): void
    {
        $this->jobs->enqueueUnique('deliver_order', $orderId, ['order_id' => $orderId]);
        $this->orders->markDeliveryQueued($orderId);
    }

    private function renderProduct(array $product): string
    {
        return '<b>' . htmlspecialchars($product['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>\n\n"
            . htmlspecialchars($product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n"
            . '💰 Цена: <b>' . Keyboard::money((int) $product['price_cents'], $this->config->storeCurrencySymbol) . '</b>';
    }

    private function renderCart(array $cart): string
    {
        if ($cart === []) {
            return '🧺 Корзина пуста.';
        }

        $lines = ['<b>Корзина</b>', ''];
        $total = 0;
        foreach ($cart as $item) {
            $sum = ((int) $item['price_cents']) * ((int) $item['quantity']);
            $total += $sum;
            $lines[] = '• ' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . ' × ' . (int) $item['quantity']
                . ' = ' . Keyboard::money($sum, $this->config->storeCurrencySymbol);
        }
        $lines[] = '';
        $lines[] = 'Итого: <b>' . Keyboard::money($total, $this->config->storeCurrencySymbol) . '</b>';
        return implode("\n", $lines);
    }

    private function renderOrders(array $orders): string
    {
        if ($orders === []) {
            return 'Заказов пока нет.';
        }

        $lines = ['<b>Последние заказы</b>', ''];
        foreach ($orders as $order) {
            $lines[] = '#'. $order['id']
                . ' — ' . htmlspecialchars((string) $order['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . ' — ' . Keyboard::money((int) $order['total_cents'], $this->config->storeCurrencySymbol)
                . ' — payment: ' . htmlspecialchars((string) ($order['payment_status'] ?? 'n/a'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return implode("\n", $lines);
    }

    private function renderPaymentOrder(int $orderId): string
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            return 'Заказ не найден.';
        }

        $items = $this->orders->items($orderId);
        $lines = [
            "<b>Заказ #{$orderId}</b>",
            'Статус: <b>' . htmlspecialchars((string) $order['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>',
            'Платеж: <b>' . htmlspecialchars((string) ($order['payment_status'] ?? 'check'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>',
            '',
            'Состав:',
        ];

        foreach ($items as $item) {
            $sum = ((int) $item['unit_price_cents']) * ((int) $item['quantity']);
            $lines[] = '• ' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . ' × ' . (int) $item['quantity']
                . ' = ' . Keyboard::money($sum, $this->config->storeCurrencySymbol);
        }

        $lines[] = '';
        $lines[] = 'Итого: <b>' . Keyboard::money((int) $order['total_cents'], $this->config->storeCurrencySymbol) . '</b>';
        $lines[] = '';
        $lines[] = 'Нажми «Оплатить», после подтверждения платежа заказ уйдет в очередь на выдачу.';
        return implode("\n", $lines);
    }

    private function renderQueuedOrder(array $order): string
    {
        return "<b>Заказ #" . (int) $order['id'] . "</b>\n"
            . 'Статус: <b>' . htmlspecialchars((string) ($order['status'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>\n'
            . 'Платеж: <b>' . htmlspecialchars((string) ($order['payment_status'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>\n\n'
            . 'Выдача поставлена в очередь. Бот пришлет товар отдельным сообщением.';
    }

    private function upsertUser(int $telegramUserId, string $username, string $fullName): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (telegram_user_id, username, full_name)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE username = VALUES(username), full_name = VALUES(full_name)'
        );
        $stmt->execute([$telegramUserId, $username, $fullName]);
    }

    private function minorToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
