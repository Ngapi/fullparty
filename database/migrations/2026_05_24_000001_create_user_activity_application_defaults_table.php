<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_application_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selected_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->json('answers')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'activity_type_id'], 'user_activity_application_defaults_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_application_defaults');
    }
};
