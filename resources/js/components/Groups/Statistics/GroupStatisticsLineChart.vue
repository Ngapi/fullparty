<script setup lang="ts">
import type { ApexOptions } from "apexcharts";
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
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

const palette = [
	"#7c3aed",
	"#059669",
	"#2563eb",
	"#d97706",
	"#dc2626",
	"#0891b2",
	"#be185d",
	"#65a30d",
	"#9333ea",
	"#0d9488",
	"#ea580c",
	"#4f46e5",
	"#e11d48",
	"#16a34a",
	"#0284c7",
	"#ca8a04",
	"#db2777",
	"#64748b",
];
const hasData = computed(() => props.series.some((series) => series.points.some((point) => point > 0)));

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

const latestLabel = (item: { latestLabel: string | null, latestValue: number | null }) => (
	item.latestLabel && item.latestValue !== null
		? `${item.latestLabel}: ${item.latestValue}`
		: ""
);

const chartSeries = computed(() => props.series.map((entry) => ({
	name: entry.label,
	data: entry.points,
})));

const chartOptions = computed<ApexOptions>(() => ({
	chart: {
		type: "line",
		background: "transparent",
		toolbar: {
			show: false,
		},
		zoom: {
			enabled: false,
		},
	},
	colors: props.series.map((_, index) => palette[index % palette.length]),
	dataLabels: {
		enabled: false,
	},
	fill: {
		opacity: 1,
	},
	grid: {
		borderColor: "rgba(255,255,255,0.08)",
		strokeDashArray: 3,
	},
	legend: {
		show: false,
	},
	markers: {
		size: 4,
		colors: ["#0a0a0a"],
		strokeColors: props.series.map((_, index) => palette[index % palette.length]),
		strokeWidth: 2,
		hover: {
			size: 5,
		},
	},
	stroke: {
		curve: "smooth",
		lineCap: "round",
		width: 2.5,
	},
	tooltip: {
		theme: "dark",
		x: {
			formatter: (_value, options) => props.labels[options.dataPointIndex] ?? "",
		},
		y: {
			formatter: (value: number) => formatCount(value),
		},
	},
	xaxis: {
		axisBorder: {
			show: false,
		},
		axisTicks: {
			show: false,
		},
		categories: props.labels,
		labels: {
			style: {
				colors: "#a3a3a3",
				fontSize: "11px",
			},
		},
		tooltip: {
			enabled: false,
		},
	},
	yaxis: {
		labels: {
			formatter: (value: number) => Math.round(value).toString(),
			style: {
				colors: "#a3a3a3",
				fontSize: "11px",
			},
		},
		min: 0,
	},
}));
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
			<VueApexCharts
				type="line"
				height="288"
				width="100%"
				:options="chartOptions"
				:series="chartSeries"
			/>
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
