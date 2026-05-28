<script setup lang="ts">
import type { ApexOptions } from "apexcharts";
import type {
	GroupDiscoveryContentItem,
	GroupDiscoveryContentStatusCount,
	GroupDiscoveryContentSummary,
} from "@/Types/Groups";
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { useI18n } from "vue-i18n";
import { formatRelativeTime } from "@/utils/formatRelativeTime";

const props = defineProps<{
	summary: GroupDiscoveryContentSummary
	items: GroupDiscoveryContentItem[]
}>();

const { locale, t } = useI18n();

const totalRuns = computed(() => props.summary.total_runs);

const statusItems = computed(() => props.summary.status_breakdown.map((item) => ({
	...item,
	label: t(`groups.dashboard.content.statuses.${item.status}`),
	color: resolveStatusColor(item.status),
})));

const visibleStatusItems = computed(() => statusItems.value.filter((item) => item.count > 0));
const chartSeries = computed(() => visibleStatusItems.value.map((item) => item.count));
const chartLabels = computed(() => visibleStatusItems.value.map((item) => item.label));
const chartColors = computed(() => visibleStatusItems.value.map((item) => item.color));
const hasStatusData = computed(() => chartSeries.value.length > 0);

const chartOptions = computed<ApexOptions>(() => ({
	chart: {
		type: "donut",
		background: "transparent",
		toolbar: {
			show: false,
		},
	},
	colors: chartColors.value,
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
						color: "#ffffff",
						fontSize: "28px",
						fontWeight: 600,
						offsetY: -12,
						formatter: (value: string) => value,
					},
					total: {
						show: true,
						label: t("groups.dashboard.content.visible_runs"),
						color: "#a3a3a3",
						fontSize: "12px",
						formatter: () => totalRuns.value.toString(),
					},
				},
			},
		},
	},
	stroke: {
		colors: ["rgba(23,20,25,0.88)"],
		width: 2,
	},
	tooltip: {
		theme: "dark",
		y: {
			formatter: (value: number) => value.toString(),
		},
	},
}));

function resolveStatusColor(status: GroupDiscoveryContentStatusCount["status"]) {
	switch (status) {
		case "draft":
			return "#7dd3fc";
		case "scheduled":
			return "#c4b5fd";
		case "active":
			return "#fcd34d";
		case "complete":
			return "#6ee7b7";
		case "cancelled":
			return "#fda4af";
	}
}

function formatCountLabel(count: number) {
	return count === 1
		? t("groups.dashboard.content.run_singular")
		: t("groups.dashboard.content.run_plural");
}

function formatRunTime(value: string | null) {
	return formatRelativeTime(
		value,
		locale.value,
		t("groups.dashboard.labels.just_now"),
		t("groups.dashboard.labels.not_available"),
	);
}
</script>

