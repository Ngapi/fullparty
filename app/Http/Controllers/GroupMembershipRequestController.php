<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMembershipApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupMembershipRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $requests = GroupMembershipApplication::query()
            ->with([
                'group:id,name,slug,profile_picture_url,banner_image_url,datacenter,join_mode,is_visible',
                'group.memberships:id,group_id,user_id',
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (GroupMembershipApplication $application) => $this->serializeRequest($application, $user->id));

        return Inertia::render('Dashboard/Groups/MembershipRequests/Index', [
            'activeRequests' => $requests
                ->filter(fn (array $application) => $application['status'] === GroupMembershipApplication::STATUS_PENDING)
                ->values()
                ->all(),
            'historicalRequests' => $requests
                ->reject(fn (array $application) => $application['status'] === GroupMembershipApplication::STATUS_PENDING)
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRequest(GroupMembershipApplication $application, int $userId): array
    {
        $group = $application->group;
        $isMember = $group instanceof Group && $group->hasMember($userId);

        return [
            'id' => $application->id,
            'status' => $application->status,
            'answers' => $application->answers ?? [],
            'form_snapshot' => $application->form_snapshot ?? [],
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
            'review_reason' => $application->review_reason,
            'can_edit' => $application->status === GroupMembershipApplication::STATUS_PENDING,
            'group' => [
                'id' => $group?->id,
                'name' => $group?->name,
                'slug' => $group?->slug,
                'profile_picture_url' => $group?->profile_picture_url,
                'banner_image_url' => $group?->banner_image_url,
                'datacenter' => $group?->datacenter,
                'is_visible' => (bool) ($group?->is_visible ?? false),
            ],
            'urls' => [
                'edit' => $group ? route('groups.membership-applications.create', $group, false) : null,
                'dashboard' => $group && $isMember ? route('groups.dashboard', $group, false) : null,
            ],
        ];
    }
}
