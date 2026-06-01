<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_events', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('category');
            $table->unsignedBigInteger('group_id')->nullable()->after('is_mandatory');

            $table->index(['topic', 'created_at']);
            $table->index(['group_id', 'created_at']);
        });

        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('channel');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'topic', 'channel'], 'user_notification_preferences_unique');
            $table->index(['topic', 'channel']);
        });

        Schema::create('group_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('channel');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'group_id', 'topic', 'channel'], 'group_notification_preferences_unique');
            $table->index(['group_id', 'topic', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_notification_preferences');
        Schema::dropIfExists('user_notification_preferences');

        Schema::table('notification_events', function (Blueprint $table) {
            $table->dropIndex(['topic', 'created_at']);
            $table->dropIndex(['group_id', 'created_at']);
            $table->dropColumn(['topic', 'group_id']);
        });
    }
};
