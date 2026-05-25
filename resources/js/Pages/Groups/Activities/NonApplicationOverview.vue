<script setup lang="ts">
import axios from "axios";
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";
import { useToast } from "@nuxt/ui/composables";
import SeoHead from "@/components/Shared/SeoHead.vue";
import PageHeader from "@/components/PageHeader.vue";
import ActivityCompletionSummaryPanel from "@/components/Groups/Activities/ActivityCompletionSummaryPanel.vue";
import ActivityRosterSummaryPanel from "@/components/Groups/Activities/ActivityRosterSummaryPanel.vue";
import ActivitySelfAssignRosterBoard from "@/components/Groups/Activities/ActivitySelfAssignRosterBoard.vue";
import SelfAssignCharacterToSlotModal from "@/components/Groups/Activities/SelfAssignCharacterToSlotModal.vue";
import { localizedValue } from "@/utils/localizedValue";
import { getActivityStatusMeta } from "@/utils/activityStatusMeta";
import { isArchivedActivityStatus } from "@/utils/activityLifecycle";
import { buildActivityCompletionSummary } from "@/utils/buildActivityCompletionSummary";
import type { ActivityOverviewPermissions, AttendeeActivity, PublicGroupSummary } from "@/Types/ActivityAttendee";
import type { ActivitySlot } from "@/Types/ActivityRoster";
import type { ManualAssignmentCharacter, QueueFilterField } from "@/Types/ActivityQueue";

const props = defineProps<{
	group: PublicGroupSummary
	activity: AttendeeActivity
	permissions: ActivityOverviewPermissions
	secretKey?: string | null
	slotFieldDefinitions: QueueFilterField[]
	selfAssignmentCharacters: ManualAssignmentCharacter[]
}>()

const { t, locale } = useI18n();
const page = usePage();
const toast = useToast();
const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"));
const localTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const activityData = ref<AttendeeActivity>({
	...props.activity,
	slots: [...props.activity.slots],
});
const selfAssignSlot = ref<ActivitySlot | null>(null);
const isSelfAssignModalOpen = ref(false);
const isSelfAssignmentPending = ref(false);
const pendingSlotId = ref<number | null>(null);
const viewerUserId = computed<number | null>(() => {
	const userId = page.props.auth?.user?.id;

	return typeof userId === "number" ? userId : null;
});

const currentActivity = computed(() => activityData.value);

const activityTypeName = computed(() => {
	return localizedValue(currentActivity.value.activity_type?.draft_name, locale.value, fallbackLocale.value)
		|| currentActivity.value.activity_type?.slug
		|| t("groups.activities.cards.unknown_type");
});

const activityTitle = computed(() => currentActivity.value.title || activityTypeName.value);
const statusMeta = computed(() => getActivityStatusMeta(currentActivity.value.status));
const seoDescription = computed(() => currentActivity.value.description
	|| t("meta.seo.activities.overview_description", {
		title: activityTitle.value,
		group: props.group.name,
	}));
const seoStructuredData = computed(() => ({
	"@context": "https://schema.org",
	"@type": "Event",
	name: activityTitle.value,
	description: seoDescription.value,
	startDate: currentActivity.value.starts_at || undefined,
	eventAttendanceMode: "https://schema.org/OnlineEventAttendanceMode",
	eventStatus: currentActivity.value.status === "cancelled"
		? "https://schema.org/EventCancelled"
		: currentActivity.value.status === "complete"
			? "https://schema.org/EventCompleted"
			: "https://schema.org/EventScheduled",
	organizer: {
		"@type": "Organization",
		name: props.group.name,
	},
	image: currentActivity.value.banner_image_url || currentActivity.value.small_image_url || undefined,
	url: route("groups.activities.overview", selfAssignmentRouteParameters.value),
}));
const completedProgression = computed(() => buildActivityCompletionSummary({
	activity: currentActivity.value,
	locale: locale.value,
	fallbackLocale: fallbackLocale.value,
	t,
}));
const canSelfAssign = computed(() => (
	props.permissions.can_self_assign
	&& !isArchivedActivityStatus(currentActivity.value.status)
));
const mainSlots = computed(() => currentActivity.value.slots.filter((slot) => !slot.is_bench));
const benchSlots = computed(() => currentActivity.value.slots.filter((slot) => slot.is_bench));
const assignedMainSlotCount = computed(() => mainSlots.value.filter((slot) => slot.assigned_character_id !== null).length);
const viewerAssignedSlot = computed(() => currentActivity.value.slots.find((slot) => (
	slot.assigned_character !== null
	&& slot.assigned_character.user_id !== null
	&& slot.assigned_character.user_id === viewerUserId.value
)) ?? null);
const viewerAssignedCharacterName = computed(() => viewerAssignedSlot.value?.assigned_character?.name ?? null);
const hasSelfAssignmentCharacters = computed(() => props.selfAssignmentCharacters.length > 0);

