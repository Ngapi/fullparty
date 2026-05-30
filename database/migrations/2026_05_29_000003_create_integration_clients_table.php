<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            $table->text('outbound_events_url')->nullable();
            $table->text('healthcheck_url')->nullable();
            $table->text('webhook_signing_secret')->nullable();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->json('scopes')->nullable();
            $table->json('allowed_events')->nullable();
            $table->timestamp('last_event_sent_at')->nullable();
            $table->timestamp('last_event_failed_at')->nullable();
            $table->text('last_event_error')->nullable();
            $table->timestamp('last_healthcheck_at')->nullable();
            $table->timestamp('last_healthcheck_ok_at')->nullable();
            $table->timestamp('last_healthcheck_failed_at')->nullable();
            $table->text('last_healthcheck_error')->nullable();
            $table->timestamp('last_api_used_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('integration_client_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_client_id')->constrained('integration_clients')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('checked_at');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['integration_client_id', 'checked_at']);
            $table->index(['integration_client_id', 'status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_client_health_checks');
        Schema::dropIfExists('integration_clients');
    }
};
