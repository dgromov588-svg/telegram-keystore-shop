<?php

declare(strict_types=1);

namespace App\Service;

use App\Api\TelegramApi;
use App\Repository\OrderRepository;

final class DeliveryService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly KeystoreService $keystore,
        private readonly TelegramApi $telegram,
    ) {
    }

    public function deliverOrder(int $orderId): void
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            throw new \RuntimeException('Order not found');
        }

        if ($order['status'] === 'delivered') {
            return;
        }

        $items = $this->orders->items($orderId);
        if ($items === []) {
            throw new \RuntimeException('Order items missing');
        }

        $this->orders->markDelivering($orderId);
        $deliveries = [];

        foreach ($items as $item) {
            $providerOrder = $this->keystore->createProviderOrder(
                (int) $item['provider_product_id'],
                (int) $item['quantity'],
                (string) $order['idempotence_id'] . '-item-' . $item['id']
            );

            $providerOrderId = $this->extractInt($providerOrder, ['getId', 'id']);
            $providerStatus = $this->extractString($providerOrder, ['getStatus', 'status'], 'in_process');
            $this->orders->markProviderCreated($orderId, $providerOrderId, $providerStatus);

            $payload = $this->pollAndDownload($providerOrderId, $orderId);
            $deliveries[] = '<b>' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>\n" . $payload;
        }

        $finalPayload = implode("\n\n", $deliveries);
        $this->orders->markDelivered($orderId, 'completed', $finalPayload);

        $this->telegram->sendMessage(
            (int) $order['telegram_user_id'],
            "<b>Заказ #{$orderId} готов</b>\n\n{$finalPayload}"
        );
    }

    private function pollAndDownload(int $providerOrderId, int $localOrderId): string
    {
        for ($i = 0; $i < 15; $i++) {
            $statusObj = $this->keystore->orderStatus($providerOrderId);
            $status = $this->extractString($statusObj, ['getStatus', 'status'], 'unknown');
            $this->orders->updateProviderStatus($localOrderId, $status);

            if ($status === 'completed') {
                return $this->normalizePayload($this->keystore->orderDownload($providerOrderId));
            }

            if (in_array($status, ['canceled', 'error', 'refund'], true)) {
                throw new \RuntimeException("Provider order failed: {$status}");
            }

            usleep(1500000);
        }

        throw new \RuntimeException('Provider order did not complete in time');
    }

    private function normalizePayload(mixed $download): string
    {
        if (is_string($download)) {
            return htmlspecialchars($download, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return htmlspecialchars((string) json_encode($download, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function extractInt(object $obj, array $keys): int
    {
        foreach ($keys as $key) {
            if (method_exists($obj, $key)) {
                return (int) $obj->{$key}();
            }
            if (isset($obj->{$key})) {
                return (int) $obj->{$key};
            }
        }
        throw new \RuntimeException('Unable to extract integer from provider response');
    }

    private function extractString(object $obj, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (method_exists($obj, $key)) {
                return (string) $obj->{$key}();
            }
            if (isset($obj->{$key})) {
                return (string) $obj->{$key};
            }
        }
        return $default;
    }
}
