<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_onboarding_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_step')->default('welcome');
            $table->timestamp('discord_skipped_at')->nullable();
            $table->timestamp('notification_preferences_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_states');
    }
};
