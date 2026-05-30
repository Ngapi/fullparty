<?php

namespace App\Events;

use App\Models\DiscordUserIntegration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscordUserAppInstalled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $discordUserIntegrationId,
    ) {}

    public static function forIntegration(DiscordUserIntegration $integration): self
    {
        return new self($integration->id);
    }
}
