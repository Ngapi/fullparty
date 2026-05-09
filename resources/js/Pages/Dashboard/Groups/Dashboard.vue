<script setup lang="ts">
import type { GroupDashboardActivity, GroupDashboardGroup } from "@/Types/Groups";
import { computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";
import AccessBadge from "@/components/Groups/AccessBadge.vue";
import PageHeader from "@/components/PageHeader.vue";
import { localizedValue } from "@/utils/localizedValue";
import { getActivityStatusMeta } from "@/utils/activityStatusMeta";
import { canAcceptActivityApplications } from "@/utils/activityLifecycle";

const props = defineProps<{
	group: GroupDashboardGroup
}>();

const { t, locale } = useI18n();
const page = usePage();

const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"));
const leadershipCount = computed(() => props.group.member_role_breakdown.owner + props.group.member_role_breakdown.moderator);
const draftAndPlannedCount = computed(() => props.group.stats.draft_count + props.group.stats.planned_count);
const activeRunCount = computed(() => (
	props.group.stats.scheduled_count
	+ props.group.stats.assigned_count
	+ props.group.stats.upcoming_count
	+ props.group.stats.ongoing_count
));
const setupItems = computed(() => [
	{
		key: "description",
		label: t("groups.dashboard.setup.items.description"),
		icon: "i-lucide-text",
		configured: Boolean(props.group.description),
	},
	{
		key: "image",
		label: t("groups.dashboard.setup.items.profile_picture"),
		icon: "i-lucide-image",
		configured: Boolean(props.group.profile_picture_url),
	},
	{
		key: "discord",
		label: t("groups.dashboard.setup.items.discord"),
		icon: "i-lucide-messages-square",
		configured: Boolean(props.group.discord_invite_url),
	},
]);
const configuredSetupCount = computed(() => setupItems.value.filter((item) => item.configured).length);
const setupProgress = computed(() => (
	setupItems.value.length === 0
		? 0
		: Math.round((configuredSetupCount.value / setupItems.value.length) * 100)
));
const statusSegments = computed(() => {
	const total = props.group.stats.activity_count;

	return props.group.activity_status_breakdown
		.filter((item) => item.count > 0)
		.map((item) => ({
			...item,
			meta: getActivityStatusMeta(item.status),
			share: total === 0 ? 0 : Math.round((item.count / total) * 100),
		}));
});
const metricCards = computed(() => [
	{
		label: t("groups.dashboard.stats.members"),
		value: props.group.stats.member_count,
		hint: t("groups.dashboard.stats.members_hint", { count: leadershipCount.value }),
		icon: "i-lucide-users-round",
		iconColor: "text-sky-600 dark:text-sky-300",
		accentClass: "from-sky-500/20",
	},
	{
		label: t("groups.dashboard.stats.leadership"),
		value: leadershipCount.value,
		hint: t("groups.dashboard.stats.leadership_hint", { count: props.group.member_role_breakdown.owner }),
		icon: "i-lucide-shield-check",
		iconColor: "text-amber-600 dark:text-amber-300",
		accentClass: "from-amber-500/20",
	},
	{
		label: t("groups.dashboard.stats.draft_and_planned"),
		value: draftAndPlannedCount.value,
		hint: t("groups.dashboard.stats.draft_and_planned_hint", { count: props.group.stats.draft_count }),
		icon: "i-lucide-clipboard-list",
		iconColor: "text-zinc-700 dark:text-zinc-200",
		accentClass: "from-zinc-500/15",
	},
	{
		label: t("groups.dashboard.stats.active_runs"),
		value: activeRunCount.value,
		hint: t("groups.dashboard.stats.active_runs_hint", {
			count: props.group.stats.scheduled_count + props.group.stats.assigned_count,
		}),
		icon: "i-lucide-activity",
		iconColor: "text-emerald-600 dark:text-emerald-300",
		accentClass: "from-emerald-500/20",
	},
	{
		label: t("groups.dashboard.stats.open_applications"),
		value: props.group.stats.open_application_count,
		hint: t("groups.dashboard.stats.open_applications_hint", { count: props.group.stats.guest_friendly_count }),
		icon: "i-lucide-file-pen-line",
		iconColor: "text-brand dark:text-brand",
		accentClass: "from-brand/20",
	},
	{
		label: t("groups.dashboard.stats.completed_runs"),
		value: props.group.stats.completed_count,
		hint: t("groups.dashboard.stats.completed_runs_hint", { count: props.group.stats.cancelled_count }),
		icon: "i-lucide-flag",
		iconColor: "text-rose-700 dark:text-rose-300",
		accentClass: "from-rose-500/20",
	},
]);
const memberRoleItems = computed(() => [
	{
		key: "owner",
		label: t("groups.index.roles.owner"),
		count: props.group.member_role_breakdown.owner,
		color: "warning" as const,
	},
	{
		key: "moderator",
		label: t("groups.index.roles.moderator"),
		count: props.group.member_role_breakdown.moderator,
		color: "primary" as const,
	},
	{
		key: "member",
		label: t("groups.index.roles.member"),
		count: props.group.member_role_breakdown.member,
		color: "neutral" as const,
	},
]);

const goToActivitiesPage = () => {
	router.get(route("groups.dashboard.activities.index", props.group.slug));
};

const goToMembersPage = () => {
	router.get(route("groups.dashboard.members", props.group.slug));
};

const goToSettingsPage = () => {
	router.get(route("groups.dashboard.settings", props.group.slug));
};

const goToActivityBoard = (activityId: number) => {
	router.get(route("groups.dashboard.activities.show", {
		group: props.group.slug,
		activity: activityId,
	}));
};

const openDiscordInvite = () => {
	if (!props.group.discord_invite_url || typeof window === "undefined") {
		return;
	}

	window.open(props.group.discord_invite_url, "_blank", "noopener,noreferrer");
};

const formatRelativeTime = (value: string | null, fallback: string) => {
	if (!value) {
		return fallback;
	}

	const target = new Date(value).getTime();
	const now = Date.now();
	const diffMs = target - now;
	const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
		["day", 1000 * 60 * 60 * 24],
		["hour", 1000 * 60 * 60],
		["minute", 1000 * 60],
	];

	for (const [unit, threshold] of units) {
		if (Math.abs(diffMs) >= threshold) {
			return new Intl.RelativeTimeFormat(locale.value, { numeric: "auto" }).format(
				Math.round(diffMs / threshold),
				unit,
			);
		}
	}

	return t("groups.dashboard.labels.just_now");
};

