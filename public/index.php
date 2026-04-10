<?php

declare(strict_types=1);

use App\BotApp;
use App\Config;
use App\Db;
use App\Api\TelegramApi;
use App\Repository\CartRepository;
use App\Repository\JobRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\CryptomusService;
use App\Service\KeystoreService;
use App\Support\HttpResponse;
use App\Support\SessionAuth;
use App\Web\AdminDashboardController;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Config();
date_default_timezone_set($config->appTimezone);

$db = new Db($config);
$pdo = $db->pdo();

$productRepository = new ProductRepository($pdo);
$productRepository->seedDemoIfEmpty();

$orders = new OrderRepository($pdo);
$jobs = new JobRepository($pdo);
$telegram = new TelegramApi($config->telegramToken);
$cryptomus = new CryptomusService(
    merchantUuid: $config->cryptomusMerchantUuid,
    paymentApiKey: $config->cryptomusPaymentApiKey,
    allowedIps: $config->cryptomusAllowedIps,
);
$keystore = new KeystoreService($config->keystoreBaseUrl, $config->keystoreApiKey);

$app = new BotApp(
    config: $config,
    telegram: $telegram,
    products: $productRepository,
    carts: new CartRepository($pdo),
    orders: $orders,
    jobs: $jobs,
    cryptomus: $cryptomus,
    keystore: $keystore,
    pdo: $pdo,
);

$sessionAuth = new SessionAuth(
    sessionName: $config->adminDashboardSessionName,
    username: $config->adminDashboardUsername,
    passwordHash: $config->adminDashboardPasswordHash,
    httpsOnly: str_starts_with($config->appUrl, 'https://'),
);

$admin = new AdminDashboardController(
    auth: $sessionAuth,
    orders: $orders,
    jobs: $jobs,
    config: $config,
);

$action = $_GET['action'] ?? null;

try {
    switch ($action) {
        case 'install-webhook':
            $app->installTelegramWebhook();
            break;

        case 'cryptomus-webhook':
            $app->handleCryptomusWebhook();
            break;

        case 'health':
            HttpResponse::json($app->health());
            break;

        case 'worker-status':
            HttpResponse::json($app->workerStatus());
            break;

        case 'admin-login':
            $admin->login();
            break;

        case 'admin-logout':
            $admin->logout();
            break;

        case 'admin-retry-order':
            $admin->retryOrder();
            break;

        case 'admin-retry-job':
            $admin->retryJob();
            break;

        case 'admin-dashboard':
            $admin->dashboard();
            break;

        default:
            $app->handleTelegramWebhook();
            break;
    }
} catch (Throwable $e) {
    error_log((string) $e);
    HttpResponse::json([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}
