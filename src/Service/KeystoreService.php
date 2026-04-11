<?php

declare(strict_types=1);

namespace App\Service;

use Keystore\Api\Client\KeystoreClientFactory;
use Keystore\Api\Params\OrderCreateParams;

final class KeystoreService
{
    private object $client;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->client = KeystoreClientFactory::create($baseUrl, $apiKey);
    }

    public function createProviderOrder(int $providerProductId, int $quantity, string $idempotenceId): object
    {
        $params = new OrderCreateParams($providerProductId, $quantity);
        $params->setIdempotenceId($idempotenceId);
        return $this->client->orderCreate($params);
    }

    public function orderStatus(int $providerOrderId): object
    {
        return $this->client->orderStatus($providerOrderId);
    }

    public function orderDownload(int $providerOrderId): mixed
    {
        return $this->client->orderDownload($providerOrderId);
    }
}
