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
        Schema::table('activity_types', function (Blueprint $table) {
            $table->json('draft_roster_summary_presets')->nullable()->after('draft_application_schema');
        });

        Schema::table('activity_type_versions', function (Blueprint $table) {
            $table->json('roster_summary_presets')->nullable()->after('application_schema');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_type_versions', function (Blueprint $table) {
            $table->dropColumn('roster_summary_presets');
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn('draft_roster_summary_presets');
        });
    }
};
