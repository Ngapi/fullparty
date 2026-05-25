<script setup lang="ts">
import type { RunDiscoveryResultItemData } from "../../Types/RunDiscovery";
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import RunDiscoveryPagination from "@/components/Runs/RunDiscoveryPagination.vue";
import RunDiscoveryResultItem from "@/components/Runs/RunDiscoveryResultItem.vue";

const props = defineProps<{
	items: RunDiscoveryResultItemData[]
	resultCount: number
	currentPage: number
	totalPages: number
	loading?: boolean
}>();

const emit = defineEmits<{
	pageChange: [page: number]
}>();

const { t } = useI18n();
const selectedSort = ref("starting_soonest");
const hasResults = computed(() => props.items.length > 0);

const sortOptions = computed(() => [
	{ label: t("runs.discovery.results.sort_options.starting_soonest"), value: "starting_soonest" },
	{ label: t("runs.discovery.results.sort_options.newest_posted"), value: "newest_posted" },
	{ label: t("runs.discovery.results.sort_options.recently_updated"), value: "recently_updated" },
	{ label: t("runs.discovery.results.sort_options.open_slots"), value: "open_slots" },
]);
</script>

<template>
	<section class="min-w-0 flex-1 h-full min-h-0">
		<div class="flex h-full min-h-0 flex-col overflow-hidden">
			<div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto_auto] lg:items-center lg:gap-6">
				<div class="min-w-0 flex flex-col gap-1">
					<h1 class="text-2xl font-semibold text-white">
						{{ t("runs.discovery.results.title") }}
					</h1>
					<p class="max-w-2xl text-sm leading-6 text-white/60">
						{{ t("runs.discovery.results.subtitle") }}
					</p>
				</div>

				<div class="border-white/8 text-sm font-medium text-white/72 lg:border-l lg:pl-6">
					{{ t("runs.discovery.results.count", { count: resultCount }) }}
				</div>

				<div class="w-full lg:max-w-xs lg:border-l lg:border-white/8 lg:pl-6">
					<USelect
						v-model="selectedSort"
						class="w-full"
						:items="sortOptions"
						value-key="value"
						:placeholder="t('runs.discovery.results.sort_by')"
						:ui="{ base: 'rounded-none' }"
					/>
				</div>
			</div>

			<div class="min-h-0 flex-1 space-y-4 overflow-y-auto  py-6">
				<div v-if="props.loading" class="space-y-4">
					<div
						v-for="index in 4"
						:key="`run-discovery-skeleton-${index}`"
						class="overflow-hidden border border-white/10 bg-neutral-950/72 shadow-[0_20px_40px_rgba(0,0,0,0.2)]"
					>
						<div class="grid gap-4 xl:grid-cols-[7rem_minmax(0,1.6fr)_11rem_10rem_11rem] xl:items-center">
							<div class="border border-white/8 bg-neutral-900/70">
								<USkeleton class="h-38 w-34 rounded-none" />
							</div>

							<div class="min-w-0 space-y-3 py-4 xl:pr-2">
								<div class="space-y-3">
									<USkeleton class="h-6 w-3/5 rounded-none" />
									<USkeleton class="h-4 w-2/5 rounded-none" />
								</div>

								<div class="space-y-2">
									<USkeleton class="h-4 w-full rounded-none" />
									<USkeleton class="h-4 w-4/5 rounded-none" />
								</div>

								<div class="flex flex-wrap gap-2">
									<USkeleton class="h-6 w-20 rounded-none" />
									<USkeleton class="h-6 w-24 rounded-none" />
									<USkeleton class="h-6 w-16 rounded-none" />
								</div>
							</div>

							<div class="space-y-3 border-t border-white/8 pt-3 xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0">
								<USkeleton class="h-5 w-16 rounded-none" />
								<USkeleton class="h-8 w-24 rounded-none" />
								<USkeleton class="h-4 w-14 rounded-none" />
								<USkeleton class="h-5 w-20 rounded-none" />
								<USkeleton class="h-4 w-16 rounded-none" />
							</div>

							<div class="border-t border-white/8 pt-3 xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0">
								<div class="space-y-3 p-3">
									<USkeleton class="h-4 w-20 rounded-none" />
									<USkeleton class="h-5 w-full rounded-none" />
									<USkeleton class="h-5 w-full rounded-none" />
									<USkeleton class="h-5 w-full rounded-none" />
								</div>
							</div>

							<div class="border-t border-white/8 pt-3 xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0">
								<div class="flex h-full flex-col justify-between gap-4">
									<USkeleton class="mx-auto h-5 w-24 rounded-none" />
									<div class="space-y-2 pr-4">
										<USkeleton class="h-10 w-full rounded-none" />
										<USkeleton class="h-10 w-full rounded-none" />
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div
					v-else-if="!hasResults"
					class="flex min-h-64 items-center justify-center border border-white/10 bg-neutral-950/42 p-8 text-center"
				>
					<div class="max-w-md space-y-2">
						<p class="text-lg font-semibold text-white">
							{{ t("runs.discovery.results.placeholder_title") }}
						</p>
						<p class="text-sm leading-6 text-white/60">
							{{ t("runs.discovery.results.placeholder_subtitle") }}
						</p>
					</div>
				</div>

				<template v-else>
					<RunDiscoveryResultItem
						v-for="item in props.items"
						:key="item.id"
						:item="item"
					/>
				</template>
			</div>

			<RunDiscoveryPagination
				v-if="!props.loading && hasResults && props.totalPages > 1"
				:current-page="props.currentPage"
				:total-pages="props.totalPages"
				:disabled="props.loading"
				@page-change="(page) => emit('pageChange', page)"
			/>
		</div>
	</section>
</template>
