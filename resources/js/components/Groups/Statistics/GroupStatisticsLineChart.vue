<script setup lang="ts">
import { computed } from "vue";
import GroupStatisticsEmptyState from "@/components/Groups/Statistics/GroupStatisticsEmptyState.vue";
import type { GroupStatisticsLoadoutSeries } from "@/Types/GroupStatistics";

const props = defineProps<{
	months: string[]
	labels: string[]
	series: GroupStatisticsLoadoutSeries[]
	emptyTitle: string
	emptyDescription: string
	countLabel: (count: number) => string
}>();

const palette = ["#7c3aed", "#059669", "#2563eb", "#d97706", "#dc2626"];
const chartWidth = 112;
const chartHeight = 58;
const leftPadding = 11;
const rightPadding = 5;
const topPadding = 5;
const plotBottom = 41;
const xLabelY = 52;
const maxValue = computed(() => Math.max(1, ...props.series.flatMap((series) => series.points)));
const hasData = computed(() => props.series.some((series) => series.points.some((point) => point > 0)));

const xPosition = (index: number) => {
	const usableWidth = chartWidth - leftPadding - rightPadding;
	const lastIndex = Math.max(1, props.months.length - 1);

	return leftPadding + (index / lastIndex) * usableWidth;
};

const yPosition = (value: number) => {
	const usableHeight = plotBottom - topPadding;

	return topPadding + usableHeight - (value / maxValue.value) * usableHeight;
};

const pointsForSeries = (points: number[]) => props.months.map((_, index) => ({
	index,
	value: points[index] ?? 0,
	x: xPosition(index),
	y: yPosition(points[index] ?? 0),
}));

const pointString = (points: number[]) => pointsForSeries(points)
	.map((point) => `${point.x.toFixed(2)},${point.y.toFixed(2)}`)
	.join(" ");

const axisLabels = computed(() => {
	const lastIndex = props.labels.length - 1;

	return props.labels
		.map((label, index) => ({
			key: `${props.months[index]}-${label}`,
			label,
			x: xPosition(index),
			show: props.labels.length <= 7 || index === 0 || index === lastIndex || index % 2 === 0,
		}))
		.filter((label) => label.show);
});

const latestActivePoint = (points: number[]) => {
	for (let index = points.length - 1; index >= 0; index -= 1) {
		if ((points[index] ?? 0) > 0) {
			return {
				index,
				value: points[index] ?? 0,
				label: props.labels[index] ?? props.months[index] ?? "",
			};
		}
	}

	return null;
};

const legendItems = computed(() => props.series.map((entry, index) => {
	const latest = latestActivePoint(entry.points);

	return {
		...entry,
		color: palette[index % palette.length],
		total: entry.points.reduce((sum, point) => sum + point, 0),
		latestLabel: latest?.label ?? null,
		latestValue: latest?.value ?? null,
	};
}));

const formatCount = (count: number) => (
	typeof props.countLabel === "function"
		? props.countLabel(count)
		: count.toString()
);

const pointTitle = (entry: GroupStatisticsLoadoutSeries, point: { index: number, value: number }) => [
	entry.label,
	props.labels[point.index] ?? props.months[point.index] ?? "",
	formatCount(point.value),
]
	.filter(Boolean)
	.join(" · ");

const latestLabel = (item: { latestLabel: string | null, latestValue: number | null }) => (
	item.latestLabel && item.latestValue !== null
		? `${item.latestLabel}: ${item.latestValue}`
		: ""
);

const strokeDashArray = (index: number) => {
	if (index < 3) {
		return undefined;
	}

	return index % 2 === 0 ? "2.5 1.6" : "1 1.4";
};

const yAxisLabels = computed(() => [
	{
		key: "max",
		label: maxValue.value.toString(),
		y: yPosition(maxValue.value),
	},
	{
		key: "zero",
		label: "0",
		y: yPosition(0),
	},
]);
</script>

<template>
	<GroupStatisticsEmptyState
		v-if="!hasData"
		:title="emptyTitle"
		:description="emptyDescription"
		icon="i-lucide-chart-line"
	/>
	<div v-else class="space-y-4">
		<div class="rounded-sm border border-default bg-muted/10 px-3 py-3">
			<svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="h-64 w-full overflow-visible">
				<line :x1="leftPadding" :y1="plotBottom" :x2="chartWidth - rightPadding" :y2="plotBottom" class="stroke-default" stroke-width="0.4" />
				<line :x1="leftPadding" :y1="topPadding" :x2="leftPadding" :y2="plotBottom" class="stroke-default" stroke-width="0.4" />
				<line
					:x1="leftPadding"
					:y1="yPosition(maxValue)"
					:x2="chartWidth - rightPadding"
					:y2="yPosition(maxValue)"
					class="stroke-default opacity-60"
					stroke-width="0.25"
					stroke-dasharray="1 1.6"
				/>
				<text
					v-for="label in yAxisLabels"
					:key="label.key"
					x="8"
					:y="label.y + 1.2"
					text-anchor="end"
					class="fill-current text-[0.18rem] text-muted"
				>
					{{ label.label }}
				</text>
				<polyline
					v-for="(entry, index) in series"
					:key="entry.key"
					:points="pointString(entry.points)"
					fill="none"
					:stroke="palette[index % palette.length]"
					stroke-width="1.8"
					:stroke-dasharray="strokeDashArray(index)"
					stroke-linecap="round"
					stroke-linejoin="round"
				/>
				<g
					v-for="(entry, seriesIndex) in series"
					:key="`${entry.key}-points`"
				>
					<circle
						v-for="point in pointsForSeries(entry.points).filter((item) => item.value > 0)"
						:key="`${entry.key}-${point.index}`"
						:cx="point.x"
						:cy="point.y"
						r="1.05"
						class="fill-background"
						:stroke="palette[seriesIndex % palette.length]"
						stroke-width="0.8"
					>
						<title>{{ pointTitle(entry, point) }}</title>
					</circle>
				</g>
				<text
					v-for="label in axisLabels"
					:key="label.key"
					:x="label.x"
					:y="xLabelY"
					text-anchor="middle"
					class="fill-current text-[0.2rem] text-muted"
				>
					{{ label.label }}
				</text>
			</svg>
		</div>
		<div class="grid gap-2 sm:grid-cols-2">
			<div
				v-for="entry in legendItems"
				:key="entry.key"
				class="flex items-center justify-between gap-3 rounded-sm border border-default bg-muted/10 px-3 py-2"
			>
				<div class="flex min-w-0 items-center gap-2">
					<span
						class="h-1 w-6 shrink-0 rounded-full"
						:style="{ backgroundColor: entry.color }"
					/>
					<img
						v-if="entry.icon_url"
						:src="entry.icon_url"
						:alt="entry.label"
						class="size-6 shrink-0 rounded-sm object-contain"
					>
					<span class="truncate text-sm font-medium text-toned">{{ entry.label }}</span>
				</div>
				<div class="shrink-0 text-right">
					<p class="text-sm font-semibold text-toned">
						{{ formatCount(entry.total) }}
					</p>
					<p v-if="latestLabel(entry)" class="text-xs text-muted">
						{{ latestLabel(entry) }}
					</p>
				</div>
			</div>
		</div>
	</div>
</template>