const serverStartsAtLabel = computed(() => {
	if (!currentActivity.value.starts_at) {
		return t("groups.activities.cards.no_time");
	}

	return new Intl.DateTimeFormat(locale.value, {
		weekday: "long",
		day: "numeric",
		month: "long",
		hour: "2-digit",
		minute: "2-digit",
		timeZone: "UTC",
	}).format(new Date(currentActivity.value.starts_at));
});

const localStartsAtLabel = computed(() => {
	if (!currentActivity.value.starts_at) {
		return t("groups.activities.cards.no_time");
	}

	return new Intl.DateTimeFormat(locale.value, {
		weekday: "long",
		day: "numeric",
		month: "long",
		hour: "2-digit",
		minute: "2-digit",
		timeZoneName: "short",
	}).format(new Date(currentActivity.value.starts_at));
});

const durationLabel = computed(() => {
	if (!currentActivity.value.duration_hours) {
		return t("groups.activities.overview.meta.no_duration");
	}

	return t("groups.activities.management.overview.duration", { count: currentActivity.value.duration_hours });
});

const targetProgPointLabel = computed(() => {
	if (currentActivity.value.target_prog_point_label) {
		return localizedValue(currentActivity.value.target_prog_point_label, locale.value, fallbackLocale.value)
			|| currentActivity.value.target_prog_point_key
			|| t("groups.activities.overview.details.no_target_prog_point");
	}

	return currentActivity.value.target_prog_point_key
		|| t("groups.activities.overview.details.no_target_prog_point");
});

const difficultyLabel = computed(() => currentActivity.value.difficulty
	? t(`groups.activities.difficulties.${currentActivity.value.difficulty}`)
	: "—");

const runStyleLabel = computed(() => currentActivity.value.run_style
	? t(`groups.activities.run_styles.${currentActivity.value.run_style}`)
	: "—");

const intensityLabel = computed(() => currentActivity.value.intensity
	? t(`groups.activities.intensities.${currentActivity.value.intensity}`)
	: "—");

const minimumItemLevelLabel = computed(() => currentActivity.value.min_item_level
	? String(currentActivity.value.min_item_level)
	: t("groups.activities.overview.details.no_min_item_level"));

const beginnerFriendlyLabel = computed(() => t(
	currentActivity.value.beginner_friendly
		? "general.yes"
		: "general.no"
));

const organizerLabel = computed(() => (
	currentActivity.value.organized_by_character?.name
	|| currentActivity.value.organized_by?.name
	|| t("groups.activities.cards.no_organizer")
));
const cancellationAlertDescription = computed(() => (
	currentActivity.value.cancellation_reason
		|| t("groups.activities.overview.cancelled_alert.description")
));

const selfAssignmentRouteParameters = computed(() => ({
	group: props.group.slug,
	activity: currentActivity.value.id,
	secretKey: props.secretKey || undefined,
}));

const initialCharacterId = computed(() => props.selfAssignmentCharacters[0]?.id ?? null);
const assignmentModeLabel = computed(() => t("groups.activities.create.summary.assignment_self_assign"));

const goBack = () => {
	if (typeof window !== "undefined" && window.history.length > 1) {
		window.history.back();

		return;
	}

	if (props.permissions.can_manage) {
		goToManagementPage();

		return;
	}

	router.get(route("home"));
};

