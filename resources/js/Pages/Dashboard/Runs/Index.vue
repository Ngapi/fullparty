<script setup lang="ts">
import type { RunDiscoveryDiscoverResponse, RunDiscoveryFilterState, RunDiscoveryPageProps, RunDiscoverySort } from "../../../Types/RunDiscovery";
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
const currentSort = ref<RunDiscoverySort>("starting_soonest");
const pendingSavedItemIds = ref<number[]>([]);
let discoveryRequestCounter = 0;
let scheduledDiscoveryTimeout: ReturnType<typeof setTimeout> | null = null;

function syncSavedStateLocally(itemId: number, isSaved: boolean) {
	const savedOnly = activeFilters.value?.saved_only ?? false;

	if (!isSaved && savedOnly) {
		discoveredRunItems.value = discoveredRunItems.value.filter((item) => item.id !== itemId);
		discoveredRunIds.value = discoveredRunIds.value.filter((id) => id !== itemId);

		const nextTotal = Math.max(0, paginationMeta.value.total - 1);
		const nextLastPage = Math.max(1, Math.ceil(nextTotal / paginationMeta.value.per_page));

		paginationMeta.value = {
			...paginationMeta.value,
			total: nextTotal,
			last_page: nextLastPage,
			current_page: Math.min(paginationMeta.value.current_page, nextLastPage),
		};
		currentPage.value = paginationMeta.value.current_page;

		return;
	}

	discoveredRunItems.value = discoveredRunItems.value.map((item) => (
		item.id === itemId
			? { ...item, is_saved: isSaved }
			: item
	));
}

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
				sort: currentSort.value,
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

function handleSortChange(sort: RunDiscoverySort) {
	if (sort === currentSort.value) {
		return;
	}

	currentSort.value = sort;
	currentPage.value = 1;
	scheduleDiscoveryFetch();
}

async function handleToggleSaved(item: RunDiscoveryDiscoverResponse["items"][number]) {
	if (pendingSavedItemIds.value.includes(item.id)) {
		return;
	}

	pendingSavedItemIds.value = [...pendingSavedItemIds.value, item.id];

	try {
		if (item.is_saved) {
			await axios.delete(route("dashboard.runs.unsave", {
				activity: item.id,
			}));

			syncSavedStateLocally(item.id, false);
		} else {
			await axios.post(route("dashboard.runs.save", {
				activity: item.id,
			}));

			syncSavedStateLocally(item.id, true);
		}
	} finally {
		pendingSavedItemIds.value = pendingSavedItemIds.value.filter((pendingId) => pendingId !== item.id);
	}
}

onBeforeUnmount(() => {
	if (scheduledDiscoveryTimeout !== null) {
		clearTimeout(scheduledDiscoveryTimeout);
	}
});
</script>

<template>
	<Head :title="`${t('runs.discovery.title')} -`" />

	<div class="flex min-h-0 flex-col gap-6 lg:h-[calc(100dvh-10rem)] lg:flex-row lg:overflow-hidden">
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
			:pending-saved-item-ids="pendingSavedItemIds"
			@page-change="handlePageChange"
			@sort-change="handleSortChange"
			@toggle-saved="handleToggleSaved"
		/>
	</div>
</template>
