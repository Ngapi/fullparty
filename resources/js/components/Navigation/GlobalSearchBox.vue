<script setup lang="ts">
import type { GlobalSearchResponse, GlobalSearchResult } from "@/Types/GlobalSearch";
import axios from "axios";
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";
import { createDateTimeFormatter } from "@/utils/dateTimeFormat";
import { useTimeDisplayMode } from "@/composables/useTimeDisplayMode";

const props = withDefaults(defineProps<{
	autofocus?: boolean
	dropdownMode?: "floating" | "static"
}>(), {
	autofocus: false,
	dropdownMode: "floating",
});

const emit = defineEmits<{
	selected: []
}>();

const { t, locale } = useI18n({ useScope: "global" });
const { withDisplayTimeZone } = useTimeDisplayMode();

const emptyResults = (): GlobalSearchResponse => ({
	runs: [],
	groups: [],
	activities: [],
});

const query = ref("");
const results = ref<GlobalSearchResponse>(emptyResults());
const isFocused = ref(false);
const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let closeTimeout: ReturnType<typeof setTimeout> | null = null;
let searchRequestCounter = 0;

const trimmedQuery = computed(() => query.value.trim());
const canSearch = computed(() => trimmedQuery.value.length >= 2);
const hasAnyResults = computed(() => (
	results.value.runs.length > 0
	|| results.value.groups.length > 0
	|| results.value.activities.length > 0
));

const resultSections = computed(() => [
	{
		key: "runs",
		title: t("navigation.topbar.global_search.sections.runs"),
		icon: "i-lucide-calendar-days",
		results: results.value.runs,
	},
	{
		key: "groups",
		title: t("navigation.topbar.global_search.sections.groups"),
		icon: "i-lucide-users",
		results: results.value.groups,
	},
	{
		key: "activities",
		title: t("navigation.topbar.global_search.sections.activities"),
		icon: "i-lucide-swords",
		results: results.value.activities,
	},
]);

const isSurfaceVisible = computed(() => {
	if (props.dropdownMode === "static") {
		return trimmedQuery.value.length > 0;
	}

	return isFocused.value && trimmedQuery.value.length > 0;
});

const surfaceClasses = computed(() => props.dropdownMode === "static"
	? "mt-4 w-full"
	: "absolute left-0 top-full z-50 mt-2 w-[min(44rem,calc(100vw-2rem))]");

const clearSearchTimeout = () => {
	if (searchTimeout !== null) {
		clearTimeout(searchTimeout);
		searchTimeout = null;
	}
};

const clearCloseTimeout = () => {
	if (closeTimeout !== null) {
		clearTimeout(closeTimeout);
		closeTimeout = null;
	}
};

const fetchResults = async () => {
	clearSearchTimeout();

	if (!canSearch.value) {
		results.value = emptyResults();
		isLoading.value = false;
		return;
	}

	const requestId = ++searchRequestCounter;
	isLoading.value = true;

	try {
		const response = await axios.get<GlobalSearchResponse>(route("dashboard.search"), {
			params: {
				query: trimmedQuery.value,
			},
		});

		if (requestId !== searchRequestCounter) {
			return;
		}

		results.value = response.data;
	} catch {
		if (requestId === searchRequestCounter) {
			results.value = emptyResults();
		}
	} finally {
		if (requestId === searchRequestCounter) {
			isLoading.value = false;
		}
	}
};

const scheduleSearch = () => {
	clearSearchTimeout();

	searchTimeout = setTimeout(() => {
		void fetchResults();
	}, 220);
};

const openSurface = () => {
	clearCloseTimeout();
	isFocused.value = true;
};

const scheduleCloseSurface = () => {
	clearCloseTimeout();

	closeTimeout = setTimeout(() => {
		isFocused.value = false;
	}, 120);
};

