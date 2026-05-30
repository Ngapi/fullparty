<?php

namespace App\Jobs;

use App\Services\Integrations\IntegrationHealthcheckService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckIntegrationClientHealthJob implements ShouldQueue
{
    use Queueable;

    public function handle(IntegrationHealthcheckService $healthcheckService): void
    {
        $healthcheckService->checkActiveClients();
    }
}
