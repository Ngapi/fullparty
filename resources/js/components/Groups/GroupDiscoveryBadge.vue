<script setup lang="ts">
import { computed } from "vue";
import { groupDiscoveryBadgeStyle } from "@/utils/groupDiscoveryBadgeStyles";

const props = withDefaults(defineProps<{
	label: string
	color?: string | null
}>(), {
	color: null,
});

const badgeStyle = computed(() => groupDiscoveryBadgeStyle(props.color));
const fallbackClass = computed(() => (
	props.color
		? "border-transparent bg-transparent"
		: "border-white/10 bg-white/5 text-white/85"
));
</script>

<template>
	<span
		class="inline-flex items-center border px-2 py-0.5 text-xs font-medium whitespace-nowrap rounded-none"
		:class="fallbackClass"
		:style="badgeStyle"
	>
		{{ label }}
	</span>
</template>
