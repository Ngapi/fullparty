<script setup lang="ts">
import axios from "axios";
import { ref } from "vue";
import { useToast } from "@nuxt/ui/composables";
import { useI18n } from "vue-i18n";
import { route } from "ziggy-js";
import type { ActivitySlot } from "@/Types/ActivityRoster";

const props = defineProps<{
	groupSlug: string
	activityId: number
	sourceGroupKey: string
	disabled?: boolean
}>();

const emit = defineEmits<{
	slotsUpdated: [slots: ActivitySlot[]]
}>();

const { t } = useI18n();
const toast = useToast();
const isUpdating = ref(false);

const applyToAll = async () => {
	if (props.disabled || isUpdating.value) {
		return;
	}

	isUpdating.value = true;

	try {
		const response = await axios.post(route("groups.dashboard.activities.slot-group-composition-presets.apply-to-all", {
			group: props.groupSlug,
			activity: props.activityId,
		}), {
			source_group_key: props.sourceGroupKey,
		});

		const updatedSlots = Array.isArray(response.data?.slots)
			? response.data.slots as ActivitySlot[]
			: [];

		if (updatedSlots.length > 0) {
			emit("slotsUpdated", updatedSlots);
		}
	} catch {
		toast.add({
			title: t("general.error"),
			description: t("groups.activities.management.roster.apply_composition_to_all_failed"),
			color: "error",
			icon: "i-lucide-octagon-alert",
		});
	} finally {
		isUpdating.value = false;
	}
};
</script>

<template>
	<UButton
		color="neutral"
		variant="ghost"
		size="xs"
		icon="i-lucide-copy-check"
		:label="t('groups.activities.management.roster.apply_composition_to_all')"
		:loading="isUpdating"
		:disabled="disabled || isUpdating"
		@click="applyToAll"
	/>
</template>