const goToManagementPage = () => {
	router.get(route("groups.dashboard.activities.show", {
		group: props.group.slug,
		activity: currentActivity.value.id,
	}));
};

const firstValidationErrorMessage = (error: any): string | null => {
	const errors = error?.response?.data?.errors;

	if (!errors || typeof errors !== "object") {
		return null;
	}

	for (const value of Object.values(errors)) {
		if (Array.isArray(value) && typeof value[0] === "string" && value[0].length > 0) {
			return value[0];
		}
	}

	return null;
};

const patchSlot = (updatedSlot: ActivitySlot) => {
	activityData.value = {
		...activityData.value,
		slots: activityData.value.slots.map((slot) => slot.id === updatedSlot.id ? updatedSlot : slot),
	};
};

const openSelfAssignModal = (slot: ActivitySlot) => {
	if (!canSelfAssign.value || viewerAssignedSlot.value || !hasSelfAssignmentCharacters.value) {
		return;
	}

	selfAssignSlot.value = slot;
	isSelfAssignModalOpen.value = true;
};

const closeSelfAssignModal = () => {
	isSelfAssignModalOpen.value = false;
	selfAssignSlot.value = null;
};

const confirmSelfAssign = async (payload: { characterId: number, slotId: number, fieldValues: Record<string, string | string[]> }) => {
	if (!selfAssignSlot.value || isSelfAssignmentPending.value) {
		return;
	}

	isSelfAssignmentPending.value = true;
	pendingSlotId.value = payload.slotId;

	try {
		const response = await axios.post(route("groups.activities.self-assignments.store", {
			...selfAssignmentRouteParameters.value,
			slot: payload.slotId,
		}), {
			character_id: payload.characterId,
			field_values: payload.fieldValues,
			expected_slot_state_token: selfAssignSlot.value.state_token,
		});

		patchSlot(response.data.slot);
		closeSelfAssignModal();

		toast.add({
			title: t("general.success"),
			description: t("groups.activities.overview.self_signup.messages.assign_success"),
			color: "success",
			icon: "i-lucide-check",
		});
	} catch (error: any) {
		toast.add({
			title: t("general.error"),
			description: firstValidationErrorMessage(error)
				?? error?.response?.data?.message
				?? t("groups.activities.overview.self_signup.messages.assign_error"),
			color: "error",
			icon: "i-lucide-octagon-alert",
		});
	} finally {
		isSelfAssignmentPending.value = false;
		pendingSlotId.value = null;
	}
};

const removeSelfFromSlot = async (slot: ActivitySlot) => {
	if (isSelfAssignmentPending.value) {
		return;
	}

	isSelfAssignmentPending.value = true;
	pendingSlotId.value = slot.id;

	try {
		const response = await axios.delete(route("groups.activities.self-assignments.destroy", {
			...selfAssignmentRouteParameters.value,
			slot: slot.id,
		}), {
			data: {
				expected_slot_state_token: slot.state_token,
			},
		});

		patchSlot(response.data.slot);

		toast.add({
			title: t("general.success"),
			description: t("groups.activities.overview.self_signup.messages.remove_success"),
			color: "success",
			icon: "i-lucide-check",
		});
	} catch (error: any) {
		toast.add({
			title: t("general.error"),
			description: firstValidationErrorMessage(error)
				?? error?.response?.data?.message
				?? t("groups.activities.overview.self_signup.messages.remove_error"),
			color: "error",
			icon: "i-lucide-octagon-alert",
		});
	} finally {
		isSelfAssignmentPending.value = false;
		pendingSlotId.value = null;
	}
};
</script>

