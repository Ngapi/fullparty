<?php

namespace App\Listeners;

use App\Events\DiscordUserAppInstalled;
use App\Models\DiscordUserIntegration;
use App\Services\Integrations\IntegrationWebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDiscordUserAppInstalledWebhook implements ShouldQueue
{
    public function __construct(
        private readonly IntegrationWebhookDispatcher $webhooks,
    ) {}

    public function handle(DiscordUserAppInstalled $event): void
    {
        $integration = DiscordUserIntegration::query()
            ->with('user:id,name,email')
            ->find($event->discordUserIntegrationId);

        if (! $integration) {
            return;
        }

        $this->webhooks->dispatchDiscordBotEvent('discord.user_app.installed', [
            'user' => [
                'id' => $integration->user_id,
                'name' => $integration->user?->name,
            ],
            'discord_user' => [
                'id' => $integration->discord_user_id,
                'username' => $integration->username,
                'global_name' => $integration->global_name,
                'avatar_url' => $integration->avatar_url,
            ],
            'installed_at' => $integration->user_app_installed_at?->toIso8601String(),
        ]);
    }
}
