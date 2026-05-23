<script setup lang="ts">
import { computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";
import PageHeader from "@/components/PageHeader.vue";
import ActivityAttendeeRosterBoard from "@/components/Groups/Activities/ActivityAttendeeRosterBoard.vue";
import ActivityRosterSummaryPanel from "@/components/Groups/Activities/ActivityRosterSummaryPanel.vue";
import { localizedValue } from "@/utils/localizedValue";
import { getActivityStatusMeta } from "@/utils/activityStatusMeta";
import type { ActivityOverviewPermissions, AttendeeActivity, PublicGroupSummary } from "@/Types/ActivityAttendee";

const props = defineProps<{
	group: PublicGroupSummary
	activity: AttendeeActivity
	permissions: ActivityOverviewPermissions
	secretKey?: string | null
}>();

const { t, locale } = useI18n();
const page = usePage();
const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"));
const localTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const activityTypeName = computed(() => {
	return localizedValue(props.activity.activity_type?.draft_name, locale.value, fallbackLocale.value)
		|| props.activity.activity_type?.slug
		|| t("groups.activities.cards.unknown_type");
});

const activityTitle = computed(() => props.activity.title || activityTypeName.value);
const statusMeta = computed(() => getActivityStatusMeta(props.activity.status));
const mainSlots = computed(() => props.activity.slots.filter((slot) => !slot.is_bench));
const benchSlots = computed(() => props.activity.slots.filter((slot) => slot.is_bench));
const assignedMainSlotCount = computed(() => mainSlots.value.filter((slot) => slot.assigned_character_id !== null).length);

const serverStartsAtLabel = computed(() => {
	if (!props.activity.starts_at) {
		return t("groups.activities.cards.no_time");
	}

	return new Intl.DateTimeFormat(locale.value, {
		weekday: "long",
		day: "numeric",
		month: "long",
		hour: "2-digit",
		minute: "2-digit",
		timeZone: "UTC",
	}).format(new Date(props.activity.starts_at));
});

const localStartsAtLabel = computed(() => {
	if (!props.activity.starts_at) {
		return t("groups.activities.cards.no_time");
	}

	return new Intl.DateTimeFormat(locale.value, {
		weekday: "long",
		day: "numeric",
		month: "long",
		hour: "2-digit",
		minute: "2-digit",
		timeZoneName: "short",
	}).format(new Date(props.activity.starts_at));
});

const durationLabel = computed(() => {
	if (!props.activity.duration_hours) {
		return t("groups.activities.overview.meta.no_duration");
	}

	return t("groups.activities.management.overview.duration", { count: props.activity.duration_hours });
});

const targetProgPointLabel = computed(() => {
	if (props.activity.target_prog_point_label) {
		return localizedValue(props.activity.target_prog_point_label, locale.value, fallbackLocale.value)
			|| props.activity.target_prog_point_key
			|| t("groups.activities.overview.details.no_target_prog_point");
	}

	return props.activity.target_prog_point_key
		|| t("groups.activities.overview.details.no_target_prog_point");
});

const guestApplicationsLabel = computed(() => t(
	props.activity.allow_guest_applications
		? "groups.activities.create.summary.guest_applications_enabled"
		: "groups.activities.create.summary.guest_applications_disabled"
));

const difficultyLabel = computed(() => props.activity.difficulty
	? t(`groups.activities.difficulties.${props.activity.difficulty}`)
	: "—");

const runStyleLabel = computed(() => props.activity.run_style
	? t(`groups.activities.run_styles.${props.activity.run_style}`)
	: "—");

const intensityLabel = computed(() => props.activity.intensity
	? t(`groups.activities.intensities.${props.activity.intensity}`)
	: "—");

const minimumItemLevelLabel = computed(() => props.activity.min_item_level
	? String(props.activity.min_item_level)
	: t("groups.activities.overview.details.no_min_item_level"));

const beginnerFriendlyLabel = computed(() => t(
	props.activity.beginner_friendly
		? "general.yes"
		: "general.no"
));

const organizerLabel = computed(() => (
	props.activity.organized_by_character?.name
	|| props.activity.organized_by?.name
	|| t("groups.activities.cards.no_organizer")
));

const applicationRouteParameters = computed(() => ({
	group: props.group.slug,
	activity: props.activity.id,
	secretKey: props.secretKey || undefined,
}));

const goBack = () => {
	if (props.group.is_public) {
		router.get(route("groups.show", props.group.slug));

		return;
	}

	if (typeof window !== "undefined" && window.history.length > 1) {
		window.history.back();

		return;
	}

	if (props.permissions.can_manage) {
		goToManagementPage();

		return;
	}

	if (props.permissions.can_apply) {
		goToApplicationPage();
	}
};

const goToApplicationPage = () => {
	router.get(route("groups.activities.application", applicationRouteParameters.value));
};

const goToManagementPage = () => {
	router.get(route("groups.dashboard.activities.show", {
		group: props.group.slug,
		activity: props.activity.id,
	}));
};
</script>

<template>
	<div class="w-full overflow-x-hidden">
		<UButton
			:label="t('groups.activities.back')"
			icon="i-lucide-arrow-left"
			variant="ghost"
			color="neutral"
			@click.stop="goBack"
		/>

		<PageHeader
			class="mt-4"
			:title="activityTitle"
			:subtitle="t('groups.activities.overview.subtitle', { group: group.name, type: activityTypeName })"
		>
			<div class="flex flex-wrap items-center justify-end gap-2">
				<UBadge
					size="md"
					variant="subtle"
					class="min-w-44 justify-center py-2"
					:color="statusMeta.color"
					:icon="statusMeta.icon"
					:label="t(`groups.activities.statuses.${activity.status}`)"
				/>
				<UButton
					v-if="activity.needs_application"
					color="primary"
					icon="i-lucide-file-pen-line"
					:label="t('groups.activities.overview.open_application')"
					@click="goToApplicationPage"
				/>
				<UButton
					v-if="permissions.can_manage"
					color="neutral"
					variant="outline"
					icon="i-lucide-settings-2"
					:label="t('groups.activities.overview.go_to_management')"
					@click="goToManagementPage"
				/>
			</div>
		</PageHeader>

		<div class="mt-6 flex flex-col gap-6">
			<section class="border border-default bg-muted/20 dark:bg-elevated/25">
				<div class="grid gap-px md:grid-cols-2 xl:grid-cols-5">
					<div class=" px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.management.type") }}</p>
						<p class="mt-2 break-words [overflow-wrap:anywhere] font-semibold text-toned">{{ activityTypeName }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.starts_at_st") }}</p>
						<p class="mt-2 break-words [overflow-wrap:anywhere] font-semibold text-toned">{{ serverStartsAtLabel }}</p>
						<p class="mt-1 text-sm text-muted">{{ durationLabel }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.starts_at_local", { timezone: localTimeZone }) }}</p>
						<p class="mt-2 break-words [overflow-wrap:anywhere] font-semibold text-toned">{{ localStartsAtLabel }}</p>
						<p class="mt-1 text-sm text-muted">{{ durationLabel }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.management.organizer") }}</p>
						<div class="mt-2">
							<UUser
								v-if="activity.organized_by_character"
								size="sm"
								:name="activity.organized_by_character.name"
								:avatar="activity.organized_by_character.avatar_url
									? {
										src: activity.organized_by_character.avatar_url,
										alt: activity.organized_by_character.name,
									}
									: undefined"
								:description="group.name"
							/>
							<div v-else class="flex items-center gap-3">
								<UAvatar
									v-if="activity.organized_by?.avatar_url"
									size="sm"
									:src="activity.organized_by.avatar_url"
									:alt="organizerLabel"
								/>
								<div class="min-w-0">
									<p class="break-words [overflow-wrap:anywhere] font-semibold text-toned">{{ organizerLabel }}</p>
									<p class="mt-1 break-words [overflow-wrap:anywhere] text-sm text-muted">{{ group.name }}</p>
								</div>
							</div>
						</div>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.overview.meta.roster") }}</p>
						<p class="mt-2 font-semibold text-toned">
							{{ t("groups.activities.overview.meta.filled_slots", { assigned: assignedMainSlotCount, total: mainSlots.length }) }}
						</p>
						<p class="mt-1 text-sm text-muted">
							{{ t("groups.activities.overview.meta.bench_slots", { count: benchSlots.length }) }}
						</p>
					</div>
				</div>

				<div class="grid gap-px border-t border-default md:grid-cols-2 xl:grid-cols-5">
					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.datacenter") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ activity.datacenter || "—" }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.difficulty") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ difficultyLabel }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.run_style") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ runStyleLabel }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.intensity") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ intensityLabel }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.min_item_level") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ minimumItemLevelLabel }}</p>
						<p class="mt-1 text-sm text-muted">{{ t("groups.activities.create.summary.beginner_friendly") }}: {{ beginnerFriendlyLabel }}</p>
					</div>
				</div>

				<div class="grid gap-px border-t border-default md:grid-cols-2 xl:grid-cols-5">
					<div
						class="bg-background px-4 py-4"
					>
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.overview.details.description") }}</p>
						<p class="mt-2 break-words [overflow-wrap:anywhere] whitespace-pre-wrap text-sm text-toned">
							{{ activity.description || t("groups.activities.overview.details.no_description") }}
						</p>
					</div>

					<div
						class="bg-background px-4 py-4"
					>
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.notes") }}</p>
						<p class="mt-2 break-words [overflow-wrap:anywhere] whitespace-pre-wrap text-sm text-muted">
							{{ activity.notes || t("groups.activities.create.summary.no_notes") }}
						</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.overview.details.target_prog_point") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ targetProgPointLabel }}</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.duration") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ durationLabel }}</p>
					</div>

					<div v-if="activity.allow_guest_applications" class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.overview.details.guest_applications") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ guestApplicationsLabel }}</p>
					</div>

					<div v-else class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.overview.details.pending_applications") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ t("groups.activities.overview.meta.pending_applications", { count: activity.pending_application_count }) }}</p>
					</div>
				</div>
			</section>

			<ActivityRosterSummaryPanel
				v-if="activity.roster_summary_presets.length > 0"
				:presets="activity.roster_summary_presets"
				:slots="activity.slots"
			/>
			<ActivityAttendeeRosterBoard :slots="activity.slots" />
		</div>
	</div>
</template>
