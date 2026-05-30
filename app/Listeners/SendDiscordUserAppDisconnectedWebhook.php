<?php

namespace App\Listeners;

use App\Events\DiscordUserAppDisconnected;
use App\Models\DiscordUserIntegration;
use App\Models\IntegrationClient;
use App\Services\Integrations\IntegrationWebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDiscordUserAppDisconnectedWebhook implements ShouldQueue
{
    public function __construct(
        private readonly IntegrationWebhookDispatcher $webhooks,
    ) {}

    public function handle(DiscordUserAppDisconnected $event): void
    {
        $integration = DiscordUserIntegration::query()
            ->with('user:id,name,email')
            ->find($event->discordUserIntegrationId);

        if (! $integration) {
            return;
        }

        $this->webhooks->dispatchDiscordBotEvent(IntegrationClient::EVENT_DISCORD_USER_APP_DISCONNECTED, [
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
            'message' => 'FullParty has disconnected the integration from your account. To fully remove the app from Discord, open Discord settings, go to Authorized Apps, and remove FullParty.',
            'disconnected_at' => now()->toIso8601String(),
        ]);
    }
}
