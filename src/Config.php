<?php

declare(strict_types=1);

namespace App;

final class Config
{
    public string $appEnv;
    public string $appUrl;
    public string $appTimezone;

    public string $telegramToken;
    public ?string $telegramWebhookSecret;
    /** @var int[] */
    public array $adminIds;

    public string $dbHost;
    public int $dbPort;
    public string $dbName;
    public string $dbUser;
    public string $dbPass;
    public string $dbCharset;

    public string $storeCurrency;
    public string $storeCurrencySymbol;

    public string $cryptomusMerchantUuid;
    public string $cryptomusPaymentApiKey;
    public ?string $cryptomusToCurrency;
    public ?string $cryptomusNetwork;
    public string $cryptomusPaymentWebhookUrl;
    /** @var string[] */
    public array $cryptomusAllowedIps;
    public ?string $storeSuccessUrl;
    public ?string $storeReturnUrl;

    public string $keystoreBaseUrl;
    public string $keystoreApiKey;

    public int $workerSleepSeconds;
    public int $workerMaxJobsPerRun;
    public int $workerRetryDelaySeconds;

    public string $adminDashboardUsername;
    public string $adminDashboardPasswordHash;
    public string $adminDashboardSessionName;

    public function __construct()
    {
        $this->appEnv = getenv('APP_ENV') ?: 'production';
        $this->appUrl = $this->requireEnv('APP_URL');
        $this->appTimezone = getenv('APP_TIMEZONE') ?: 'UTC';

        $this->telegramToken = $this->requireEnv('TELEGRAM_BOT_TOKEN');
        $this->telegramWebhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
        $this->adminIds = array_values(array_filter(array_map(
            static fn(string $id): int => (int) trim($id),
            explode(',', getenv('ADMIN_IDS') ?: '')
        )));

        $this->dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $this->dbPort = max(1, (int) (getenv('DB_PORT') ?: 3306));
        $this->dbName = $this->requireEnv('DB_NAME');
        $this->dbUser = $this->requireEnv('DB_USER');
        $this->dbPass = $this->requireEnv('DB_PASS');
        $this->dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

        $this->storeCurrency = getenv('STORE_CURRENCY') ?: 'USD';
        $this->storeCurrencySymbol = getenv('STORE_CURRENCY_SYMBOL') ?: '$';

        $this->cryptomusMerchantUuid = $this->requireEnv('CRYPTOMUS_MERCHANT_UUID');
        $this->cryptomusPaymentApiKey = $this->requireEnv('CRYPTOMUS_PAYMENT_API_KEY');
        $this->cryptomusToCurrency = getenv('CRYPTOMUS_TO_CURRENCY') ?: null;
        $this->cryptomusNetwork = getenv('CRYPTOMUS_NETWORK') ?: null;
        $this->cryptomusPaymentWebhookUrl = $this->requireEnv('CRYPTOMUS_PAYMENT_WEBHOOK_URL');
        $this->cryptomusAllowedIps = array_values(array_filter(array_map(
            static fn(string $ip): string => trim($ip),
            explode(',', getenv('CRYPTOMUS_ALLOWED_IPS') ?: '91.227.144.54')
        )));
        $this->storeSuccessUrl = getenv('STORE_SUCCESS_URL') ?: null;
        $this->storeReturnUrl = getenv('STORE_RETURN_URL') ?: null;

        $this->keystoreBaseUrl = $this->requireEnv('KEYSTORE_BASE_URL');
        $this->keystoreApiKey = $this->requireEnv('KEYSTORE_API_KEY');

        $this->workerSleepSeconds = max(1, (int) (getenv('WORKER_SLEEP_SECONDS') ?: 3));
        $this->workerMaxJobsPerRun = max(1, (int) (getenv('WORKER_MAX_JOBS_PER_RUN') ?: 30));
        $this->workerRetryDelaySeconds = max(5, (int) (getenv('WORKER_RETRY_DELAY_SECONDS') ?: 20));

        $this->adminDashboardUsername = $this->requireEnv('ADMIN_DASHBOARD_USERNAME');
        $this->adminDashboardPasswordHash = $this->requireEnv('ADMIN_DASHBOARD_PASSWORD_HASH');
        $this->adminDashboardSessionName = getenv('ADMIN_DASHBOARD_SESSION_NAME') ?: 'legal_shop_admin';
    }

    private function requireEnv(string $key): string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }

        return trim($value);
    }
}
