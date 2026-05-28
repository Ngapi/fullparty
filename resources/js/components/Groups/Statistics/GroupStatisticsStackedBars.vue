<script setup lang="ts">
import type { ApexOptions } from "apexcharts";
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
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
const hasData = computed(() => props.items.some((item) => item.total > 0));
const chartHeight = computed(() => Math.max(260, props.items.length * 48 + 80));

const chartSeries = computed(() => props.segments.map((segment) => ({
	name: segment.label,
	data: props.items.map((item) => item.statuses[segment.key] ?? 0),
})));

const chartOptions = computed<ApexOptions>(() => ({
	chart: {
		type: "bar",
		background: "transparent",
		stacked: true,
		toolbar: {
			show: false,
		},
		zoom: {
			enabled: false,
		},
	},
	colors: props.segments.map((_, index) => palette[index % palette.length]),
	dataLabels: {
		enabled: false,
	},
	grid: {
		borderColor: "rgba(255,255,255,0.08)",
		strokeDashArray: 3,
		xaxis: {
			lines: {
				show: true,
			},
		},
		yaxis: {
			lines: {
				show: false,
			},
		},
	},
	legend: {
		fontSize: "12px",
		labels: {
			colors: "#a3a3a3",
		},
		markers: {
			size: 5,
			strokeWidth: 0,
		},
		position: "top",
	},
	plotOptions: {
		bar: {
			borderRadius: 3,
			horizontal: true,
			barHeight: "58%",
		},
	},
	stroke: {
		colors: ["rgba(10,10,10,0.25)"],
		width: 1,
	},
	tooltip: {
		theme: "dark",
		y: {
			formatter: (value: number) => value.toString(),
		},
	},
	xaxis: {
		axisBorder: {
			show: false,
		},
		axisTicks: {
			show: false,
		},
		categories: props.items.map((item) => item.label),
		labels: {
			style: {
				colors: "#a3a3a3",
				fontSize: "11px",
			},
		},
	},
	yaxis: {
		labels: {
			style: {
				colors: "#a3a3a3",
				fontSize: "12px",
			},
		},
	},
}));
</script>

<template>
	<GroupStatisticsEmptyState
		v-if="!hasData"
		:title="emptyTitle"
		:description="emptyDescription"
		icon="i-lucide-chart-bar-stacked"
	/>
	<div v-else>
		<VueApexCharts
			type="bar"
			width="100%"
			:height="chartHeight"
			:options="chartOptions"
			:series="chartSeries"
		/>
	</div>
</template>
