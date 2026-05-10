<script setup lang="ts">
import type { GroupProfileActivity, GroupProfileGroup, GroupProfileStaffMember } from "@/Types/Groups";
import { computed } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";
import { localizedValue } from "@/utils/localizedValue";
import { getActivityStatusMeta } from "@/utils/activityStatusMeta";
import { useGroupNotificationToast } from "@/composables/useGroupNotificationToast";

const props = defineProps<{
	group: GroupProfileGroup
}>();

const { t, locale } = useI18n();
const page = usePage();
const { showGroupNotificationsToast } = useGroupNotificationToast();
const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"));
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const isGroupActionProcessing = computed(() => router.processing);

const staffMembers = computed(() => props.group.staff_members.length > 0
	? props.group.staff_members
	: props.group.owner.id
		? [{
			id: props.group.owner.id,
			name: props.group.owner.name || t("groups.profile.staff.unknown"),
			avatar_url: props.group.owner.avatar_url,
			role: "owner",
			joined_at: null,
		}]
		: []);

const currentActivities = computed(() => props.group.activities.current);
const recentActivities = computed(() => props.group.activities.recent);
const fallbackBackHref = computed(() => isAuthenticated.value ? route("groups.index") : "/");

const goBack = () => {
	if (typeof window !== "undefined" && window.history.length > 1) {
		window.history.back();
		return;
	}

	router.get(fallbackBackHref.value);
};

const joinGroup = () => {
	if (!props.group.permissions.can_join) {
		return;
	}

	if (!isAuthenticated.value) {
		router.get(route("login"));
		return;
	}

	router.post(route("groups.join", props.group.slug), {}, {
		preserveScroll: true,
	});
};

const followGroup = () => {
	if (!props.group.permissions.can_follow) {
		return;
	}

	if (!isAuthenticated.value) {
		router.get(route("login"));
		return;
	}

	router.post(route("groups.follow", props.group.slug), {}, {
		preserveScroll: true,
	});
};

const unfollowGroup = () => {
	if (!props.group.permissions.can_unfollow || !isAuthenticated.value) {
		return;
	}

	router.delete(route("groups.unfollow", props.group.slug), {
		preserveScroll: true,
	});
};

const leaveGroup = () => {
	if (!props.group.permissions.can_leave || !isAuthenticated.value) {
		return;
	}

	router.post(route("groups.leave", props.group.slug), {}, {
		preserveScroll: true,
	});
};

const setGroupNotifications = (enabled: boolean) => {
	if (!props.group.permissions.can_toggle_notifications || !isAuthenticated.value) {
		return;
	}

	router.patch(route("groups.follow-notifications.update", props.group.slug), {
		enabled,
	}, {
		preserveScroll: true,
		onSuccess: () => {
			showGroupNotificationsToast(enabled);
		},
	});
};

const openDashboard = () => {
	router.get(route("groups.dashboard", props.group.slug));
};

const openDiscordInvite = () => {
	if (!props.group.discord_invite_url || typeof window === "undefined") {
		return;
	}

	window.open(props.group.discord_invite_url, "_blank", "noopener,noreferrer");
};

const openActivity = (activity: GroupProfileActivity) => {
	router.get(route("groups.activities.overview", {
		group: props.group.slug,
		activity: activity.id,
	}));
};

const resolveActivityTypeName = (activity: GroupProfileActivity) => (
	localizedValue(activity.activity_type?.draft_name, locale.value, fallbackLocale.value)
	|| activity.activity_type?.slug
	|| t("groups.activities.cards.unknown_type")
);

const resolveActivityTitle = (activity: GroupProfileActivity) => activity.title || resolveActivityTypeName(activity);

const resolveOrganizerName = (activity: GroupProfileActivity) => (
	activity.organized_by_character?.name
	|| activity.organized_by?.name
	|| t("groups.activities.cards.no_organizer")
);