<template>
	<div class="w-full overflow-x-hidden">
		<SeoHead
			:title="activityTitle"
			:description="seoDescription"
			:image="currentActivity.banner_image_url || currentActivity.small_image_url"
			og-type="event"
			:structured-data="seoStructuredData"
		/>

		<UButton
			:label="t('groups.activities.back')"
			icon="i-lucide-arrow-left"
			variant="ghost"
			color="neutral"
			@click.stop="goBack"
		/>

		<UAlert
			v-if="currentActivity.status === 'cancelled'"
			class="mt-4"
			color="error"
			variant="soft"
			icon="i-lucide-ban"
			:title="t('groups.activities.overview.cancelled_alert.title')"
		>
			<template #description>
				<p class="whitespace-pre-wrap text-sm">
					{{ cancellationAlertDescription }}
				</p>
			</template>
		</UAlert>

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
					:label="t(`groups.activities.statuses.${currentActivity.status}`)"
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

		<UAlert
			v-if="viewerAssignedCharacterName"
			class="mt-4"
			color="primary"
			variant="soft"
			icon="i-lucide-user-check"
			:title="t('groups.activities.overview.self_signup.alerts.assigned_title')"
			:description="t('groups.activities.overview.self_signup.alerts.assigned_description', { character: viewerAssignedCharacterName })"
		/>

		<UAlert
			v-else-if="canSelfAssign && !hasSelfAssignmentCharacters"
			class="mt-4"
			color="warning"
			variant="soft"
			icon="i-lucide-triangle-alert"
			:title="t('groups.activities.overview.self_signup.alerts.no_characters_title')"
			:description="t('groups.activities.overview.self_signup.alerts.no_characters_description')"
		/>

		<div class="mt-6 flex flex-col gap-6">
			<section class="border border-default bg-muted/20 dark:bg-elevated/25">
				<div class="grid gap-px md:grid-cols-2 xl:grid-cols-5">
					<div class="px-4 py-4">
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
								v-if="currentActivity.organized_by_character"
								size="sm"
								:name="currentActivity.organized_by_character.name"
								:avatar="currentActivity.organized_by_character.avatar_url
									? {
										src: currentActivity.organized_by_character.avatar_url,
										alt: currentActivity.organized_by_character.name,
									}
									: undefined"
								:description="group.name"
							/>
							<div v-else class="flex items-center gap-3">
								<UAvatar
									v-if="currentActivity.organized_by?.avatar_url"
									size="sm"
									:src="currentActivity.organized_by.avatar_url"
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
						<p class="mt-2 font-semibold text-toned">{{ currentActivity.datacenter || "—" }}</p>
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
					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.overview.details.description") }}</p>
						<p class="mt-2 break-words [overflow-wrap:anywhere] whitespace-pre-wrap text-sm text-toned">
							{{ currentActivity.description || t("groups.activities.overview.details.no_description") }}
						</p>
					</div>

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.notes") }}</p>
						<p class="mt-2 break-words [overflow-wrap:anywhere] whitespace-pre-wrap text-sm text-muted">
							{{ currentActivity.notes || t("groups.activities.create.summary.no_notes") }}
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

					<div class="bg-background px-4 py-4">
						<p class="text-xs uppercase tracking-[0.22em] text-muted">{{ t("groups.activities.create.summary.assignment") }}</p>
						<p class="mt-2 font-semibold text-toned">{{ assignmentModeLabel }}</p>
					</div>
				</div>
			</section>

			<ActivityCompletionSummaryPanel
				v-if="completedProgression"
				:completed-progression="completedProgression"
			/>
			<ActivityRosterSummaryPanel
				v-if="currentActivity.roster_summary_presets.length > 0"
				:presets="currentActivity.roster_summary_presets"
				:slots="currentActivity.slots"
			/>
			<ActivitySelfAssignRosterBoard
				:slots="currentActivity.slots"
				:can-self-assign="canSelfAssign"
				:has-verified-characters="hasSelfAssignmentCharacters"
				:viewer-assigned-slot-id="viewerAssignedSlot?.id ?? null"
				:pending-slot-id="pendingSlotId"
				@assign="openSelfAssignModal"
				@remove="removeSelfFromSlot"
			/>
		</div>

		<SelfAssignCharacterToSlotModal
			v-model:open="isSelfAssignModalOpen"
			:slot="selfAssignSlot"
			:characters="selfAssignmentCharacters"
			:slot-field-definitions="slotFieldDefinitions"
			:is-submitting="isSelfAssignmentPending"
			:initial-character-id="initialCharacterId"
			@confirm="confirmSelfAssign"
			@update:open="(value) => {
				if (!value) {
					closeSelfAssignModal();
				}
			}"
		/>
	</div>
</template>
