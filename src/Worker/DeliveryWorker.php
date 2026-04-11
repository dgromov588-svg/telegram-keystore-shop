<?php

declare(strict_types=1);

namespace App\Worker;

use App\Repository\JobRepository;
use App\Service\DeliveryService;

final class DeliveryWorker
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly DeliveryService $delivery,
        private readonly int $sleepSeconds = 3,
        private readonly int $maxJobsPerRun = 30,
        private readonly int $retryDelaySeconds = 20,
    ) {
    }

    public function run(): void
    {
        $processed = 0;

        while ($processed < $this->maxJobsPerRun) {
            $job = $this->jobs->claimNext('deliver_order');
            if ($job === null) {
                if ($processed === 0) {
                    sleep($this->sleepSeconds);
                }
                return;
            }

            try {
                $this->delivery->deliverOrder((int) $job['order_id']);
                $this->jobs->markDone((int) $job['id']);
            } catch (\Throwable $e) {
                $this->jobs->markRetryOrFailed(
                    (int) $job['id'],
                    (int) $job['attempts'],
                    (int) $job['max_attempts'],
                    $e->getMessage(),
                    $this->retryDelaySeconds
                );
            }

            $processed++;
        }
    }
}
