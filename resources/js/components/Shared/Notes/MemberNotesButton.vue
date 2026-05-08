<script setup lang="ts">
import type { MemberNoteSummary } from "@/Types/Groups";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	userId: number | null
	noteSummary: MemberNoteSummary
	color?: string
	variant?: string
	size?: string
}>();

const emit = defineEmits<{
	open: [userId: number]
}>();

const { t } = useI18n();

const canOpen = computed(() => props.noteSummary.can_view && props.userId !== null);
const totalCount = computed(() => props.noteSummary.current_group_count + props.noteSummary.shared_count);
const label = computed(() => totalCount.value > 0
	? `${t('general.notes')} (${totalCount.value})`
	: t('general.notes'));

const handleClick = () => {
	if (props.userId === null || !props.noteSummary.can_view) {
		return;
	}

	emit('open', props.userId);
};
</script>

<template>
	<UButton
		v-if="canOpen"
		:color="color ?? 'secondary'"
		:variant="variant ?? 'subtle'"
		:size="size"
		icon="i-lucide-notebook-pen"
		:label="label"
		@click="handleClick"
	/>
</template>