const formattedMeta = (result: GlobalSearchResult): string | null => {
	if (!result.meta) {
		return null;
	}

	if (result.type !== "run") {
		return result.meta;
	}

	const date = new Date(result.meta);

	if (Number.isNaN(date.getTime())) {
		return null;
	}

	return createDateTimeFormatter(locale.value, withDisplayTimeZone({
		day: "numeric",
		month: "short",
		hour: "numeric",
		minute: "2-digit",
	})).format(date);
};

const selectResult = (result: GlobalSearchResult) => {
	query.value = "";
	results.value = emptyResults();
	isFocused.value = false;
	emit("selected");
	router.get(result.url);
};

watch(query, () => {
	scheduleSearch();
});

onBeforeUnmount(() => {
	clearSearchTimeout();
	clearCloseTimeout();
});
</script>

<template>
	<div
		class="relative"
		@focusin="openSurface"
		@focusout="scheduleCloseSurface"
	>
		<UInput
			v-model="query"
			:autofocus="autofocus"
			:placeholder="t('navigation.topbar.search_bar')"
			:ui="{ base: 'rounded-none placeholder:text-neutral-500' }"
			leading-icon="i-lucide-search"
			size="xl"
			class="w-full"
			@keydown.esc="isFocused = false"
		/>

		<div
			v-if="isSurfaceVisible"
			:class="surfaceClasses"
		>
			<div class="max-h-[min(34rem,calc(100dvh-8rem))] overflow-y-auto border border-white/10 bg-neutral-950/96 p-3 shadow-[0_24px_64px_rgba(0,0,0,0.48)] backdrop-blur-xl">
				<div
					v-if="!canSearch"
					class="px-3 py-6 text-center text-sm text-white/55"
				>
					{{ t("navigation.topbar.global_search.min_chars") }}
				</div>

				<div
					v-else-if="isLoading && !hasAnyResults"
					class="space-y-2 px-2 py-2"
				>
					<USkeleton
						v-for="index in 5"
						:key="index"
						class="h-14 rounded-none bg-white/8"
					/>
				</div>

				<div
					v-else-if="!hasAnyResults"
					class="px-3 py-6 text-center text-sm text-white/55"
				>
					{{ t("navigation.topbar.global_search.empty") }}
				</div>

				<div
					v-else
					class="space-y-4"
				>
					<section
						v-for="section in resultSections"
						:key="section.key"
						v-show="section.results.length > 0"
						class="space-y-2"
					>
						<div class="flex items-center gap-2 px-2 text-xs font-semibold uppercase tracking-[0.16em] text-white/42">
							<UIcon :name="section.icon" class="size-3.5" />
							<span>{{ section.title }}</span>
						</div>

						<div class="space-y-1">
							<button
								v-for="result in section.results"
								:key="`${result.type}-${result.id}`"
								type="button"
								class="flex w-full items-center gap-3 border border-transparent px-2 py-2.5 text-left transition-colors hover:border-brand-400/35 hover:bg-brand-400/10"
								@mousedown.prevent
								@click="selectResult(result)"
							>
								<span class="flex size-11 shrink-0 items-center justify-center overflow-hidden border border-white/10 bg-white/5">
									<img
										v-if="result.image_url"
										:src="result.image_url"
										:alt="result.title"
										class="h-full w-full object-cover"
									>
									<UIcon
										v-else
										:name="result.icon"
										class="size-5 text-brand-300"
									/>
								</span>

								<span class="min-w-0 flex-1 space-y-1">
									<span class="block truncate text-sm font-semibold text-white">
										{{ result.title }}
									</span>
									<span
										v-if="result.subtitle"
										class="block truncate text-xs text-white/58"
									>
										{{ result.subtitle }}
									</span>
								</span>

								<span
									v-if="formattedMeta(result)"
									class="hidden shrink-0 border border-white/10 bg-white/5 px-2 py-1 text-xs text-white/54 sm:inline-flex"
								>
									{{ formattedMeta(result) }}
								</span>

								<UIcon name="i-lucide-arrow-up-right" class="size-4 shrink-0 text-white/36" />
							</button>
						</div>
					</section>
				</div>
			</div>
		</div>
	</div>
</template>
