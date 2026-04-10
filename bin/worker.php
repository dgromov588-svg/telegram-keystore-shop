<?php

declare(strict_types=1);

use App\Config;
use App\Db;
use App\Api\TelegramApi;
use App\Repository\JobRepository;
use App\Repository\OrderRepository;
use App\Service\DeliveryService;
use App\Service\KeystoreService;
use App\Worker\DeliveryWorker;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Config();
date_default_timezone_set($config->appTimezone);
$db = new Db($config);
$pdo = $db->pdo();

$worker = new DeliveryWorker(
    jobs: new JobRepository($pdo),
    delivery: new DeliveryService(
        orders: new OrderRepository($pdo),
        keystore: new KeystoreService($config->keystoreBaseUrl, $config->keystoreApiKey),
        telegram: new TelegramApi($config->telegramToken),
    ),
    sleepSeconds: $config->workerSleepSeconds,
    maxJobsPerRun: $config->workerMaxJobsPerRun,
    retryDelaySeconds: $config->workerRetryDelaySeconds,
);

$worker->run();
