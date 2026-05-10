<?php

use App\Models\Activity;
use App\Models\ActivityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->string('draft_difficulty')->default(ActivityType::DIFFICULTY_NORMAL)->after('draft_description');
            $table->unsignedSmallInteger('draft_default_min_item_level')->nullable()->after('draft_difficulty');
        });

        Schema::table('activity_type_versions', function (Blueprint $table) {
            $table->string('difficulty')->default(ActivityType::DIFFICULTY_NORMAL)->after('description');
            $table->unsignedSmallInteger('default_min_item_level')->nullable()->after('difficulty');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('datacenter')->nullable()->after('duration_hours');
            $table->string('intensity')->default(Activity::INTENSITY_CASUAL)->after('datacenter');
            $table->unsignedSmallInteger('min_item_level')->nullable()->after('intensity');
            $table->boolean('beginner_friendly')->default(false)->after('min_item_level');
            $table->string('run_style')->default(Activity::RUN_STYLE_PROGRESSION)->after('beginner_friendly');

            $table->index(['datacenter', 'starts_at']);
            $table->index(['intensity', 'starts_at']);
            $table->index(['run_style', 'starts_at']);
        });

        $groupDatacenters = DB::table('groups')->pluck('datacenter', 'id');

        DB::table('activities')
            ->whereNull('datacenter')
            ->orderBy('id')
            ->chunkById(500, function ($activities) use ($groupDatacenters): void {
                foreach ($activities as $activity) {
                    $datacenter = $groupDatacenters->get($activity->group_id);

                    if ($datacenter === null) {
                        continue;
                    }

                    DB::table('activities')
                        ->where('id', $activity->id)
                        ->update(['datacenter' => $datacenter]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['datacenter', 'starts_at']);
            $table->dropIndex(['intensity', 'starts_at']);
            $table->dropIndex(['run_style', 'starts_at']);

            $table->dropColumn([
                'datacenter',
                'intensity',
                'min_item_level',
                'beginner_friendly',
                'run_style',
            ]);
        });

        Schema::table('activity_type_versions', function (Blueprint $table) {
            $table->dropColumn([
                'difficulty',
                'default_min_item_level',
            ]);
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn([
                'draft_difficulty',
                'draft_default_min_item_level',
            ]);
        });
    }
};
