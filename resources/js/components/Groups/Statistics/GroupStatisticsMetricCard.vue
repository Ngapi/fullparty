<script setup lang="ts">
defineProps<{
	label: string
	value: string
	description?: string
	icon: string
	tone?: "primary" | "success" | "info" | "warning" | "neutral"
}>();

const toneClasses: Record<string, string> = {
	primary: "bg-primary/10 text-primary",
	success: "bg-success/10 text-success",
	info: "bg-info/10 text-info",
	warning: "bg-warning/10 text-warning",
	neutral: "bg-muted text-muted",
};
</script>

<template>
	<UCard class="h-full dark:bg-elevated/25" :ui="{ body: 'p-4 sm:p-4' }">
		<div class="flex items-start justify-between gap-3">
			<div class="min-w-0">
				<p class="text-xs font-semibold uppercase tracking-wide text-muted">
					{{ label }}
				</p>
				<p class="mt-2 text-2xl font-semibold text-toned">
					{{ value }}
				</p>
				<p v-if="description" class="mt-1 text-xs text-muted">
					{{ description }}
				</p>
			</div>
			<div
				class="flex size-10 shrink-0 items-center justify-center rounded-sm"
				:class="toneClasses[tone ?? 'primary']"
			>
				<UIcon :name="icon" class="size-5" />
			</div>
		</div>
	</UCard>
</template>
