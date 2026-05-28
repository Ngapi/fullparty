<script setup lang="ts">
import type { ApexOptions } from "apexcharts";
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
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
const total = computed(() => props.segments.reduce((sum, segment) => sum + segment.value, 0));
const visibleSegments = computed(() => props.segments.filter((segment) => segment.value > 0));
const chartSeries = computed(() => visibleSegments.value.map((segment) => segment.value));
const chartLabels = computed(() => visibleSegments.value.map((segment) => segment.label));

const chartOptions = computed<ApexOptions>(() => ({
	chart: {
		type: "donut",
		background: "transparent",
		toolbar: {
			show: false,
		},
	},
	colors: visibleSegments.value.map((_, index) => palette[index % palette.length]),
	dataLabels: {
		enabled: false,
	},
	labels: chartLabels.value,
	legend: {
		show: false,
	},
	plotOptions: {
		pie: {
			donut: {
				size: "68%",
				labels: {
					show: true,
					name: {
						show: true,
						color: "#a3a3a3",
						fontSize: "12px",
						offsetY: 16,
					},
					value: {
						show: true,
						color: "#e5e5e5",
						fontSize: "28px",
						fontWeight: 600,
						offsetY: -12,
						formatter: (value: string) => value,
					},
					total: {
						show: true,
						label: props.totalLabel,
						color: "#a3a3a3",
						fontSize: "12px",
						formatter: () => total.value.toString(),
					},
				},
			},
		},
	},
	stroke: {
		colors: ["rgba(10,10,10,0.7)"],
		width: 2,
	},
	tooltip: {
		theme: "dark",
		y: {
			formatter: (value: number) => value.toString(),
		},
	},
}));
</script>

<template>
	<GroupStatisticsEmptyState
		v-if="total === 0"
		:title="emptyTitle"
		:description="emptyDescription"
		icon="i-lucide-chart-pie"
	/>
	<div v-else class="space-y-5">
		<div class="mx-auto w-48">
			<VueApexCharts
				type="donut"
				height="210"
				width="100%"
				:options="chartOptions"
				:series="chartSeries"
			/>
		</div>
		<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
			<div
				v-for="(segment, index) in visibleSegments"
				:key="segment.key"
				class="flex min-w-0 items-center justify-between gap-3 rounded-sm border border-default bg-muted/10 px-3 py-2 text-sm"
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
