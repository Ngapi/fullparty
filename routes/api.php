<?php

use App\Http\Controllers\Api\IntegrationGuildController;
use App\Http\Controllers\Api\IntegrationRunController;
use App\Http\Controllers\Api\IntegrationUserController;
use App\Models\IntegrationClient;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations')
    ->group(function () {
        Route::middleware('integration.client:'.IntegrationClient::SCOPE_USERS_READ)
            ->post('/discord-users/primary-characters', [IntegrationUserController::class, 'primaryCharacters'])
            ->name('api.integrations.discord-users.primary-characters.index');

        Route::middleware('integration.client:'.IntegrationClient::SCOPE_USERS_WRITE)
            ->post('/discord-users/link', [IntegrationUserController::class, 'link'])
            ->name('api.integrations.discord-users.link');

        Route::middleware('integration.client:'.IntegrationClient::SCOPE_GUILDS_WRITE)
            ->post('/discord-guilds/link', [IntegrationGuildController::class, 'link'])
            ->name('api.integrations.discord-guilds.link');

        Route::middleware('integration.client:'.IntegrationClient::SCOPE_RUNS_READ)
            ->group(function () {
                Route::get('/discord-users/{discordUserId}/upcoming-runs', [IntegrationUserController::class, 'upcomingRuns'])
                    ->whereNumber('discordUserId')
                    ->name('api.integrations.discord-users.upcoming-runs.index');

                Route::get('/discord-users/{discordUserId}/applications', [IntegrationUserController::class, 'applications'])
                    ->whereNumber('discordUserId')
                    ->name('api.integrations.discord-users.applications.index');

                Route::get('/runs/{activity}', [IntegrationRunController::class, 'show'])
                    ->name('api.integrations.runs.show');
            });
    });
