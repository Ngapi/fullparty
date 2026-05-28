<script setup lang="ts">
import type { ApexOptions } from "apexcharts";
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
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

const hasData = computed(() => props.items.some((item) => item.value > 0));

const chartSeries = computed(() => [
	{
		name: "",
		data: props.items.map((item) => item.value),
	},
]);

const chartOptions = computed<ApexOptions>(() => ({
	chart: {
		type: "bar",
		background: "transparent",
		toolbar: {
			show: false,
		},
		zoom: {
			enabled: false,
		},
	},
	colors: ["#a855f7"],
	dataLabels: {
		enabled: false,
	},
	fill: {
		type: "gradient",
		gradient: {
			shade: "dark",
			type: "vertical",
			opacityFrom: 0.9,
			opacityTo: 0.45,
			stops: [0, 100],
		},
	},
	grid: {
		borderColor: "rgba(255,255,255,0.08)",
		strokeDashArray: 3,
		xaxis: {
			lines: {
				show: false,
			},
		},
		yaxis: {
			lines: {
				show: true,
			},
		},
	},
	plotOptions: {
		bar: {
			borderRadius: 3,
			borderRadiusApplication: "end",
			columnWidth: "58%",
		},
	},
	states: {
		hover: {
			filter: {
				type: "lighten",
				value: 0.08,
			},
		},
	},
	tooltip: {
		theme: "dark",
		custom: ({ dataPointIndex, series }) => {
			const item = props.items[dataPointIndex];
			const value = series[0]?.[dataPointIndex] ?? 0;

			return `<div class="px-3 py-2 text-sm">
				<div class="font-semibold">${item?.label ?? ""}</div>
				<div class="text-neutral-300">${item?.secondaryLabel ?? value}</div>
			</div>`;
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
			rotate: 0,
			style: {
				colors: "#a3a3a3",
				fontSize: "11px",
			},
			trim: true,
		},
	},
	yaxis: {
		labels: {
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
		icon="i-lucide-chart-column"
	/>
	<div v-else class="min-h-72">
		<VueApexCharts
			type="bar"
			height="288"
			width="100%"
			:options="chartOptions"
			:series="chartSeries"
		/>
	</div>
</template>
