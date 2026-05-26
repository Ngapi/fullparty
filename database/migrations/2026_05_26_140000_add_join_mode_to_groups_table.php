<?php

use App\Models\Group;
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
            $table->string('join_mode', 32)
                ->default(Group::JOIN_MODE_INVITE_ONLY)
                ->after('group_type');

            $table->index('join_mode');
        });

        if (Schema::hasColumn('groups', 'is_public')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropColumn('is_public');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex(['join_mode']);
            $table->dropColumn('join_mode');
        });
    }
};
