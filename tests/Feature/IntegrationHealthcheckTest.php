<?php

use App\Jobs\CheckIntegrationClientHealthJob;
use App\Models\IntegrationClient;
use App\Models\IntegrationClientHealthCheck;
use App\Services\Integrations\IntegrationHealthcheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('records successful integration healthchecks with signed headers', function () {
    $client = IntegrationClient::factory()->create([
        'healthcheck_url' => 'https://discord-bot.fullparty.test/health',
        'webhook_signing_secret' => 'health-secret',
    ]);

    Http::fake([
        'https://discord-bot.fullparty.test/health' => Http::response(['ok' => true], 204),
    ]);

    app(CheckIntegrationClientHealthJob::class)->handle(app(IntegrationHealthcheckService::class));

    $check = IntegrationClientHealthCheck::query()->sole();

    expect($check->integration_client_id)->toBe($client->id)
        ->and($check->status)->toBe(IntegrationClientHealthCheck::STATUS_HEALTHY)
        ->and($check->response_status)->toBe(204)
        ->and($check->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($client->fresh()->last_healthcheck_ok_at)->not->toBeNull()
        ->and($client->fresh()->last_healthcheck_failed_at)->toBeNull();

    Http::assertSent(function (HttpRequest $request) {
        $timestamp = $request->header('X-FullParty-Timestamp')[0] ?? null;

        return $request->url() === 'https://discord-bot.fullparty.test/health'
            && $request->method() === 'GET'
            && ($request->header('X-FullParty-Event')[0] ?? null) === 'integration.healthcheck'
            && is_string($timestamp)
            && ($request->header('X-FullParty-Signature')[0] ?? null) === 'sha256='.hash_hmac('sha256', $timestamp.'.', 'health-secret');
    });
});

it('records degraded integration healthchecks without marking the client failed', function () {
    $client = IntegrationClient::factory()->create([
        'healthcheck_url' => 'https://discord-bot.fullparty.test/health',
    ]);

    Http::fake([
        'https://discord-bot.fullparty.test/health' => Http::response([
            'ok' => true,
            'status' => 'degraded',
            'checks' => [
                'discord' => [
                    'ok' => true,
                    'status' => 'healthy',
                    'ready' => true,
                ],
                'recent_failures' => [
                    'ok' => false,
                    'status' => 'degraded',
                    'warnCount' => 1,
                    'errorCount' => 0,
                    'ignoredCount' => 3,
                    'lastFailureAt' => '2026-05-30T14:52:00.000Z',
                ],
            ],
        ], 200),
    ]);

    app(CheckIntegrationClientHealthJob::class)->handle(app(IntegrationHealthcheckService::class));

    $check = IntegrationClientHealthCheck::query()->sole();
    $client->refresh();

    expect($check->status)->toBe(IntegrationClientHealthCheck::STATUS_DEGRADED)
        ->and($check->response_status)->toBe(200)
        ->and($check->error)->toContain('Recent Failures')
        ->and($check->error)->toContain('ignored: 3')
        ->and($client->last_healthcheck_at)->not->toBeNull()
        ->and($client->last_healthcheck_failed_at)->toBeNull()
        ->and($client->last_healthcheck_error)->toContain('Recent Failures');
});

it('records unhealthy integration healthchecks in history and latest fields', function () {
    $client = IntegrationClient::factory()->create([
        'healthcheck_url' => 'https://discord-bot.fullparty.test/health',
    ]);

    Http::fake([
        'https://discord-bot.fullparty.test/health' => Http::response([
            'ok' => false,
            'status' => 'unhealthy',
            'checks' => [
                'discord' => [
                    'ok' => false,
                    'status' => 'unhealthy',
                    'ready' => false,
                ],
            ],
        ], 200),
    ]);

    app(CheckIntegrationClientHealthJob::class)->handle(app(IntegrationHealthcheckService::class));

    $check = IntegrationClientHealthCheck::query()->sole();
    $client->refresh();

    expect($check->status)->toBe(IntegrationClientHealthCheck::STATUS_UNHEALTHY)
        ->and($check->response_status)->toBe(200)
        ->and($check->error)->toContain('Discord')
        ->and($check->error)->toContain('not ready')
        ->and($client->last_healthcheck_at)->not->toBeNull()
        ->and($client->last_healthcheck_failed_at)->not->toBeNull()
        ->and($client->last_healthcheck_error)->not->toBeNull();
});

it('skips inactive integrations and integrations without a healthcheck url', function () {
    IntegrationClient::factory()->create([
        'status' => IntegrationClient::STATUS_PAUSED,
        'healthcheck_url' => 'https://discord-bot.fullparty.test/paused-health',
    ]);
    IntegrationClient::factory()->create([
        'healthcheck_url' => null,
    ]);

    Http::fake();

    app(CheckIntegrationClientHealthJob::class)->handle(app(IntegrationHealthcheckService::class));

    expect(IntegrationClientHealthCheck::query()->count())->toBe(0);

    Http::assertNothingSent();
});
