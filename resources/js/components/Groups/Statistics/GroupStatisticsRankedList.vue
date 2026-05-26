<script setup lang="ts">
import GroupStatisticsEmptyState from "@/components/Groups/Statistics/GroupStatisticsEmptyState.vue";
import type { GroupStatisticsLoadoutItem } from "@/Types/GroupStatistics";

defineProps<{
	items: GroupStatisticsLoadoutItem[]
	emptyTitle: string
	emptyDescription: string
	countLabel: (count: number) => string
}>();
</script>

<template>
	<GroupStatisticsEmptyState
		v-if="items.length === 0"
		:title="emptyTitle"
		:description="emptyDescription"
		icon="i-lucide-list-ordered"
	/>
	<div v-else class="space-y-3">
		<div
			v-for="item in items"
			:key="item.key"
			class="rounded-sm border border-default bg-muted/10 px-3 py-3"
		>
			<div class="flex items-center justify-between gap-3">
				<div class="flex min-w-0 items-center gap-3">
					<img
						v-if="item.icon_url"
						:src="item.icon_url"
						:alt="item.label"
						class="size-8 rounded-sm object-contain"
					>
					<div v-else class="flex size-8 shrink-0 items-center justify-center rounded-sm bg-primary/10 text-xs font-semibold text-primary">
						{{ item.short_label || item.label.slice(0, 2) }}
					</div>
					<div class="min-w-0">
						<p class="truncate font-medium text-toned">
							{{ item.label }}
						</p>
						<p v-if="item.role" class="text-xs capitalize text-muted">
							{{ item.role }}
						</p>
					</div>
				</div>
				<div class="shrink-0 text-right">
					<p class="font-semibold text-toned">
						{{ item.count }}
					</p>
					<p class="text-xs text-muted">
						{{ countLabel(item.count) }}
					</p>
				</div>
			</div>
			<div class="mt-3 h-1.5 overflow-hidden rounded-sm bg-muted">
				<div class="h-full rounded-sm bg-primary" :style="{ width: `${item.percent}%` }" />
			</div>
		</div>
	</div>
</template>
