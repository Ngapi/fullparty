<?php

namespace App\Services\Integrations;

use App\Models\IntegrationClient;
use App\Models\IntegrationClientHealthCheck;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IntegrationHealthcheckService
{
    public function checkActiveClients(): void
    {
        IntegrationClient::query()
            ->where('status', IntegrationClient::STATUS_ACTIVE)
            ->whereNotNull('healthcheck_url')
            ->get()
            ->each(fn (IntegrationClient $client) => $this->check($client));
    }

    public function check(IntegrationClient $client): void
    {
        if (blank($client->healthcheck_url)) {
            return;
        }

        $deliveryId = (string) Str::uuid();
        $timestamp = (string) now()->unix();
        $signature = filled($client->webhook_signing_secret)
            ? 'sha256='.hash_hmac('sha256', $timestamp.'.', (string) $client->webhook_signing_secret)
            : null;
        $headers = [
            'User-Agent' => 'FullParty-Integrations/1.0',
            'X-FullParty-Event' => 'integration.healthcheck',
            'X-FullParty-Delivery' => $deliveryId,
            'X-FullParty-Timestamp' => $timestamp,
        ];

        if ($signature !== null) {
            $headers['X-FullParty-Signature'] = $signature;
        }

        $checkedAt = now();
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(5)
                ->retry(1, 250)
                ->withHeaders($headers)
                ->get((string) $client->healthcheck_url)
                ->throw();

            $this->recordResult(
                client: $client,
                checkedAt: $checkedAt,
                status: IntegrationClientHealthCheck::STATUS_OK,
                responseStatus: $response->status(),
                durationMs: $this->durationMs($startedAt),
            );
        } catch (Throwable $exception) {
            $this->recordResult(
                client: $client,
                checkedAt: $checkedAt,
                status: IntegrationClientHealthCheck::STATUS_FAILED,
                responseStatus: $exception instanceof RequestException ? $exception->response->status() : null,
                durationMs: $this->durationMs($startedAt),
                error: $exception->getMessage(),
            );
        }
    }

    private function recordResult(
        IntegrationClient $client,
        CarbonInterface $checkedAt,
        string $status,
        ?int $responseStatus = null,
        ?int $durationMs = null,
        ?string $error = null,
    ): void {
        $trimmedError = $error === null ? null : Str::limit($error, 500, '...');

        $client->healthChecks()->create([
            'status' => $status,
            'checked_at' => $checkedAt,
            'response_status' => $responseStatus,
            'duration_ms' => $durationMs,
            'error' => $trimmedError,
        ]);

        $client->forceFill([
            'last_healthcheck_at' => $checkedAt,
            'last_healthcheck_ok_at' => $status === IntegrationClientHealthCheck::STATUS_OK ? $checkedAt : $client->last_healthcheck_ok_at,
            'last_healthcheck_failed_at' => $status === IntegrationClientHealthCheck::STATUS_FAILED ? $checkedAt : null,
            'last_healthcheck_error' => $status === IntegrationClientHealthCheck::STATUS_FAILED ? $trimmedError : null,
        ])->save();
    }

    private function durationMs(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
