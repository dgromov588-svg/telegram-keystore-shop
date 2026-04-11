<?php

declare(strict_types=1);

namespace App\Service;

final class CryptomusService
{
    private const API_BASE = 'https://api.cryptomus.com';

    /** @param string[] $allowedIps */
    public function __construct(
        private readonly string $merchantUuid,
        private readonly string $paymentApiKey,
        private readonly array $allowedIps = [],
    ) {
    }

    public function createInvoice(
        string $amount,
        string $currency,
        string $orderId,
        string $urlCallback,
        ?string $toCurrency = null,
        ?string $network = null,
        ?string $urlReturn = null,
        ?string $urlSuccess = null,
        ?string $additionalData = null,
    ): array {
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $orderId,
            'url_callback' => $urlCallback,
            'is_payment_multiple' => true,
            'lifetime' => 3600,
        ];

        if ($toCurrency !== null) {
            $payload['to_currency'] = $toCurrency;
        }
        if ($network !== null) {
            $payload['network'] = $network;
        }
        if ($urlReturn !== null) {
            $payload['url_return'] = $urlReturn;
        }
        if ($urlSuccess !== null) {
            $payload['url_success'] = $urlSuccess;
        }
        if ($additionalData !== null) {
            $payload['additional_data'] = $additionalData;
        }

        return $this->request('/v1/payment', $payload);
    }

    public function paymentInfo(?string $invoiceUuid = null, ?string $orderId = null): array
    {
        $payload = [];
        if ($invoiceUuid !== null) {
            $payload['uuid'] = $invoiceUuid;
        }
        if ($orderId !== null) {
            $payload['order_id'] = $orderId;
        }

        return $this->request('/v1/payment/info', $payload);
    }

    public function verifyWebhook(string $rawBody, ?string $remoteIp = null): array
    {
        if ($this->allowedIps !== [] && $remoteIp !== null && !in_array($remoteIp, $this->allowedIps, true)) {
            throw new \RuntimeException('Webhook IP is not allowed');
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid webhook JSON');
        }

        $incomingSign = (string) ($data['sign'] ?? '');
        if ($incomingSign === '') {
            throw new \RuntimeException('Missing webhook sign');
        }

        unset($data['sign']);
        $expected = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $this->paymentApiKey);
        if (!hash_equals($expected, $incomingSign)) {
            throw new \RuntimeException('Invalid webhook sign');
        }

        return $data;
    }

    private function request(string $path, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode payload');
        }

        $sign = md5(base64_encode($json) . $this->paymentApiKey);

        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'merchant: ' . $this->merchantUuid,
                'sign: ' . $sign,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new \RuntimeException('Cryptomus request failed: ' . curl_error($ch));
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            throw new \RuntimeException('Cryptomus API HTTP error: ' . $raw);
        }

        if ((int) ($decoded['state'] ?? 1) !== 0) {
            throw new \RuntimeException((string) ($decoded['message'] ?? 'Cryptomus error'));
        }

        return (array) ($decoded['result'] ?? []);
    }
}
