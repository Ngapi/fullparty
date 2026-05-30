<?php

namespace App\Http\Controllers;

use App\Models\FeaturedGroup;
use App\Models\Group;
use App\Services\Groups\FeaturedGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FeaturedGroupController extends Controller
{
    public function __construct(
        private readonly FeaturedGroupService $featuredGroupService,
    ) {}

    public function index(): Response
    {
        $this->authorizeAdminAccess();

        return Inertia::render('Admin/FeaturedGroups', [
            'featuredGroups' => FeaturedGroup::query()
                ->with('group')
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get()
                ->map(fn (FeaturedGroup $featuredGroup): array => $this->serializeFeaturedGroup($featuredGroup))
                ->values(),
            'groupOptions' => $this->groupOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdminAccess();

        $validated = $this->validatedFeaturedGroupData($request);
        $group = Group::query()->findOrFail((int) $validated['group_id']);
        $this->ensureGroupIsFeatureable($group);

        FeaturedGroup::query()->create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->featuredGroupService->clearCache();

        return back()->with('success', 'featured_group_created');
    }

    public function update(Request $request, FeaturedGroup $featuredGroup): RedirectResponse
    {
        $this->authorizeAdminAccess();

        $validated = $this->validatedFeaturedGroupData($request, $featuredGroup);
        $group = Group::query()->findOrFail((int) $validated['group_id']);
        $this->ensureGroupIsFeatureable($group);

        $featuredGroup->update($validated);
        $this->featuredGroupService->clearCache();

        return back()->with('success', 'featured_group_updated');
    }

    public function destroy(FeaturedGroup $featuredGroup): RedirectResponse
    {
        $this->authorizeAdminAccess();

        $featuredGroup->delete();
        $this->featuredGroupService->clearCache();

        return back()->with('success', 'featured_group_deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFeaturedGroupData(Request $request, ?FeaturedGroup $featuredGroup = null): array
    {
        $uniqueGroupRule = Rule::unique('featured_groups', 'group_id');

        if ($featuredGroup) {
            $uniqueGroupRule->ignore($featuredGroup);
        }

        return $request->validate([
            'group_id' => [
                'required',
                'integer',
                Rule::exists('groups', 'id'),
                $uniqueGroupRule,
            ],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function ensureGroupIsFeatureable(Group $group): void
    {
        if ($this->featuredGroupService->isFeatureable($group)) {
            return;
        }

        throw ValidationException::withMessages([
            'group_id' => 'featured_group_not_eligible',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupOptions(): array
    {
        return $this->featuredGroupService
            ->eligibleGroupsQuery()
            ->select(['id', 'name', 'slug', 'datacenter'])
            ->orderBy('name')
            ->limit(250)
            ->get()
            ->map(fn (Group $group): array => [
                'label' => $group->name,
                'value' => $group->id,
                'description' => "{$group->slug} - {$group->datacenter}",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFeaturedGroup(FeaturedGroup $featuredGroup): array
    {
        return [
            'id' => $featuredGroup->id,
            'group_id' => $featuredGroup->group_id,
            'priority' => $featuredGroup->priority,
            'starts_at' => $featuredGroup->starts_at?->toIso8601String(),
            'ends_at' => $featuredGroup->ends_at?->toIso8601String(),
            'internal_note' => $featuredGroup->internal_note,
            'is_active' => $featuredGroup->starts_at?->isFuture() !== true
                && $featuredGroup->ends_at?->isPast() !== true,
            'group' => $featuredGroup->group ? [
                'id' => $featuredGroup->group->id,
                'name' => $featuredGroup->group->name,
                'slug' => $featuredGroup->group->slug,
                'datacenter' => $featuredGroup->group->datacenter,
                'is_visible' => $featuredGroup->group->is_visible,
                'banner_image_url' => $featuredGroup->group->banner_image_url,
            ] : null,
        ];
    }

    private function authorizeAdminAccess(): void
    {
        if (! auth()->user()?->is_admin) {
            abort(403);
        }
    }
}
