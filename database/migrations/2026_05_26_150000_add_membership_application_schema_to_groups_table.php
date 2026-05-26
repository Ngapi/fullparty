<?php

use App\Models\Group;
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
        Schema::table('groups', function (Blueprint $table) {
            $table->json('membership_application_schema')
                ->nullable()
                ->after('join_mode');
        });

        DB::table('groups')
            ->where('join_mode', Group::JOIN_MODE_APPLICATION)
            ->whereNull('membership_application_schema')
            ->update([
                'membership_application_schema' => json_encode([[
                    'id' => 'are_you_a_gamer',
                    'type' => 'toggle',
                    'name' => [
                        'en' => 'Are you a gamer?',
                    ],
                    'description' => [],
                    'required' => true,
                    'options' => [],
                ]], JSON_THROW_ON_ERROR),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('membership_application_schema');
        });
    }
};
