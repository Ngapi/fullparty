<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->text('draft_small_image_url')->nullable()->after('draft_description');
            $table->text('draft_banner_image_url')->nullable()->after('draft_small_image_url');
        });

        Schema::table('activity_type_versions', function (Blueprint $table) {
            $table->text('small_image_url')->nullable()->after('description');
            $table->text('banner_image_url')->nullable()->after('small_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('activity_type_versions', function (Blueprint $table) {
            $table->dropColumn([
                'small_image_url',
                'banner_image_url',
            ]);
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn([
                'draft_small_image_url',
                'draft_banner_image_url',
            ]);
        });
    }
};
