<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserOnboardingState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserOnboardingController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_step' => ['required', Rule::in(UserOnboardingState::STEPS)],
            'notification_preferences_reviewed' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user()->load('discordUserIntegration');
        $state = $this->stateFor($user);
        $targetStep = $validated['current_step'];
        $now = now();
        $notificationPreferencesReviewed = (bool) ($validated['notification_preferences_reviewed'] ?? false);

        $this->ensureStepCanBeReached($state, $targetStep);

        $discordWillBeResolved = $this->discordWillBeResolved($user, $state, $targetStep);

        if (
            in_array($targetStep, [UserOnboardingState::STEP_NOTIFICATIONS, UserOnboardingState::STEP_NEXT], true)
            && ! $discordWillBeResolved
        ) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_discord_required',
            ]);
        }

        $notificationsWillBeResolved = $state->notification_preferences_completed_at !== null
            || $notificationPreferencesReviewed;

        if ($targetStep === UserOnboardingState::STEP_NEXT && ! $notificationsWillBeResolved) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_notification_preferences_required',
            ]);
        }

        $state = DB::transaction(function () use (
            $state,
            $user,
            $targetStep,
            $notificationPreferencesReviewed,
            $now,
        ) {
            $updates = [
                'current_step' => $targetStep,
            ];

            if (
                $targetStep === UserOnboardingState::STEP_NOTIFICATIONS
                && $state->current_step === UserOnboardingState::STEP_DISCORD_WARNING
                && ! $this->hasDiscordUserIntegration($user)
            ) {
                $updates['discord_skipped_at'] = $state->discord_skipped_at ?? $now;
            }

            if ($notificationPreferencesReviewed) {
                $updates['notification_preferences_completed_at'] = $state->notification_preferences_completed_at ?? $now;

                if ($user->notification_preferences_reviewed_at === null) {
                    $user->forceFill([
                        'notification_preferences_reviewed_at' => $now,
                    ])->save();
                }
            }

            $state->update($updates);

            return $state->fresh();
        });

        return response()->json([
            'onboarding' => $state->toSharedPayload(),
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user()->load('discordUserIntegration');
        $state = $this->stateFor($user);

        if ($state->current_step !== UserOnboardingState::STEP_NEXT) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_final_step_required',
            ]);
        }

        if (! $this->hasDiscordUserIntegration($user) && $state->discord_skipped_at === null) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_discord_required',
            ]);
        }

        if ($state->notification_preferences_completed_at === null) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_notification_preferences_required',
            ]);
        }

        $state->update([
            'completed_at' => $state->completed_at ?? now(),
        ]);

        return response()->json([
            'onboarding' => $state->fresh()->toSharedPayload(),
        ]);
    }

    private function stateFor(User $user): UserOnboardingState
    {
        return $user->onboardingState()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'current_step' => UserOnboardingState::STEP_WELCOME,
        ]);
    }

    private function hasDiscordUserIntegration(User $user): bool
    {
        return $user->discordUserIntegration !== null;
    }

    private function discordWillBeResolved(User $user, UserOnboardingState $state, string $targetStep): bool
    {
        if ($this->hasDiscordUserIntegration($user) || $state->discord_skipped_at !== null) {
            return true;
        }

        return $targetStep === UserOnboardingState::STEP_NOTIFICATIONS
            && $state->current_step === UserOnboardingState::STEP_DISCORD_WARNING;
    }

    private function ensureStepCanBeReached(UserOnboardingState $state, string $targetStep): void
    {
        if ($targetStep === UserOnboardingState::STEP_DISCORD_WARNING
            && ! in_array($state->current_step, [
                UserOnboardingState::STEP_DISCORD,
                UserOnboardingState::STEP_DISCORD_WARNING,
            ], true)) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_step_sequence_required',
            ]);
        }

        if ($targetStep === UserOnboardingState::STEP_NOTIFICATIONS
            && ! in_array($state->current_step, [
                UserOnboardingState::STEP_DISCORD,
                UserOnboardingState::STEP_DISCORD_WARNING,
                UserOnboardingState::STEP_NOTIFICATIONS,
                UserOnboardingState::STEP_NEXT,
            ], true)) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_step_sequence_required',
            ]);
        }

        if ($targetStep === UserOnboardingState::STEP_NEXT
            && ! in_array($state->current_step, [
                UserOnboardingState::STEP_NOTIFICATIONS,
                UserOnboardingState::STEP_NEXT,
            ], true)) {
            throw ValidationException::withMessages([
                'current_step' => 'onboarding_step_sequence_required',
            ]);
        }
    }
}
