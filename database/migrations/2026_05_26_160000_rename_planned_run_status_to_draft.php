<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activities')
            ->where('status', 'planned')
            ->update(['status' => 'draft']);

        DB::table('scheduled_runs')
            ->where('status', 'planned')
            ->update(['status' => 'draft']);
    }

    public function down(): void
    {
        DB::table('activities')
            ->where('status', 'draft')
            ->update(['status' => 'planned']);

        DB::table('scheduled_runs')
            ->where('status', 'draft')
            ->update(['status' => 'planned']);
    }
};
