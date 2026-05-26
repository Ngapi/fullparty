<script setup lang="ts">
import { computed } from "vue";
import GroupStatisticsEmptyState from "@/components/Groups/Statistics/GroupStatisticsEmptyState.vue";

type SegmentDefinition = {
	key: string
	label: string
}

const props = defineProps<{
	items: Array<{
		key: string
		label: string
		total: number
		statuses: Record<string, number>
	}>
	segments: SegmentDefinition[]
	emptyTitle: string
	emptyDescription: string
}>();

const palette = ["#7c3aed", "#059669", "#dc2626", "#d97706", "#64748b"];
const maxTotal = computed(() => Math.max(1, ...props.items.map((item) => item.total)));
const hasData = computed(() => props.items.some((item) => item.total > 0));
</script>

<template>
	<GroupStatisticsEmptyState
		v-if="!hasData"
		:title="emptyTitle"
		:description="emptyDescription"
		icon="i-lucide-chart-bar-stacked"
	/>
	<div v-else class="space-y-4">
		<div class="flex flex-wrap gap-3">
			<div
				v-for="(segment, index) in segments"
				:key="segment.key"
				class="flex items-center gap-2 text-xs text-muted"
			>
				<span
					class="size-2.5 rounded-full"
					:style="{ backgroundColor: palette[index % palette.length] }"
				/>
				<span>{{ segment.label }}</span>
			</div>
		</div>
		<div class="space-y-3">
			<div
				v-for="item in items"
				:key="item.key"
				class="grid grid-cols-[4rem_minmax(0,1fr)_2.5rem] items-center gap-3 text-sm"
			>
				<p class="truncate text-muted">
					{{ item.label }}
				</p>
				<div class="flex h-7 overflow-hidden rounded-sm bg-muted/25">
					<div
						v-for="(segment, index) in segments"
						:key="`${item.key}-${segment.key}`"
						class="h-full"
						:style="{
							width: `${((item.statuses[segment.key] ?? 0) / maxTotal) * 100}%`,
							backgroundColor: palette[index % palette.length],
						}"
					/>
				</div>
				<p class="text-right font-semibold text-toned">
					{{ item.total }}
				</p>
			</div>
		</div>
	</div>
</template>
