<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_EVENT = 'discord.guild.run_reminder';

    private const SPLIT_EVENTS = [
        'discord.guild.run_starting_soon',
        'discord.guild.run_starting_now',
    ];

    public function up(): void
    {
        $this->transformAllowedEvents(
            shouldTransform: fn (array $events): bool => in_array(self::LEGACY_EVENT, $events, true),
            transform: fn (array $events): array => array_values(array_unique([
                ...array_filter($events, fn (string $event): bool => $event !== self::LEGACY_EVENT),
                ...self::SPLIT_EVENTS,
            ])),
        );
    }

    public function down(): void
    {
        $this->transformAllowedEvents(
            shouldTransform: fn (array $events): bool => count(array_intersect($events, self::SPLIT_EVENTS)) > 0,
            transform: fn (array $events): array => array_values(array_unique([
                ...array_filter($events, fn (string $event): bool => ! in_array($event, self::SPLIT_EVENTS, true)),
                self::LEGACY_EVENT,
            ])),
        );
    }

    /**
     * @param  callable(array<int, string>): bool  $shouldTransform
     * @param  callable(array<int, string>): array<int, string>  $transform
     */
    private function transformAllowedEvents(callable $shouldTransform, callable $transform): void
    {
        DB::table('integration_clients')
            ->select(['id', 'allowed_events'])
            ->orderBy('id')
            ->get()
            ->each(function (object $client) use ($shouldTransform, $transform): void {
                $events = $this->decodeAllowedEvents($client->allowed_events);

                if (! $shouldTransform($events)) {
                    return;
                }

                DB::table('integration_clients')
                    ->where('id', $client->id)
                    ->update([
                        'allowed_events' => json_encode($transform($events), JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    /**
     * @return array<int, string>
     */
    private function decodeAllowedEvents(mixed $events): array
    {
        if (is_array($events)) {
            return array_values(array_filter($events, 'is_string'));
        }

        if (! is_string($events) || $events === '') {
            return [];
        }

        $decoded = json_decode($events, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [];
    }
};
