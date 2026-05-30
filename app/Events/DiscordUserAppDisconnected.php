<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscordUserAppDisconnected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $discordUserIntegrationId,
    ) {}
}