const formatDateTime = (value: string | null, fallback = t("groups.activities.cards.no_time")) => {
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

const formatRelativeTime = (value: string | null, fallback: string) => {
	if (!value) {
		return fallback;
	}

	const diffMs = new Date(value).getTime() - Date.now();
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

	return t("groups.profile.activity.just_now");
};

const formatDuration = (activity: GroupProfileActivity) => activity.duration_hours
	? t("groups.activities.management.overview.duration", { count: activity.duration_hours })
	: t("groups.activities.management.overview.no_duration");

const roleColor = (role: string): "warning" | "primary" | "neutral" => {
	if (role === "owner") {
		return "warning";
	}

	if (role === "moderator") {
		return "primary";
	}

	return "neutral";
};

const memberJoinedLabel = (member: GroupProfileStaffMember) => (
	member.joined_at
		? formatRelativeTime(member.joined_at, t("groups.profile.staff.joined_unknown"))
		: t("groups.profile.staff.joined_unknown")
);
</script>

<template>
	<Head :title="`${group.name} -`" />

	<div class="w-full">
		<UButton
			class="mb-4"
			color="neutral"
			variant="ghost"
			icon="i-lucide-arrow-left"
			:label="t('groups.profile.actions.back')"
			@click.stop="goBack"
		/>

		<section class="overflow-hidden border border-default bg-background">
			<div class="grid gap-px bg-default/70 xl:grid-cols-[minmax(0,1fr)_22rem]">
				<div class="bg-background p-5 sm:p-6">
					<div class="flex flex-col gap-5 md:flex-row md:items-start">
						<div class="h-24 w-24 shrink-0 overflow-hidden border border-default bg-muted/20">
							<img
								v-if="group.profile_picture_url"
								:src="group.profile_picture_url"
								:alt="t('groups.profile.hero.profile_image_alt', { group: group.name })"
								class="h-full w-full object-cover"
							>
							<div v-else class="flex h-full w-full items-center justify-center">
								<UIcon name="i-lucide-shield" class="text-3xl text-muted" />
							</div>
						</div>

						<div class="min-w-0 flex-1">
							<div class="flex flex-wrap items-center gap-2">
								<UBadge
									:label="group.is_public ? t('groups.profile.badges.public') : t('groups.profile.badges.private')"
									:icon="group.is_public ? 'i-lucide-globe-2' : 'i-lucide-lock'"
									:color="group.is_public ? 'primary' : 'neutral'"
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

							<h1 class="mt-4 text-3xl font-black text-highlighted sm:text-4xl">
								{{ group.name }}
							</h1>
							<p class="mt-3 max-w-3xl whitespace-pre-wrap text-sm leading-7 text-muted sm:text-base">
								{{ group.description || t("groups.profile.hero.no_description") }}
							</p>

							<div class="mt-5 flex flex-wrap gap-2">
								<UButton
									v-if="group.permissions.can_access_dashboard"
									color="primary"
									icon="i-lucide-layout-dashboard"
									:label="t('groups.profile.actions.open_dashboard')"
									@click="openDashboard"
								/>
								<UButton
									v-else-if="group.permissions.can_join"
									color="primary"
									icon="i-lucide-user-plus"
									:label="isAuthenticated ? t('groups.profile.actions.join') : t('groups.profile.actions.sign_in_to_join')"
									:loading="isGroupActionProcessing"
									@click="joinGroup"
								/>
								<UButton
									v-if="group.permissions.can_follow"
									color="neutral"
									variant="outline"
									icon="i-lucide-bell-plus"
									:label="isAuthenticated ? t('groups.profile.actions.follow') : t('groups.profile.actions.sign_in_to_follow')"
									:loading="isGroupActionProcessing"
									@click="followGroup"
								/>
								<UButton
									v-if="group.permissions.can_toggle_notifications"
									color="neutral"
									variant="outline"
									:icon="group.follow.notifications_enabled ? 'i-lucide-bell-off' : 'i-lucide-bell'"
									:label="group.follow.notifications_enabled
										? t('groups.profile.actions.mute_notifications')
										: t('groups.profile.actions.unmute_notifications')"
									:loading="isGroupActionProcessing"
									@click="setGroupNotifications(!group.follow.notifications_enabled)"
								/>
								<UButton
									v-if="group.permissions.can_unfollow"
									color="neutral"
									variant="ghost"
									icon="i-lucide-user-minus"
									:label="t('groups.profile.actions.unfollow')"
									:loading="isGroupActionProcessing"
									@click="unfollowGroup"
								/>
								<UButton
									v-if="group.permissions.can_leave"
									color="error"
									variant="outline"
									icon="i-lucide-log-out"
									:label="t('groups.profile.actions.leave')"
									:loading="isGroupActionProcessing"
									@click="leaveGroup"
								/>
								<UButton
									v-if="group.discord_invite_url"
									color="neutral"
									variant="outline"
									icon="i-lucide-message-circle-more"
									:label="t('groups.profile.actions.open_discord')"
									@click="openDiscordInvite"
								/>
							</div>
						</div>
					</div>
				</div>

				<div class="grid gap-px bg-default/70 sm:grid-cols-3 xl:grid-cols-1">
					<div class="bg-background px-5 py-4">
						<p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted">
							{{ t("groups.profile.snapshot.members") }}
						</p>
						<p class="mt-2 text-2xl font-semibold text-toned">{{ group.stats.member_count }}</p>
					</div>
					<div class="bg-background px-5 py-4">
						<p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted">
							{{ t("groups.profile.snapshot.active_runs") }}
						</p>
						<p class="mt-2 text-2xl font-semibold text-toned">{{ group.stats.current_activity_count }}</p>
					</div>
					<div class="bg-background px-5 py-4">
						<p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted">
							{{ t("groups.profile.snapshot.staff") }}
						</p>
						<p class="mt-2 text-2xl font-semibold text-toned">{{ staffMembers.length }}</p>
					</div>
				</div>
			</div>
		</section>

		<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
			<div class="flex flex-col gap-6">
				<UCard class="border-default dark:bg-elevated/25">
					<template #header>
						<div class="flex items-start justify-between gap-4">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("groups.profile.activity.current_title") }}</p>
								<p class="mt-1 text-sm text-muted">{{ t("groups.profile.activity.current_subtitle") }}</p>
							</div>
							<UBadge
								color="primary"
								variant="subtle"
								:label="t('groups.profile.activity.count', { count: currentActivities.length })"
							/>
						</div>
					</template>

					<div v-if="currentActivities.length > 0" class="divide-y divide-default">
						<button
							v-for="activity in currentActivities"
							:key="activity.id"
							type="button"
							class="grid w-full gap-4 px-0 py-4 text-left transition hover:bg-muted/20 sm:px-2 lg:grid-cols-[minmax(0,1fr)_14rem]"
							@click="openActivity(activity)"
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
										v-if="activity.needs_application"
										:label="t('groups.profile.activity.applications')"
										color="primary"
										variant="soft"
									/>
									<UBadge
										v-if="activity.allow_guest_applications"
										:label="t('groups.profile.activity.guests')"
										color="success"
										variant="soft"
									/>
								</div>

								<p class="mt-2 text-sm text-muted">{{ resolveActivityTypeName(activity) }}</p>

								<div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted">
									<span class="inline-flex items-center gap-2">
										<UIcon name="i-lucide-user-round" class="text-base" />
										{{ resolveOrganizerName(activity) }}
									</span>
									<span class="inline-flex items-center gap-2">
										<UIcon name="i-lucide-users-round" class="text-base" />
										{{ t("groups.activities.cards.slots", { count: activity.slot_count }) }}
									</span>
									<span class="inline-flex items-center gap-2">
										<UIcon name="i-lucide-file-text" class="text-base" />
										{{ t("groups.activities.cards.applications", { count: activity.application_count }) }}
									</span>
								</div>
							</div>

							<div class="flex flex-col gap-2 text-sm text-muted lg:text-right">
								<p class="font-medium text-toned">{{ formatDateTime(activity.starts_at) }}</p>
								<p>{{ formatDuration(activity) }}</p>
								<p>{{ formatRelativeTime(activity.starts_at, t("groups.activities.cards.no_relative_time")) }}</p>
							</div>
						</button>
					</div>

					<div v-else class="border border-dashed border-default bg-muted/10 px-5 py-10 text-center">
						<UIcon name="i-lucide-calendar-range" class="mx-auto text-2xl text-muted" />
						<p class="mt-3 font-semibold text-toned">{{ t("groups.profile.activity.empty_current_title") }}</p>
						<p class="mx-auto mt-1 max-w-md text-sm text-muted">{{ t("groups.profile.activity.empty_current_description") }}</p>
					</div>
				</UCard>

				<UCard class="border-default dark:bg-elevated/25">
					<template #header>
						<div class="flex items-start justify-between gap-4">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("groups.profile.activity.recent_title") }}</p>
								<p class="mt-1 text-sm text-muted">{{ t("groups.profile.activity.recent_subtitle") }}</p>
							</div>
							<UBadge
								color="neutral"
								variant="subtle"
								:label="t('groups.profile.activity.count', { count: recentActivities.length })"
							/>
						</div>
					</template>

					<div v-if="recentActivities.length > 0" class="grid gap-3 md:grid-cols-2">
						<button
							v-for="activity in recentActivities"
							:key="activity.id"
							type="button"
							class="border border-default bg-muted/10 px-4 py-4 text-left transition hover:border-primary/40 hover:bg-primary/5"
							@click="openActivity(activity)"
						>
							<div class="flex items-start justify-between gap-3">
								<div class="min-w-0">
									<p class="truncate font-semibold text-toned">{{ resolveActivityTitle(activity) }}</p>
									<p class="mt-1 text-sm text-muted">{{ resolveActivityTypeName(activity) }}</p>
								</div>
								<UBadge
									:label="t(`groups.activities.statuses.${activity.status}`)"
									:color="getActivityStatusMeta(activity.status).color"
									variant="subtle"
								/>
							</div>
							<p class="mt-4 text-sm text-muted">
								{{ t("groups.profile.activity.updated", {
									time: formatRelativeTime(activity.updated_at, t("groups.profile.activity.unknown_update")),
								}) }}
							</p>
							<p class="mt-1 text-sm font-medium text-toned">{{ formatDateTime(activity.starts_at) }}</p>
						</button>
					</div>

					<div v-else class="border border-dashed border-default bg-muted/10 px-5 py-8 text-center text-sm text-muted">
						{{ t("groups.profile.activity.empty_recent") }}
					</div>
				</UCard>
			</div>

			<div class="flex flex-col gap-6">
				<UCard class="border-default dark:bg-elevated/25">
					<template #header>
						<div>
							<p class="font-semibold text-md text-toned">{{ t("groups.profile.staff.title") }}</p>
							<p class="mt-1 text-sm text-muted">{{ t("groups.profile.staff.subtitle") }}</p>
						</div>
					</template>

					<div v-if="staffMembers.length > 0" class="space-y-3">
						<div
							v-for="member in staffMembers"
							:key="member.id"
							class="flex items-center justify-between gap-3 border border-default bg-muted/10 px-4 py-3"
						>
							<UUser
								:name="member.name"
								:description="memberJoinedLabel(member)"
								:avatar="member.avatar_url
									? {
										src: member.avatar_url,
										alt: member.name,
									}
									: undefined"
								size="sm"
							/>
							<UBadge
								:label="t(`groups.index.roles.${member.role}`)"
								:color="roleColor(member.role)"
								variant="subtle"
							/>
						</div>
					</div>

					<div v-else class="border border-dashed border-default bg-muted/10 px-4 py-8 text-center text-sm text-muted">
						{{ t("groups.profile.staff.empty") }}
					</div>
				</UCard>

				<UCard class="border-default dark:bg-elevated/25">
					<template #header>
						<div>
							<p class="font-semibold text-md text-toned">{{ t("groups.profile.access.title") }}</p>
							<p class="mt-1 text-sm text-muted">{{ t("groups.profile.access.subtitle") }}</p>
						</div>
					</template>

					<div class="space-y-3">
						<div class="flex items-start gap-3 border border-default bg-muted/10 px-4 py-3">
							<UIcon
								:name="group.is_public ? 'i-lucide-door-open' : 'i-lucide-lock-keyhole'"
								class="mt-0.5 text-lg text-muted"
							/>
							<div>
								<p class="font-medium text-toned">
									{{ group.is_public ? t("groups.profile.access.public_title") : t("groups.profile.access.private_title") }}
								</p>
								<p class="mt-1 text-sm text-muted">
									{{ group.is_public ? t("groups.profile.access.public_description") : t("groups.profile.access.private_description") }}
								</p>
							</div>
						</div>

						<div class="flex items-start gap-3 border border-default bg-muted/10 px-4 py-3">
							<UIcon
								:name="group.is_visible ? 'i-lucide-search-check' : 'i-lucide-eye-off'"
								class="mt-0.5 text-lg text-muted"
							/>
							<div>
								<p class="font-medium text-toned">
									{{ group.is_visible ? t("groups.profile.access.visible_title") : t("groups.profile.access.hidden_title") }}
								</p>
								<p class="mt-1 text-sm text-muted">
									{{ group.is_visible ? t("groups.profile.access.visible_description") : t("groups.profile.access.hidden_description") }}
								</p>
							</div>
						</div>
					</div>
				</UCard>
			</div>
		</div>
	</div>
</template>
