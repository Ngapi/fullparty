<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditSeverity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccountDeletionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function delete(User $user): void
    {
        $this->ensureUserCanDeleteAccount($user);

        $originalEmail = $user->email;

        DB::transaction(function () use ($user, $originalEmail): void {
            $this->auditLogger->log(
                action: 'user.account.deleted',
                severity: AuditSeverity::SEVERE_CHANGE,
                scopeType: AuditScope::USER,
                scopeId: $user->id,
                message: 'audit_log.events.user.account.deleted',
                actor: $user,
                subject: $user,
            );

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

            if (filled($originalEmail)) {
                DB::table('password_reset_tokens')
                    ->where('email', $originalEmail)
                    ->delete();
            }

            DB::table('group_bans')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('system_notification_broadcast_reads')
                ->where('user_id', $user->id)
                ->delete();

            $user->receivedGroupNotes()->delete();
            $user->groupMemberships()->delete();
            $user->followedGroups()->detach();
            $user->socialAccounts()->delete();
            $user->inAppNotifications()->delete();
            $user->notificationDeliveries()->delete();

            $user->forceFill([
                'name' => sprintf('Deleted User #%d', $user->id),
                'email' => sprintf('deleted-user-%d-%s@deleted.fullparty.local', $user->id, Str::lower(Str::random(12))),
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => null,
                'avatar_url' => null,
                'is_admin' => false,
                'public_profile' => false,
                'public_characters' => false,
                'application_notifications' => false,
                'run_and_reminder_notifications' => false,
                'group_update_notifications' => false,
                'assignment_notifications' => false,
                'account_character_notifications' => false,
                'system_notice_notifications' => false,
                'email_notifications' => false,
                'discord_notifications' => false,
                'remember_token' => null,
            ])->save();
        });
    }

    private function ensureUserCanDeleteAccount(User $user): void
    {
        if ($user->ownedGroups()->exists()) {
            throw ValidationException::withMessages([
                'error' => 'account_delete_group_owner',
            ]);
        }
    }
}
