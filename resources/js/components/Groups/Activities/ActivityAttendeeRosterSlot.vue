<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { usePage } from "@inertiajs/vue3";
import { localizedValue } from "@/utils/localizedValue";
import type { ActivityApplicationFieldGroup, ActivitySlot, ActivitySlotFieldValue } from "@/Types/ActivityRoster";
import type { LocalizedText } from "@/Types/Common";

const props = defineProps<{
	slot: ActivitySlot
}>();

const { t, locale } = useI18n();
const page = usePage();
const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"));
const viewerUserId = computed<number | null>(() => {
	const userId = page.props.auth?.user?.id;

	return typeof userId === "number" ? userId : null;
});

const localizedText = (value: LocalizedText, fallback: string) => (
	localizedValue(value, locale.value, fallbackLocale.value) || fallback
);

const slotLabel = computed(() => localizedText(props.slot.slot_label, props.slot.slot_key));
const classField = computed(() => props.slot.field_values.find((field) => field.source === "character_classes") ?? null);
const roleField = computed(() => {
	const role = classField.value?.display_meta?.role;

	return typeof role === "string" ? role.trim().toLowerCase() : null;
});

const isViewerAssignedCharacter = computed(() => (
	props.slot.assigned_character !== null
	&& viewerUserId.value !== null
	&& props.slot.assigned_character.user_id === viewerUserId.value
));

const benchApplicationFieldGroups = computed<ActivityApplicationFieldGroup[]>(() => (
	props.slot.is_bench
		? props.slot.application_field_groups
			.map((group) => ({
				...group,
				items: group.items.filter((item) => applicationItemIconUrl(item) !== null),
			}))
			.filter((group) => group.items.length > 0)
		: []
));

const attendanceBadge = computed(() => {
	if (props.slot.attendance_status === "checked_in") {
		return {
			color: "success" as const,
			label: t("groups.activities.management.roster.checked_in"),
		};
	}

	if (props.slot.attendance_status === "late") {
		return {
			color: "warning" as const,
			label: t("groups.activities.management.roster.late"),
		};
	}

	if (props.slot.assigned_character_id !== null) {
		return {
			color: "primary" as const,
			label: t("groups.activities.management.roster.assigned"),
		};
	}

	return {
		color: "neutral" as const,
		label: t("groups.activities.management.roster.open"),
	};
});

const slotToneClass = computed(() => {
	if (isViewerAssignedCharacter.value) {
		return "border-primary/90 bg-primary/20 ring-1 ring-primary/50 shadow-[0_0_0_1px_rgba(0,200,255,0.22)]";
	}

	if (props.slot.is_raid_leader) {
		return "border-amber-400/70 bg-amber-500/10";
	}

	if (props.slot.is_host) {
		return "border-sky-400/70 bg-sky-500/10";
	}

	if (props.slot.attendance_status === "checked_in") {
		return "border-emerald-400/70 bg-emerald-500/10";
	}

	if (props.slot.attendance_status === "late") {
		return "border-amber-400/70 bg-amber-500/10";
	}

	if (roleField.value === "tank") {
		return "border-blue-500/70 bg-blue-500/10";
	}

	if (roleField.value === "healer") {
		return "border-emerald-500/70 bg-emerald-500/10";
	}

	if (roleField.value === "melee dps") {
		return "border-red-900/70 bg-red-950/20";
	}

	if (roleField.value === "physical ranged dps" || roleField.value === "magic ranged dps") {
		return "border-rose-400/70 bg-rose-400/10";
	}

	if (props.slot.assigned_character_id !== null) {
		return "border-primary/40 bg-primary/10";
	}

	return "border-dashed border-default bg-elevated/50";
});

const fieldDisplayValue = (field: ActivitySlotFieldValue): string => {
	if (typeof field.display_value === "string") {
		return field.display_value;
	}

	if (field.display_value) {
		return localizedText(field.display_value, "");
	}

	if (typeof field.display_meta?.label === "string") {
		return field.display_meta.label;
	}

	if (field.display_meta?.label) {
		return localizedText(field.display_meta.label, "");
	}

	return field.display_meta?.name
		|| field.display_meta?.shorthand
		|| field.display_meta?.key
		|| "";
};

const visibleFieldEntries = computed(() => (
	props.slot.field_values
		.map((field) => ({
			id: field.id,
			label: localizedText(field.field_label, field.field_key),
			value: fieldDisplayValue(field),
		}))
		.filter((field) => field.value !== "")
		.slice(0, 2)
));

const fieldIconUrl = (field: ActivitySlotFieldValue): string | null => (
	field.display_meta?.transparent_icon_url
		|| field.display_meta?.flaticon_url
		|| field.display_meta?.icon_url
		|| field.display_meta?.black_icon_url
		|| field.display_meta?.sprite_url
		|| null
);

const iconFieldEntries = computed(() => (
	props.slot.field_values
		.map((field) => ({
			id: field.id,
			label: localizedText(field.field_label, field.field_key),
			value: fieldDisplayValue(field),
			iconUrl: fieldIconUrl(field),
		}))
		.filter((field) => field.iconUrl !== null)
		.slice(0, 3)
));

