<?php

use App\Models\GroupMembershipApplication;
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
        Schema::create('group_membership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default(GroupMembershipApplication::STATUS_PENDING);
            $table->json('answers');
            $table->json('form_snapshot');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "create unique index group_membership_applications_pending_unique on group_membership_applications (group_id, user_id) where status = 'pending'"
            );
        } else {
            Schema::table('group_membership_applications', function (Blueprint $table) {
                $table->index(['group_id', 'user_id', 'status'], 'group_membership_applications_group_user_status_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('group_membership_applications')) {
            Schema::drop('group_membership_applications');
        }
    }
};
