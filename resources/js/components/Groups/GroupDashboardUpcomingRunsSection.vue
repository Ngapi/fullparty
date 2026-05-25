<script setup lang="ts">
import type { GroupDashboardActivity } from "@/Types/Groups";
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import GroupDashboardUpcomingRunCard from "@/components/Groups/GroupDashboardUpcomingRunCard.vue";

const props = defineProps<{
	activities: GroupDashboardActivity[]
}>();

const { t } = useI18n();
const hasRuns = computed(() => props.activities.length > 0);
const scrollContainer = ref<HTMLElement | null>(null);

const scrollByDirection = (direction: "left" | "right") => {
	if (! scrollContainer.value) {
		return;
	}

	const amount = Math.round(scrollContainer.value.clientWidth * 0.82);

	scrollContainer.value.scrollBy({
		left: direction === "left" ? -amount : amount,
		behavior: "smooth",
	});
};
</script>

<template>
	<section class="flex flex-col px-6 mt-6">
		<div class="mb-5 flex flex-col gap-1">
			<h2 class="text-lg font-semibold text-white">
				{{ t("groups.dashboard.upcoming_runs.title") }}
			</h2>
			<p class="max-w-2xl text-sm leading-6 text-white/62">
				{{ t("groups.dashboard.upcoming_runs.subtitle") }}
			</p>
		</div>

		<div v-if="hasRuns" class="relative min-h-0 flex-1">
			<UButton
				class="absolute top-1/2 left-0 z-10 inline-flex -translate-x-1/2 -translate-y-1/2 border border-white/10 bg-neutral-950/85 backdrop-blur-sm"
				color="neutral"
				variant="soft"
				icon="i-lucide-chevron-left"
				:aria-label="t('groups.index.featured.previous')"
				@click="scrollByDirection('left')"
			/>
			<UButton
				class="absolute top-1/2 right-0 z-10 inline-flex translate-x-1/2 -translate-y-1/2 border border-white/10 bg-neutral-950/85 backdrop-blur-sm"
				color="neutral"
				variant="soft"
				icon="i-lucide-chevron-right"
				:aria-label="t('groups.index.featured.next')"
				@click="scrollByDirection('right')"
			/>

			<div
				ref="scrollContainer"
				class=" flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
			>
				<div
					v-for="activity in activities"
					:key="activity.id"
					class="min-w-[20rem] flex-1 snap-start"
				>
					<GroupDashboardUpcomingRunCard :activity="activity" />
				</div>
			</div>
		</div>

		<div
			v-else
			class="flex flex-1 flex-col items-start justify-start"
		>
			<p class="text-lg font-semibold text-white">
				{{ t("groups.dashboard.upcoming_runs.empty_title") }}
			</p>
			<p class="max-w-lg text-sm leading-6 text-white/62">
				{{ t("groups.dashboard.upcoming_runs.empty_description") }}
			</p>
		</div>
	</section>
</template>
