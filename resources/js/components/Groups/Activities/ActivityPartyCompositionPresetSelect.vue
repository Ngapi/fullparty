<script setup lang="ts">
import axios from "axios";
import { computed, ref } from "vue";
import { useToast } from "@nuxt/ui/composables";
import { useI18n } from "vue-i18n";
import { route } from "ziggy-js";
import CompositionPresetRoleIcons from "@/components/Groups/Activities/CompositionPresetRoleIcons.vue";
import type { ActivitySlot } from "@/Types/ActivityRoster";
import {
	type CompositionPreset,
	compositionPresetKeyForSlots,
	compositionPresetsForPartySize,
} from "@/utils/activityCompositionHints";

const props = defineProps<{
	groupSlug: string
	activityId: number
	groupKey: string
	slots: ActivitySlot[]
	disabled?: boolean
}>();

const emit = defineEmits<{
	slotsUpdated: [slots: ActivitySlot[]]
}>();

const { t } = useI18n();
const toast = useToast();
const isUpdating = ref(false);

type CompositionPresetOption = {
	label: string
	value: string
	preset: CompositionPreset
}

const presetOptions = computed(() => compositionPresetsForPartySize(props.slots.length).map((preset) => ({
	label: preset.shorthand,
	value: preset.key,
	preset,
})));

const selectedPresetKey = computed(() => compositionPresetKeyForSlots(props.slots) ?? undefined);
const selectedPreset = computed(() => (
	presetOptions.value.find((option) => option.value === selectedPresetKey.value)?.preset ?? null
));
const canChangePreset = computed(() => (
	presetOptions.value.length > 0
	&& !props.disabled
	&& !isUpdating.value
));

const updatePreset = async (presetKey: string | number | undefined) => {
	if (typeof presetKey !== "string" || !canChangePreset.value || presetKey === selectedPresetKey.value) {
		return;
	}

	isUpdating.value = true;

	try {
		const response = await axios.post(route("groups.dashboard.activities.slot-group-composition-presets.store", {
			group: props.groupSlug,
			activity: props.activityId,
		}), {
			group_key: props.groupKey,
			composition_preset_key: presetKey,
		});

		const updatedSlots = Array.isArray(response.data?.slots)
			? response.data.slots as ActivitySlot[]
			: [];

		if (updatedSlots.length > 0) {
			emit("slotsUpdated", updatedSlots);
		}
	} catch (error) {
		console.error(error);

		toast.add({
			title: t("general.error"),
			description: t("groups.activities.management.roster.composition_update_failed"),
			color: "error",
			icon: "i-lucide-octagon-alert",
		});
	} finally {
		isUpdating.value = false;
	}
};
</script>

<template>
	<USelectMenu
		v-if="presetOptions.length > 0"
		:model-value="selectedPresetKey"
		:items="presetOptions"
		value-key="value"
		size="xs"
		variant="ghost"
		class="w-48"
		:disabled="!canChangePreset"
		:loading="isUpdating"
		:search-input="false"
		:aria-label="t('groups.activities.management.roster.composition_preset')"
		:ui="{
			value: 'min-w-0 flex items-center',
			itemWrapper: 'min-w-0 flex-1',
			itemLabel: 'min-w-0 w-full',
		}"
		@update:model-value="updatePreset"
	>
		<template #default="{ ui }">
			<span
				v-if="selectedPreset"
				data-slot="value"
				:class="ui.value({ class: 'flex min-w-0 items-center' })"
			>
				<CompositionPresetRoleIcons
					:preset="selectedPreset"
					size="sm"
				/>
			</span>
			<span
				v-else
				data-slot="placeholder"
				:class="ui.placeholder({ class: 'truncate text-xs' })"
			>
				{{ t('groups.activities.management.roster.composition_preset') }}
			</span>
		</template>

		<template #item-label="{ item }">
			<div class="flex min-w-0 items-center">
				<CompositionPresetRoleIcons
					:preset="(item as CompositionPresetOption).preset"
					size="md"
				/>
				<span class="sr-only">
					{{ (item as CompositionPresetOption).label }}
				</span>
			</div>
		</template>
	</USelectMenu>
</template>
