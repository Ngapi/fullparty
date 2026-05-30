<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EVENT = 'discord.notification.delivery';

    public function up(): void
    {
        DB::table('integration_clients')
            ->where('type', 'discord_bot')
            ->orderBy('id')
            ->get(['id', 'allowed_events'])
            ->each(function (object $client): void {
                $allowedEvents = json_decode((string) $client->allowed_events, true);

                if (! is_array($allowedEvents)) {
                    $allowedEvents = [];
                }

                if (in_array(self::EVENT, $allowedEvents, true)) {
                    return;
                }

                $allowedEvents[] = self::EVENT;

                DB::table('integration_clients')
                    ->where('id', $client->id)
                    ->update([
                        'allowed_events' => json_encode(array_values(array_unique($allowedEvents))),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('integration_clients')
            ->where('type', 'discord_bot')
            ->orderBy('id')
            ->get(['id', 'allowed_events'])
            ->each(function (object $client): void {
                $allowedEvents = json_decode((string) $client->allowed_events, true);

                if (! is_array($allowedEvents)) {
                    return;
                }

                DB::table('integration_clients')
                    ->where('id', $client->id)
                    ->update([
                        'allowed_events' => json_encode(array_values(array_diff($allowedEvents, [self::EVENT]))),
                    ]);
            });
    }
};
