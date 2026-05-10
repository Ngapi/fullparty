<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { ActivitySlot, ActivitySlotCompositionHint } from "@/Types/ActivityRoster";
import {
	primaryCompositionHintRole,
	sortedCompositionHints,
} from "@/utils/activityCompositionHints";

const props = defineProps<{
	slot: ActivitySlot
}>();

const { t } = useI18n();

const hintLabel = (hint: ActivitySlotCompositionHint): string => {
	if (hint.type === "role") {
		return ["tank", "healer", "dps"].includes(hint.key)
			? t(`groups.activities.management.roster.roles.${hint.key}`)
			: hint.key.toUpperCase();
	}

	return hint.character_class?.shorthand || hint.key.toUpperCase();
};

const hintLabels = computed(() => {
	const labels = sortedCompositionHints(props.slot)
		.map(hintLabel)
		.filter((label) => label !== "");

	return [...new Set(labels)];
});

const primaryRole = computed(() => primaryCompositionHintRole(props.slot));
const badgeColor = computed((): "info" | "success" | "error" | "neutral" => (
	primaryRole.value === "tank"
		? "info"
		: primaryRole.value === "healer"
			? "success"
			: primaryRole.value === "dps"
				? "error"
				: "neutral"
));
const label = computed(() => (
	hintLabels.value.length > 0
		? hintLabels.value.join(" / ")
		: null
));
</script>

<template>
	<UBadge
		v-if="label"
		:label="label"
		:color="badgeColor"
		variant="ghost"
		class="max-w-full truncate opacity-75"
	/>
</template>
