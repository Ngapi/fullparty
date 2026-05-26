<script setup lang="ts">
import { computed } from "vue";
import GroupStatisticsEmptyState from "@/components/Groups/Statistics/GroupStatisticsEmptyState.vue";

const props = defineProps<{
	segments: Array<{
		key: string
		label: string
		value: number
		percent?: number
	}>
	totalLabel: string
	emptyTitle: string
	emptyDescription: string
}>();

const palette = ["#7c3aed", "#059669", "#2563eb", "#d97706", "#dc2626", "#0891b2", "#be185d"];
const total = computed(() => props.segments.reduce((sum, segment) => sum + segment.value, 0));
const visibleSegments = computed(() => props.segments.filter((segment) => segment.value > 0));
const gradient = computed(() => {
	if (total.value === 0) {
		return "#1f2937";
	}

	let cursor = 0;
	const stops = visibleSegments.value.map((segment, index) => {
		const start = cursor;
		cursor += (segment.value / total.value) * 100;
		const color = palette[index % palette.length];

		return `${color} ${start}% ${cursor}%`;
	});

	return `conic-gradient(${stops.join(", ")})`;
});
</script>

<template>
	<GroupStatisticsEmptyState
		v-if="total === 0"
		:title="emptyTitle"
		:description="emptyDescription"
		icon="i-lucide-chart-pie"
	/>
	<div v-else class="grid gap-5 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-center">
		<div class="relative mx-auto size-44 rounded-full" :style="{ background: gradient }">
			<div class="absolute inset-6 flex flex-col items-center justify-center rounded-full bg-background text-center">
				<p class="text-2xl font-semibold text-toned">
					{{ total }}
				</p>
				<p class="text-xs text-muted">
					{{ totalLabel }}
				</p>
			</div>
		</div>
		<div class="space-y-2">
			<div
				v-for="(segment, index) in visibleSegments"
				:key="segment.key"
				class="flex items-center justify-between gap-3 rounded-sm border border-default bg-muted/10 px-3 py-2 text-sm"
			>
				<div class="flex min-w-0 items-center gap-2">
					<span
						class="size-2.5 shrink-0 rounded-full"
						:style="{ backgroundColor: palette[index % palette.length] }"
					/>
					<span class="truncate text-toned">{{ segment.label }}</span>
				</div>
				<span class="shrink-0 text-muted">
					{{ segment.value }} · {{ segment.percent ?? Math.round((segment.value / total) * 100) }}%
				</span>
			</div>
		</div>
	</div>
</template>
