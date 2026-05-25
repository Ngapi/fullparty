<script setup lang="ts">
import type { GroupIndexRecord, PaginatedGroups } from "@/Types/Groups";
import GroupSearchItem from "@/components/Groups/GroupSearchItem.vue";
import GroupSearchItemSkeleton from "@/components/Groups/GroupSearchItemSkeleton.vue";
import GroupSearchPagination from "@/components/Groups/GroupSearchPagination.vue";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	results: PaginatedGroups
	loading?: boolean
}>();

const emit = defineEmits<{
	pageChange: [page: number]
	openGroup: [group: GroupIndexRecord]
}>();

const { t } = useI18n();

const hasResults = computed(() => props.results.data.length > 0);
const totalPages = computed(() => Math.max(1, props.results.meta.last_page));

function onPageChange(page: number) {
	emit("pageChange", page);
}

function onOpenGroup(group: GroupIndexRecord) {
	emit("openGroup", group);
}
</script>

<template>
	<div class="flex flex-col gap-4">
		<div class="flex flex-col gap-3">
			<template v-if="loading">
				<GroupSearchItemSkeleton
					v-for="index in 4"
					:key="`group-search-skeleton-${index}`"
				/>
			</template>

			<template v-else>
				<GroupSearchItem
					v-for="group in results.data"
					:key="group.id"
					:group="group"
					@open-group="onOpenGroup"
				/>
			</template>

			<div
				v-if="!loading && !hasResults"
				class="border border-neutral-900 bg-neutral-900/50 px-4 py-8 text-sm text-muted"
			>
				{{ t("general.no_results") }}
			</div>
		</div>

		<GroupSearchPagination
			v-if="!loading && hasResults"
			:current-page="results.meta.current_page"
			:total-pages="totalPages"
			:disabled="loading"
			@page-change="onPageChange"
		/>
	</div>
</template>