const formatDateTime = (value: string | null, fallback: string) => {
	if (!value) {
		return fallback;
	}

	return new Intl.DateTimeFormat(locale.value, {
		weekday: "short",
		day: "numeric",
		month: "short",
		hour: "2-digit",
		minute: "2-digit",
	}).format(new Date(value));
};

const resolveActivityTypeName = (activity: GroupDashboardActivity) => (
	localizedValue(activity.activity_type.draft_name, locale.value, fallbackLocale.value)
	|| activity.activity_type.slug
	|| t("groups.activities.cards.unknown_type")
);

const resolveActivityTitle = (activity: GroupDashboardActivity) => (
	activity.title || resolveActivityTypeName(activity)
);

const resolveActivityOrganizer = (activity: GroupDashboardActivity) => (
	activity.organized_by_character?.name
	|| activity.organized_by?.name
	|| t("groups.activities.cards.no_organizer")
);
</script>

<template>
	<div class="w-full">
		<PageHeader
			:title="group.name"
			:subtitle="t('groups.dashboard.subtitle', { datacenter: group.datacenter })"
		>
			<div class="flex items-center justify-end gap-2">
				<UBadge
					size="md"
					:label="group.is_public
						? t('groups.dashboard.hero.public_group')
						: t('groups.dashboard.hero.private_group')"
					:icon="group.is_public ? 'i-lucide-globe-2' : 'i-lucide-lock'"
					:color="group.is_public ? 'primary' : 'neutral'"
					variant="subtle"
				/>
				<AccessBadge :role="group.current_user_role" compact />
			</div>
		</PageHeader>

		<div class="mt-4 flex flex-col gap-6">
			<UCard class="overflow-hidden border-default" :ui="{ body: 'sm:p-0 p-0 m-0' }">
				<div class="relative">
					<div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.12),transparent_48%),radial-gradient(circle_at_bottom_right,rgba(245,158,11,0.12),transparent_42%)]" />

					<div class="relative grid gap-6 p-6 xl:grid-cols-[1.2fr_0.8fr]">
						<div class="flex flex-col gap-6">
							<div class="flex flex-col gap-5 md:flex-row md:items-start">
								<div
									v-if="group.profile_picture_url"
									class="h-24 w-24 shrink-0 overflow-hidden border border-default bg-muted/20"
								>
									<img
										:src="group.profile_picture_url"
										:alt="group.name"
										class="h-full w-full object-cover"
									>
								</div>
								<div
									v-else
									class="flex h-24 w-24 shrink-0 items-center justify-center border border-default bg-muted/20"
								>
									<UIcon name="i-lucide-users-round" class="text-3xl text-muted" />
								</div>

								<div class="min-w-0 flex-1">
									<div class="flex flex-wrap items-center gap-2">
										<UBadge
											:label="group.is_visible
												? t('groups.dashboard.hero.visible_group')
												: t('groups.dashboard.hero.hidden_group')"
											:color="group.is_visible ? 'success' : 'neutral'"
											variant="subtle"
										/>
										<UBadge
											:label="group.datacenter"
											icon="i-lucide-map-pinned"
											color="neutral"
											variant="subtle"
										/>
										<UBadge
											:label="`/${group.slug}`"
											icon="i-lucide-at-sign"
											color="neutral"
											variant="outline"
										/>
									</div>

									<p class="mt-4 max-w-2xl text-sm leading-6 text-toned">
										{{ group.description || t("groups.dashboard.hero.no_description") }}
									</p>

									<div class="mt-5 flex flex-wrap gap-2">
										<UButton
											color="primary"
											icon="i-lucide-calendar-range"
											:label="t('groups.dashboard.actions.view_runs')"
											@click="goToActivitiesPage"
										/>
										<UButton
											color="neutral"
											variant="outline"
											icon="i-lucide-users"
											:label="t('groups.dashboard.actions.view_members')"
											@click="goToMembersPage"
										/>
										<UButton
											v-if="group.permissions.can_manage_members"
											color="neutral"
											variant="outline"
											icon="i-lucide-settings-2"
											:label="t('groups.dashboard.actions.open_settings')"
											@click="goToSettingsPage"
										/>
										<UButton
											v-if="group.discord_invite_url"
											color="neutral"
											variant="ghost"
											icon="i-lucide-message-circle-more"
											:label="t('groups.dashboard.actions.open_discord')"
											@click="openDiscordInvite"
										/>
									</div>
								</div>
							</div>
						</div>

						<div class="grid gap-px border border-default bg-muted/30 sm:grid-cols-2">
							<div class="bg-background px-4 py-4">
								<p class="text-xs uppercase tracking-[0.22em] text-muted">
									{{ t("groups.dashboard.hero.owner") }}
								</p>
								<div class="mt-3">
									<UUser
										:name="group.owner.name || t('groups.dashboard.labels.not_available')"
										:avatar="group.owner.avatar_url
											? {
												src: group.owner.avatar_url,
												alt: group.owner.name || t('groups.dashboard.labels.not_available'),
											}
											: undefined"
										:description="t('groups.index.roles.owner')"
										size="sm"
									/>
								</div>
							</div>

							<div class="bg-background px-4 py-4">
								<p class="text-xs uppercase tracking-[0.22em] text-muted">
									{{ t("groups.dashboard.hero.last_activity") }}
								</p>
								<p class="mt-3 text-base font-semibold text-toned">
									{{ formatRelativeTime(group.stats.last_activity_at, t("groups.dashboard.labels.no_recent_activity")) }}
								</p>
								<p class="mt-1 text-sm text-muted">
									{{ formatDateTime(group.stats.last_activity_at, t("groups.dashboard.labels.no_recent_activity")) }}
								</p>
							</div>

							<div class="bg-background px-4 py-4">
								<p class="text-xs uppercase tracking-[0.22em] text-muted">
									{{ t("groups.dashboard.hero.latest_member") }}
								</p>
								<p class="mt-3 text-base font-semibold text-toned">
									{{ formatRelativeTime(group.stats.latest_member_join_at, t("groups.dashboard.labels.no_member_joins")) }}
								</p>
								<p class="mt-1 text-sm text-muted">
									{{ formatDateTime(group.stats.latest_member_join_at, t("groups.dashboard.labels.no_member_joins")) }}
								</p>
							</div>

							<div class="bg-background px-4 py-4">
								<p class="text-xs uppercase tracking-[0.22em] text-muted">
									{{ t("groups.dashboard.hero.reach") }}
								</p>
								<p class="mt-3 text-base font-semibold text-toned">
									{{ t("groups.dashboard.hero.public_runs", { count: group.stats.public_activity_count }) }}
								</p>
								<p class="mt-1 text-sm text-muted">
									{{ t("groups.dashboard.hero.guest_friendly_runs", { count: group.stats.guest_friendly_count }) }}
								</p>
							</div>
						</div>
					</div>
				</div>
			</UCard>

			<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
				<div
					v-for="metric in metricCards"
					:key="metric.label"
					class="relative overflow-hidden border border-default bg-background px-4 py-4"
				>
					<div :class="['absolute inset-x-0 top-0 h-1 bg-gradient-to-r to-transparent', metric.accentClass]" />

					<div class="flex items-start justify-between gap-3">
						<div class="min-w-0">
							<p class="text-sm text-muted">{{ metric.label }}</p>
							<p class="mt-3 text-3xl font-semibold text-toned">{{ metric.value }}</p>
							<p class="mt-2 text-xs text-muted">{{ metric.hint }}</p>
						</div>

						<div class="flex h-11 w-11 shrink-0 items-center justify-center border border-default bg-muted/10">
							<UIcon :name="metric.icon" :class="['text-lg', metric.iconColor]" />
						</div>
					</div>
				</div>
			</section>

			<div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
				<UCard class="overflow-hidden border-default">
					<div class="flex items-start justify-between gap-4 border-b border-default px-5 py-4">
						<div>
							<p class="font-semibold text-md text-toned">{{ t("groups.dashboard.recent_runs.title") }}</p>
							<p class="mt-1 text-sm text-muted">{{ t("groups.dashboard.recent_runs.subtitle") }}</p>
						</div>

						<UButton
							color="neutral"
							variant="ghost"
							icon="i-lucide-arrow-right"
							:label="t('groups.dashboard.actions.view_runs')"
							@click="goToActivitiesPage"
						/>
					</div>

					<div v-if="group.recent_activities.length > 0" class="divide-y divide-default">
						<div
							v-for="activity in group.recent_activities"
							:key="activity.id"
							class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_220px]"
						>
							<div class="min-w-0">
								<div class="flex flex-wrap items-center gap-2">
									<p class="truncate font-semibold text-toned">{{ resolveActivityTitle(activity) }}</p>
									<UBadge
										:label="t(`groups.activities.statuses.${activity.status}`)"
										:color="getActivityStatusMeta(activity.status).color"
										:icon="getActivityStatusMeta(activity.status).icon"
										variant="subtle"
									/>
									<UBadge
										v-if="activity.needs_application && canAcceptActivityApplications(activity.status)"
										:label="t('groups.dashboard.recent_runs.accepting_applications')"
										color="primary"
										variant="soft"
									/>
								</div>

								<p class="mt-2 text-sm text-muted">
									{{ resolveActivityTypeName(activity) }}
								</p>

								<div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted">
									<div class="flex items-center gap-2">
										<UIcon name="i-lucide-user-round" class="text-base" />
										<span>{{ resolveActivityOrganizer(activity) }}</span>
									</div>
									<div class="flex items-center gap-2">
										<UIcon name="i-lucide-calendar-clock" class="text-base" />
										<span>{{ formatDateTime(activity.starts_at, t("groups.activities.cards.no_time")) }}</span>
									</div>
									<div class="flex items-center gap-2">
										<UIcon name="i-lucide-users-round" class="text-base" />
										<span>{{ t("groups.activities.cards.slots", { count: activity.slot_count }) }}</span>
									</div>
									<div class="flex items-center gap-2">
										<UIcon name="i-lucide-file-text" class="text-base" />
										<span>{{ t("groups.activities.cards.applications", { count: activity.application_count }) }}</span>
									</div>
								</div>
							</div>

							<div class="flex flex-col items-start justify-between gap-3 lg:items-end">
								<div class="flex flex-wrap gap-2 lg:justify-end">
									<UBadge
										:label="activity.is_public
											? t('groups.dashboard.recent_runs.public')
											: t('groups.dashboard.recent_runs.private')"
										color="neutral"
										variant="subtle"
									/>
									<UBadge
										v-if="activity.allow_guest_applications"
										:label="t('groups.dashboard.recent_runs.guest_welcome')"
										color="success"
										variant="subtle"
									/>
								</div>

								<div class="text-sm text-muted lg:text-right">
									<p>{{ t("groups.dashboard.recent_runs.updated", {
										time: formatRelativeTime(activity.updated_at, t("groups.dashboard.labels.not_available")),
									}) }}</p>
									<p class="mt-1">{{ formatDateTime(activity.updated_at, t("groups.dashboard.labels.not_available")) }}</p>
								</div>

								<UButton
									v-if="group.permissions.can_manage_activities"
									color="neutral"
									variant="outline"
									icon="i-lucide-arrow-up-right"
									:label="t('groups.dashboard.recent_runs.open_board')"
									@click="goToActivityBoard(activity.id)"
								/>
							</div>
						</div>
					</div>

					<div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
						<div class="flex h-14 w-14 items-center justify-center border border-default bg-muted/15">
							<UIcon name="i-lucide-calendar-range" class="text-2xl text-muted" />
						</div>
						<p class="font-semibold text-toned">{{ t("groups.dashboard.recent_runs.empty_title") }}</p>
						<p class="max-w-md text-sm text-muted">{{ t("groups.dashboard.recent_runs.empty_description") }}</p>
					</div>
				</UCard>

				<div class="flex flex-col gap-6">
					<UCard class="border-default">
						<div class="flex items-start justify-between gap-4">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("groups.dashboard.pipeline.title") }}</p>
								<p class="mt-1 text-sm text-muted">{{ t("groups.dashboard.pipeline.subtitle") }}</p>
							</div>

							<UBadge
								:label="t('groups.dashboard.pipeline.total', { count: group.stats.activity_count })"
								color="neutral"
								variant="subtle"
							/>
						</div>

						<div v-if="statusSegments.length > 0" class="mt-5 space-y-5">
							<div class="flex h-3 w-full overflow-hidden bg-muted/20">
								<div
									v-for="segment in statusSegments"
									:key="segment.status"
									:class="segment.meta.dotClass"
									:style="{ width: `${segment.share}%` }"
								/>
							</div>

							<div class="space-y-3">
								<div
									v-for="segment in statusSegments"
									:key="segment.status"
									class="space-y-2"
								>
									<div class="flex items-center justify-between gap-3 text-sm">
										<div class="flex items-center gap-2">
											<div class="h-2.5 w-2.5 rounded-full" :class="segment.meta.dotClass" />
											<span class="text-toned">{{ t(`groups.activities.statuses.${segment.status}`) }}</span>
										</div>
										<span class="text-muted">{{ segment.count }} · {{ segment.share }}%</span>
									</div>
									<div class="h-1.5 bg-muted/20">
										<div
											class="h-full"
											:class="segment.meta.dotClass"
											:style="{ width: `${segment.share}%` }"
										/>
									</div>
								</div>
							</div>

							<div class="grid gap-3 sm:grid-cols-2">
								<div class="border border-default bg-muted/10 px-4 py-3">
									<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.dashboard.pipeline.active_management") }}</p>
									<p class="mt-2 text-xl font-semibold text-toned">{{ draftAndPlannedCount + activeRunCount }}</p>
								</div>
								<div class="border border-default bg-muted/10 px-4 py-3">
									<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.dashboard.pipeline.archived") }}</p>
									<p class="mt-2 text-xl font-semibold text-toned">{{ group.stats.completed_count + group.stats.cancelled_count }}</p>
								</div>
							</div>
						</div>

						<div v-else class="mt-5 border border-dashed border-default px-4 py-8 text-center text-sm text-muted">
							{{ t("groups.dashboard.pipeline.empty") }}
						</div>
					</UCard>

					<UCard class="border-default">
						<div class="flex items-start justify-between gap-4">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("groups.dashboard.setup.title") }}</p>
								<p class="mt-1 text-sm text-muted">{{ t("groups.dashboard.setup.subtitle") }}</p>
							</div>

							<UBadge
								:label="t('groups.dashboard.setup.score', {
									configured: configuredSetupCount,
									total: setupItems.length,
								})"
								color="primary"
								variant="subtle"
							/>
						</div>

						<div class="mt-5 space-y-5">
							<div class="space-y-2">
								<div class="flex items-center justify-between text-sm">
									<span class="text-muted">{{ t("groups.dashboard.setup.coverage") }}</span>
									<span class="font-medium text-toned">{{ setupProgress }}%</span>
								</div>
								<UProgress :model-value="setupProgress" color="primary" />
							</div>

							<div class="space-y-3">
								<div
									v-for="item in setupItems"
									:key="item.key"
									class="flex items-center justify-between gap-3 border border-default bg-muted/10 px-4 py-3"
								>
									<div class="flex items-center gap-3">
										<div class="flex h-9 w-9 items-center justify-center border border-default bg-background">
											<UIcon :name="item.icon" class="text-base text-muted" />
										</div>
										<p class="text-sm font-medium text-toned">{{ item.label }}</p>
									</div>

									<UBadge
										:label="item.configured
											? t('groups.dashboard.setup.configured')
											: t('groups.dashboard.setup.missing')"
										:color="item.configured ? 'success' : 'warning'"
										variant="subtle"
									/>
								</div>
							</div>

							<div class="grid gap-px border border-default bg-muted/30 sm:grid-cols-2">
								<div class="bg-background px-4 py-3">
									<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.dashboard.setup.join_mode") }}</p>
									<p class="mt-2 font-semibold text-toned">
										{{ group.is_public
											? t("groups.dashboard.setup.join_mode_public")
											: t("groups.dashboard.setup.join_mode_private") }}
									</p>
								</div>
								<div class="bg-background px-4 py-3">
									<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.dashboard.setup.discovery") }}</p>
									<p class="mt-2 font-semibold text-toned">
										{{ group.is_visible
											? t("groups.dashboard.setup.discovery_visible")
											: t("groups.dashboard.setup.discovery_hidden") }}
									</p>
								</div>
							</div>
						</div>
					</UCard>

					<UCard class="border-default">
						<div class="flex items-start justify-between gap-4">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("groups.dashboard.members.title") }}</p>
								<p class="mt-1 text-sm text-muted">{{ t("groups.dashboard.members.subtitle") }}</p>
							</div>

							<UButton
								color="neutral"
								variant="ghost"
								icon="i-lucide-arrow-right"
								:label="t('groups.dashboard.actions.view_members')"
								@click="goToMembersPage"
							/>
						</div>

						<div class="mt-5 grid gap-3 sm:grid-cols-3">
							<div
								v-for="item in memberRoleItems"
								:key="item.key"
								class="border border-default bg-muted/10 px-4 py-3"
							>
								<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ item.label }}</p>
								<p class="mt-2 text-xl font-semibold text-toned">{{ item.count }}</p>
							</div>
						</div>

						<div v-if="group.members_preview.length > 0" class="mt-5 space-y-3">
							<div
								v-for="member in group.members_preview"
								:key="member.id"
								class="flex items-center justify-between gap-3 border border-default bg-muted/10 px-4 py-3"
							>
								<UUser
									:name="member.name"
									:avatar="member.avatar_url
										? {
											src: member.avatar_url,
											alt: member.name,
										}
										: undefined"
									:description="formatRelativeTime(member.joined_at, t('groups.dashboard.labels.no_member_joins'))"
									size="sm"
								/>

								<UBadge
									:label="t(`groups.index.roles.${member.role}`)"
									:color="member.role === 'owner'
										? 'warning'
										: member.role === 'moderator'
											? 'primary'
											: 'neutral'"
									variant="subtle"
								/>
							</div>
						</div>

						<div v-else class="mt-5 border border-dashed border-default px-4 py-8 text-center text-sm text-muted">
							{{ t("groups.dashboard.members.empty") }}
						</div>
					</UCard>
				</div>
			</div>
		</div>
	</div>
</template>
