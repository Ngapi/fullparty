<script setup lang="ts">
import { computed } from "vue";
import GroupStatisticsEmptyState from "@/components/Groups/Statistics/GroupStatisticsEmptyState.vue";

const props = defineProps<{
	items: Array<{
		key: string
		label: string
		value: number
		secondaryLabel?: string
	}>
	emptyTitle: string
	emptyDescription: string
}>();

const maxValue = computed(() => Math.max(1, ...props.items.map((item) => item.value)));
</script>

<template>
	<GroupStatisticsEmptyState
		v-if="items.length === 0"
		:title="emptyTitle"
		:description="emptyDescription"
		icon="i-lucide-chart-column"
	/>
	<div v-else class="flex min-h-72 items-end gap-2 overflow-x-auto pb-2">
		<div
			v-for="item in items"
			:key="item.key"
			class="flex min-w-12 flex-1 flex-col items-center justify-end gap-2"
		>
			<UTooltip :text="item.secondaryLabel ?? item.label">
				<div class="flex h-56 w-full items-end rounded-sm bg-muted/20 px-1.5 py-1.5">
					<div
						class="w-full rounded-sm bg-primary/75 transition hover:bg-primary"
						:style="{ height: `${Math.max(6, (item.value / maxValue) * 100)}%` }"
					/>
				</div>
			</UTooltip>
			<div class="w-full text-center">
				<p class="text-xs font-semibold text-toned">
					{{ item.value }}
				</p>
				<p class="truncate text-[0.68rem] text-muted">
					{{ item.label }}
				</p>
			</div>
		</div>
	</div>
</template>
