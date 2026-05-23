<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setDefaults(false);
    }

    public function down(): void
    {
        $this->setDefaults(true);
    }

    private function setDefaults(bool $enabled): void
    {
        $default = $enabled ? 'true' : 'false';

        match (DB::getDriverName()) {
            'pgsql' => $this->setPostgresDefaults($default),
            'mysql', 'mariadb' => $this->setMySqlDefaults($enabled),
            'sqlite' => $this->setSqliteDefaults($enabled),
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

    private function setSqliteDefaults(bool $enabled): void
    {
        Schema::table('users', function (Blueprint $table) use ($enabled): void {
            $table->boolean('email_notifications')->default($enabled)->change();
            $table->boolean('discord_notifications')->default($enabled)->change();
        });
    }
};
