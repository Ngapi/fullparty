<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('selected_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('applicant_lodestone_id')->nullable();
            $table->string('applicant_character_name')->nullable();
            $table->string('applicant_world')->nullable();
            $table->string('applicant_datacenter')->nullable();
            $table->string('applicant_avatar_url')->nullable();
            $table->string('guest_access_token', 64)->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamps();

            $table->unique('guest_access_token');
            $table->index(['activity_id', 'status']);
        });

        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement(
                "create unique index activity_applications_active_user_unique
                on activity_applications (activity_id, user_id)
                where user_id is not null and status <> 'withdrawn'"
            );

            DB::statement(
                "create unique index activity_applications_active_applicant_unique
                on activity_applications (activity_id, applicant_lodestone_id)
                where applicant_lodestone_id is not null and status <> 'withdrawn'"
            );

            return;
        }

        DB::statement(
            'create index activity_applications_active_user_unique on activity_applications (activity_id, user_id)'
        );
        DB::statement(
            'create index activity_applications_active_applicant_unique on activity_applications (activity_id, applicant_lodestone_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_applications');
    }
};
