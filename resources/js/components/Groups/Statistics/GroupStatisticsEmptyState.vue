<script setup lang="ts">
defineProps<{
	title: string
	description: string
	icon?: string
}>();
</script>

<template>
	<div class="flex min-h-40 flex-col items-center justify-center rounded-sm border border-dashed border-default bg-muted/10 px-4 py-8 text-center">
		<UIcon :name="icon ?? 'i-lucide-chart-no-axes-combined'" class="size-8 text-muted" />
		<p class="mt-3 font-semibold text-toned">
			{{ title }}
		</p>
		<p class="mt-1 max-w-md text-sm text-muted">
			{{ description }}
		</p>
	</div>
</template>
