<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivitySlot;
use App\Models\Group;
use App\Services\Groups\ActivityManagementRealtimeService;
use App\Services\Groups\ActivityPartyCompositionHintService;
use App\Services\Groups\ActivitySlotSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GroupActivitySlotGroupCompositionPresetController extends Controller
{
    public function store(
        Request $request,
        Group $group,
        Activity $activity,
        ActivityPartyCompositionHintService $compositionHintService,
        ActivitySlotSerializer $slotSerializer,
        ActivityManagementRealtimeService $activityManagementRealtimeService,
    ): JsonResponse {
        $this->assertCanManageActivity($group, $activity);

        $validated = $request->validate([
            'group_key' => ['required', 'string', 'max:255'],
            'composition_preset_key' => ['required', 'string', 'max:50'],
        ]);

        $slots = $compositionHintService->applyPreset(
            $activity,
            (string) $validated['group_key'],
            (string) $validated['composition_preset_key'],
        );

        return $this->respondWithUpdatedSlots(
            $activity,
            $slots,
            $slotSerializer,
            $activityManagementRealtimeService,
        );
    }

    public function applyToAll(
        Request $request,
        Group $group,
        Activity $activity,
        ActivityPartyCompositionHintService $compositionHintService,
        ActivitySlotSerializer $slotSerializer,
        ActivityManagementRealtimeService $activityManagementRealtimeService,
    ): JsonResponse {
        $this->assertCanManageActivity($group, $activity);

        $validated = $request->validate([
            'source_group_key' => ['required', 'string', 'max:255'],
        ]);

        $slots = $compositionHintService->copyCompositionHintsToCompatibleGroups(
            $activity,
            (string) $validated['source_group_key'],
        );

        return $this->respondWithUpdatedSlots(
            $activity,
            $slots,
            $slotSerializer,
            $activityManagementRealtimeService,
        );
    }

    private function assertCanManageActivity(Group $group, Activity $activity): void
    {
        $this->authorize('manageDashboard', [$activity, $group]);

        if ((int) $activity->group_id !== (int) $group->id) {
            abort(404);
        }

        if ($activity->isArchived()) {
            abort(403);
        }
    }

    /**
     * @param  Collection<int, ActivitySlot>  $slots
     */
    private function respondWithUpdatedSlots(
        Activity $activity,
        Collection $slots,
        ActivitySlotSerializer $slotSerializer,
        ActivityManagementRealtimeService $activityManagementRealtimeService,
    ): JsonResponse {
        $serializedSlots = $slots
            ->map(fn (ActivitySlot $slot) => $slotSerializer->serialize($slot))
            ->values()
            ->all();

        $activityManagementRealtimeService->broadcastPatch($activity, [
            'updated_slot_composition_hints' => $slots
                ->map(fn (ActivitySlot $slot) => [
                    'slot_id' => $slot->id,
                    'composition_hints' => $this->serializeCompositionHints($slot),
                ])
                ->values()
                ->all(),
        ]);

        return response()->json([
            'slots' => $serializedSlots,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeCompositionHints(ActivitySlot $slot): array
    {
        return $slot->compositionHints
            ->map(fn ($hint) => [
                'id' => $hint->id,
                'type' => $hint->hint_type,
                'key' => $hint->hint_key,
                'role_key' => $hint->role_key,
                'character_class_id' => $hint->character_class_id,
                'sort_order' => $hint->sort_order,
                'character_class' => $hint->characterClass ? [
                    'id' => $hint->characterClass->id,
                    'name' => $hint->characterClass->name,
                    'shorthand' => $hint->characterClass->shorthand,
                    'role' => $hint->characterClass->role,
                    'icon_url' => $hint->characterClass->icon_url,
                    'flaticon_url' => $hint->characterClass->flaticon_url,
                ] : null,
            ])
            ->values()
            ->all();
    }
}
