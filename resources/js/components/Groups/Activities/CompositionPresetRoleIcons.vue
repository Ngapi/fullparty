<script setup lang="ts">
import type { CompositionPreset } from "@/utils/activityCompositionHints";
import { compositionRoleIconUrl } from "@/utils/activityCompositionHints";

defineProps<{
	preset: CompositionPreset
	size?: "sm" | "md"
}>();
</script>

<template>
	<span
		class="inline-flex min-w-0 items-center"
		:class="size === 'sm' ? 'gap-0.5' : 'gap-1'"
		:title="preset.shorthand"
		:aria-label="preset.shorthand"
	>
		<img
			v-for="(role, index) in preset.roles"
			:key="`${preset.key}-${index}-${role}`"
			:src="compositionRoleIconUrl(role)"
			alt=""
			aria-hidden="true"
			class="shrink-0 rounded-none object-contain"
			:class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'"
			loading="lazy"
		>
	</span>
</template>
