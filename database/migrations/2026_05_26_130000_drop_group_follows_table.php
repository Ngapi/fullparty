<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('group_memberships', 'notifications_enabled')) {
            Schema::table('group_memberships', function (Blueprint $table) {
                $table->boolean('notifications_enabled')
                    ->default(true)
                    ->after('joined_at');
            });
        }

        if (Schema::hasTable('group_follows') && Schema::hasColumn('group_follows', 'notifications_enabled')) {
            DB::table('group_follows')
                ->select(['id', 'group_id', 'user_id', 'notifications_enabled'])
                ->where('notifications_enabled', false)
                ->orderBy('id')
                ->chunkById(100, function ($follows): void {
                    foreach ($follows as $follow) {
                        DB::table('group_memberships')
                            ->where('group_id', $follow->group_id)
                            ->where('user_id', $follow->user_id)
                            ->update(['notifications_enabled' => false]);
                    }
                });
        }

        Schema::dropIfExists('group_follows');
    }

    public function down(): void
    {
        Schema::create('group_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        if (Schema::hasColumn('group_memberships', 'notifications_enabled')) {
            Schema::table('group_memberships', function (Blueprint $table) {
                $table->dropColumn('notifications_enabled');
            });
        }
    }
};