function applicationItemIconUrl(item: ActivityApplicationFieldGroup["items"][number]): string | null {
	return (
	item.transparent_icon_url
		|| item.flat_icon_url
		|| item.icon_url
		|| null
	);
}

const showsBenchApplicationRows = computed(() => (
	props.slot.is_bench
	&& props.slot.assigned_character !== null
	&& benchApplicationFieldGroups.value.length > 0
));

const showsInlineSlotIcons = computed(() => (
	props.slot.assigned_character !== null
	&& !showsBenchApplicationRows.value
	&& iconFieldEntries.value.length > 0
));

const slotFrameClass = computed(() => (
	showsBenchApplicationRows.value
		? "min-h-18 py-2.5"
		: "h-18 py-2"
));

const designationMarker = computed(() => {
	if (props.slot.is_raid_leader) {
		return {
			key: "raid-leader",
			label: t("groups.activities.management.roster.raid_leader_badge"),
			icon: "i-lucide-crown",
			wrapperClass: "-left-2 -top-2 bg-amber-400 text-amber-950 ring-amber-200/80",
			iconClass: "text-amber-400 drop-shadow-[0_4px_10px_rgba(251,191,36,0.85)]",
		};
	}

	if (props.slot.is_host) {
		return {
			key: "host",
			label: t("groups.activities.management.roster.host_badge"),
			icon: "i-lucide-swords",
			wrapperClass: "-left-2 -top-2 bg-sky-500 text-sky-50 ring-sky-300/70",
			iconClass: "text-sky-500 drop-shadow-[0_4px_10px_rgba(14,165,233,0.85)]",
		};
	}

	return null;
});
</script>

<template>
	<div
		class="relative border px-3 transition-colors"
		:class="[slotToneClass, slotFrameClass]"
	>
		<div
			v-if="designationMarker"
			class="pointer-events-none absolute z-20 flex h-8 w-8 items-center justify-center bg-transparent shadow-lg"
			:class="designationMarker.wrapperClass"
			:aria-label="designationMarker.label"
			:title="designationMarker.label"
		>
			<UIcon
				:name="designationMarker.icon"
				class="h-8 w-8 -rotate-35"
				:class="designationMarker.iconClass"
			/>
		</div>

		<div class="flex h-full min-h-0 justify-center flex-col gap-1.5 overflow-hidden">
<!--			<div class="flex items-start justify-between gap-2">-->
<!--				<div class="min-w-0">-->
<!--					<p class="truncate text-[10px] uppercase tracking-[0.2em] text-muted">-->
<!--						{{ slotLabel }}-->
<!--					</p>-->
<!--				</div>-->

<!--				<div class="flex shrink-0 items-center gap-1.5">-->
<!--					<UBadge-->
<!--						size="sm"-->
<!--						:color="attendanceBadge.color"-->
<!--						variant="subtle"-->
<!--						:label="attendanceBadge.label"-->
<!--					/>-->
<!--				</div>-->
<!--			</div>-->

			<div
				v-if="slot.assigned_character"
				class="flex "
			>
				<div
					v-if="showsBenchApplicationRows"
					class="w-full flex flex-row items-center gap-2 justify-between overflow-hidden"
				>
					<div class="flex items-center gap-2 overflow-hidden">
						<UUser
							size="sm"
							:name="slot.assigned_character.name"
							:description="slot.assigned_character.world+' - '+slot.assigned_character.datacenter"
							:avatar="{
								src: slot.assigned_character.avatar_url ?? null
							}"
						/>
					</div>
					<div class="flex flex-col gap-1 overflow-hidden">
						<div
							v-for="group in benchApplicationFieldGroups"
							:key="group.question_key"
							class="flex flex-wrap items-center gap-1"
						>
							<img
								v-for="(item, itemIndex) in group.items"
								:key="`${group.question_key}-${itemIndex}-${item.label}`"
								:src="applicationItemIconUrl(item) || undefined"
								:alt="item.label"
								:title="item.label"
								class="h-5 w-5 rounded-none object-contain"
							>
						</div>
					</div>
				</div>
				<div v-else class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
					<UUser
						size="sm"
						:name="slot.assigned_character.name"
						:description="slot.assigned_character.world+' - '+slot.assigned_character.datacenter"
						:avatar="{
							src: slot.assigned_character.avatar_url ?? null
						}"
					/>
					<div
						v-if="showsInlineSlotIcons"
						class="ml-auto flex shrink-0 items-center gap-1"
					>
						<img
							v-for="field in iconFieldEntries"
							:key="field.id"
							:src="field.iconUrl || undefined"
							:alt="field.value || field.label"
							:title="field.value || field.label"
							class="h-6 w-6 rounded-none object-contain"
						>
					</div>
				</div>
			</div>
			<div v-else class="h-full flex items-center justify-center">
				<p class="text-sm text-muted">{{ t("groups.activities.management.roster.empty_slot") }}</p>
			</div>
		</div>
	</div>
</template>