<template>
	<section class="">
		<div class="overflow-hidden ">
			<div class="flex flex-col gap-4 px-5 sm:flex-row sm:items-start sm:justify-between">
				<div class="flex flex-col gap-1">
					<h2 class="text-lg font-semibold text-white">
						{{ t("groups.dashboard.content.title") }}
					</h2>
					<p class="max-w-2xl text-sm leading-6 text-white/62">
						{{ t("groups.dashboard.content.subtitle") }}
					</p>
				</div>

				<UBadge
					color="neutral"
					variant="subtle"
					:label="t('groups.dashboard.content.total_runs', { count: summary.total_runs })"
				/>
			</div>

			<div class="py-5">
				<div class="border border-white/10 bg-white/[0.03] p-4 xl:hidden">
					<div class="flex items-start justify-between gap-4">
						<div>
							<p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/44">
								{{ t("groups.dashboard.content.visible_runs") }}
							</p>
							<p class="mt-2 text-3xl font-semibold text-white">
								{{ summary.total_runs }}
							</p>
						</div>
						<UIcon name="i-lucide-chart-pie" class="size-5 text-white/42" />
					</div>

					<div v-if="hasStatusData" class="mx-auto mt-2 max-w-64">
						<VueApexCharts
							type="donut"
							height="220"
							width="100%"
							:options="chartOptions"
							:series="chartSeries"
						/>
					</div>
					<div v-else class="mt-5 flex h-36 items-center justify-center border border-white/8 bg-neutral-950/45 text-sm text-white/52">
						{{ t("groups.dashboard.content.empty") }}
					</div>

					<div class="mt-3 flex flex-row flex-wrap gap-x-4 gap-y-3">
						<div
							v-for="item in statusItems"
							:key="item.status"
							class="flex min-w-24 items-center gap-2 text-sm"
						>
							<span
								class="size-2.5 shrink-0 rounded-full"
								:style="{ backgroundColor: item.color }"
							/>
							<span class="min-w-0 text-white/58">
								{{ item.label }}
							</span>
							<span class="shrink-0 text-white/82">
								{{ item.count }}
							</span>
						</div>
					</div>
				</div>

				<div class="hidden items-stretch justify-evenly gap-4 xl:flex">
					<div class="w-full border border-white/10 bg-white/[0.03] p-5">
					<p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/44">
						{{ t("groups.dashboard.content.visible_runs") }}
					</p>
					<p class="mt-3 text-4xl font-semibold text-white">
						{{ summary.total_runs }}
					</p>
					</div>

					<div class="flex w-full items-center gap-5 overflow-hidden border border-white/10 bg-white/[0.03] px-5">
						<div v-if="hasStatusData" class="w-52 shrink-0">
							<VueApexCharts
								type="donut"
								height="190"
								width="100%"
								:options="chartOptions"
								:series="chartSeries"
							/>
						</div>
						<div v-else class="flex h-36 w-52 shrink-0 items-center justify-center border border-white/8 bg-neutral-950/45 text-sm text-white/52">
							{{ t("groups.dashboard.content.empty") }}
						</div>

						<div class="flex min-w-0 flex-1 flex-row flex-wrap gap-x-5 gap-y-3 py-4">
							<div
								v-for="item in statusItems"
								:key="item.status"
								class="flex min-w-32 items-center gap-3"
							>
								<span
									class="size-2.5 shrink-0 rounded-full"
									:style="{ backgroundColor: item.color }"
								/>
								<div class="min-w-0">
									<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/44">
										{{ item.label }}
									</p>
									<p class="mt-1 text-sm text-white/76">
										{{ item.count }}
									</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="">
				<div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
					<p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/44">
						{{ t("groups.dashboard.content.library") }}
					</p>
					<p class="text-xs text-white/52">
						{{ t("groups.dashboard.content.library_hint") }}
					</p>
				</div>

				<div v-if="items.length > 0" class="grid gap-4 xl:grid-cols-2">
					<article
						v-for="item in items"
						:key="item.key"
						class="overflow-hidden border border-white/10 bg-white/[0.03]"
					>
						<div class="relative h-32 overflow-hidden border-b border-white/10 bg-neutral-900">
							<img
								v-if="item.activity_image_url"
								:src="item.activity_image_url"
								:alt="item.activity_name"
								class="absolute inset-0 size-full object-cover"
							>
							<div
								v-else
								class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(123,97,153,0.34),transparent_46%),radial-gradient(circle_at_center_right,rgba(84,136,184,0.28),transparent_38%),linear-gradient(180deg,#201c24_0%,#151217_100%)]"
							/>
							<div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/45 to-transparent" />

							<div class="absolute inset-x-0 bottom-0 p-4">
								<div class="flex items-end justify-between gap-3">
									<div class="min-w-0">
										<p class="text-sm font-semibold text-white break-words [overflow-wrap:anywhere]">
											{{ item.activity_name }}
										</p>
										<p class="mt-1 text-xs uppercase tracking-[0.12em] text-white/62">
											{{ item.total_runs }} {{ formatCountLabel(item.total_runs) }}
										</p>
									</div>
								</div>
							</div>
						</div>

						<div class="space-y-4 p-4">
							<div class="flex flex-wrap gap-2">
								<UBadge
									color="neutral"
									variant="soft"
									:label="t('groups.dashboard.content.completed', { count: item.completed_runs })"
								/>
								<UBadge
									color="primary"
									variant="soft"
									:label="t('groups.dashboard.content.active', { count: item.active_runs })"
								/>
							</div>

							<div class="grid gap-3 sm:grid-cols-2">
								<div class="border border-white/8 bg-neutral-950/45 px-3 py-3">
									<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/42">
										{{ t("groups.dashboard.content.last_run") }}
									</p>
									<p class="mt-2 text-sm text-white/78">
										{{ item.last_run_at ? formatRunTime(item.last_run_at) : t("groups.dashboard.labels.not_available") }}
									</p>
								</div>

								<div class="border border-white/8 bg-neutral-950/45 px-3 py-3">
									<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/42">
										{{ t("groups.dashboard.content.next_run") }}
									</p>
									<p class="mt-2 text-sm text-white/78">
										{{ item.next_run_at ? formatRunTime(item.next_run_at) : t("groups.dashboard.labels.not_available") }}
									</p>
								</div>
							</div>
						</div>
					</article>
				</div>

				<p v-else class="text-sm text-white/58">
					{{ t("groups.dashboard.content.empty") }}
				</p>
			</div>
		</div>
	</section>
</template>
