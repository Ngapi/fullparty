<script setup lang="ts">
import type { RunDiscoveryDiscoverResponse, RunDiscoveryFilterState, RunDiscoveryPageProps } from "../../../Types/RunDiscovery";
import axios from "axios";
import { onBeforeUnmount, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";
import RunDiscoveryFilterPanel from "@/components/Runs/RunDiscoveryFilterPanel.vue";
import RunDiscoveryResultsPanel from "@/components/Runs/RunDiscoveryResultsPanel.vue";

const props = defineProps<RunDiscoveryPageProps>();

const { t } = useI18n();

const discoveredRunIds = ref<number[]>([]);
const discoveredRunItems = ref<RunDiscoveryDiscoverResponse["items"]>([]);
const paginationMeta = ref<RunDiscoveryDiscoverResponse["meta"]>({
	current_page: 1,
	last_page: 1,
	per_page: 10,
	total: 0,
});
const activeFilters = ref<RunDiscoveryFilterState | null>(null);
const isLoading = ref(true);
const currentPage = ref(1);
let discoveryRequestCounter = 0;
let scheduledDiscoveryTimeout: ReturnType<typeof setTimeout> | null = null;

async function fetchRunIds() {
	if (activeFilters.value === null) {
		return;
	}

	const requestId = ++discoveryRequestCounter;
	isLoading.value = true;

	try {
		const response = await axios.get<RunDiscoveryDiscoverResponse>(route("dashboard.runs.discover"), {
			params: {
				...activeFilters.value,
				page: currentPage.value,
			},
		});

		if (requestId !== discoveryRequestCounter) {
			return;
		}

		discoveredRunIds.value = response.data.ids;
		discoveredRunItems.value = response.data.items;
		paginationMeta.value = response.data.meta;
		currentPage.value = response.data.meta.current_page;
	} catch {
		// Keep the last successful discovery payload in place when the request fails.
	} finally {
		if (requestId === discoveryRequestCounter) {
			isLoading.value = false;
		}
	}
}

function scheduleDiscoveryFetch() {
	if (scheduledDiscoveryTimeout !== null) {
		clearTimeout(scheduledDiscoveryTimeout);
	}

	scheduledDiscoveryTimeout = setTimeout(() => {
		scheduledDiscoveryTimeout = null;
		void fetchRunIds();
	}, 250);
}

function handleFiltersChange(filters: RunDiscoveryFilterState) {
	activeFilters.value = filters;
	currentPage.value = 1;
	scheduleDiscoveryFetch();
}

function handlePageChange(page: number) {
	if (page === currentPage.value) {
		return;
	}

	currentPage.value = page;
	void fetchRunIds();
}

onBeforeUnmount(() => {
	if (scheduledDiscoveryTimeout !== null) {
		clearTimeout(scheduledDiscoveryTimeout);
	}
});
</script>

<template>
	<Head :title="`${t('runs.discovery.title')} -`" />

	<div class="flex h-[calc(100dvh-10rem)] min-h-0 flex-row gap-6 overflow-hidden">
		<RunDiscoveryFilterPanel
			:lookups="props.lookups"
			@filters-change="handleFiltersChange"
		/>
		<RunDiscoveryResultsPanel
			:items="discoveredRunItems"
			:result-count="paginationMeta.total"
			:current-page="paginationMeta.current_page"
			:total-pages="paginationMeta.last_page"
			:loading="isLoading"
			@page-change="handlePageChange"
		/>
	</div>
</template>
