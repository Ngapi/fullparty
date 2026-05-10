<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_slot_composition_hints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_slot_id')->constrained()->cascadeOnDelete();
            $table->string('hint_type');
            $table->string('hint_key');
            $table->string('role_key')->nullable();
            $table->foreignId('character_class_id')->nullable()->constrained('character_classes')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['activity_slot_id', 'sort_order']);
            $table->index(['hint_type', 'hint_key']);
            $table->index('role_key');
            $table->index('character_class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_slot_composition_hints');
    }
};
