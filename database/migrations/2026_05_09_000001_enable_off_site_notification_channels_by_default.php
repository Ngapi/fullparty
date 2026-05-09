<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->setDefaults(true);
    }

    public function down(): void
    {
        $this->setDefaults(false);
    }

    private function setDefaults(bool $enabled): void
    {
        $default = $enabled ? 'true' : 'false';

        match (DB::getDriverName()) {
            'pgsql' => $this->setPostgresDefaults($default),
            'mysql', 'mariadb' => $this->setMySqlDefaults($enabled),
            default => null,
        };
    }

    private function setPostgresDefaults(string $default): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN email_notifications SET DEFAULT {$default}");
        DB::statement("ALTER TABLE users ALTER COLUMN discord_notifications SET DEFAULT {$default}");
    }

    private function setMySqlDefaults(bool $enabled): void
    {
        $default = $enabled ? 1 : 0;

        DB::statement("ALTER TABLE users ALTER email_notifications SET DEFAULT {$default}");
        DB::statement("ALTER TABLE users ALTER discord_notifications SET DEFAULT {$default}");
    }
};
