<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	currentPage: number
	totalPages: number
	disabled?: boolean
}>();

const emit = defineEmits<{
	pageChange: [page: number]
}>();

const { t } = useI18n();

const pageNumbers = computed(() => Array.from({ length: props.totalPages }, (_, index) => index + 1));
const canGoBack = computed(() => !props.disabled && props.currentPage > 1);
const canGoForward = computed(() => !props.disabled && props.currentPage < props.totalPages);

function goToPage(page: number) {
	if (props.disabled || page < 1 || page > props.totalPages || page === props.currentPage) {
		return;
	}

	emit("pageChange", page);
}
</script>

<template>
	<div class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between">
		<p class="text-sm text-muted">
			{{ t("groups.index.discovery.placeholder.page_status", { current: currentPage, total: totalPages }) }}
		</p>

		<div class="flex items-center justify-end gap-1">
			<UButton
				color="neutral"
				variant="ghost"
				icon="i-lucide-chevron-left"
				:disabled="!canGoBack"
				@click="goToPage(currentPage - 1)"
			/>

			<UButton
				v-for="page in pageNumbers"
				:key="page"
				color="neutral"
				:variant="page === currentPage ? 'soft' : 'ghost'"
				class="min-w-10 justify-center"
				:disabled="disabled"
				@click="goToPage(page)"
			>
				{{ page }}
			</UButton>

			<UButton
				color="neutral"
				variant="ghost"
				icon="i-lucide-chevron-right"
				:disabled="!canGoForward"
				@click="goToPage(currentPage + 1)"
			/>
		</div>
	</div>
</template>
