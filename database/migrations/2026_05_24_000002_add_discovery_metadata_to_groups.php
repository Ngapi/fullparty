<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->text('banner_image_url')->nullable()->after('profile_picture_url');
            $table->json('primary_focuses')->nullable()->after('group_type');
            $table->string('experience_expectation', 64)->nullable()->after('primary_focuses');
            $table->string('voice_expectation', 64)->nullable()->after('experience_expectation');
            $table->json('preferred_languages')->nullable()->after('voice_expectation');
            $table->json('tags')->nullable()->after('preferred_languages');
            $table->string('active_timezone')->nullable()->after('tags');
            $table->json('active_days')->nullable()->after('active_timezone');
            $table->string('active_start_time', 5)->nullable()->after('active_days');
            $table->string('active_end_time', 5)->nullable()->after('active_start_time');

            $table->index('experience_expectation');
            $table->index('voice_expectation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex(['experience_expectation']);
            $table->dropIndex(['voice_expectation']);

            $table->dropColumn([
                'banner_image_url',
                'primary_focuses',
                'experience_expectation',
                'voice_expectation',
                'preferred_languages',
                'tags',
                'active_timezone',
                'active_days',
                'active_start_time',
                'active_end_time',
            ]);
        });
    }
};
