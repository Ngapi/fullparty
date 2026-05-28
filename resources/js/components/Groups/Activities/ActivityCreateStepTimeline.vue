<script setup lang="ts">
import { computed } from "vue";

type CreateStep = {
	title: string
	description: string
	value: number
}

const props = defineProps<{
	steps: CreateStep[]
	modelValue: number
}>();

const emit = defineEmits<{
	"update:modelValue": [value: number]
}>();

const timelineItems = computed(() => props.steps.map((step, index) => ({
	...step,
	ui: {
		indicator: index < props.modelValue
			? 'bg-primary text-inverted ring-1 ring-primary'
			: index === props.modelValue
				? 'bg-primary/20 text-primary ring-1 ring-primary/60'
				: 'bg-muted text-muted ring-1 ring-white/15',
		separator: index < props.modelValue ? 'bg-primary/70' : 'bg-default',
		title: index === props.modelValue ? 'text-highlighted' : 'text-muted',
	},
})));

const selectStep = (_event: Event, item: CreateStep) => {
	emit('update:modelValue', item.value);
};
</script>

<template>
	<UTimeline
		:model-value="modelValue"
		:items="timelineItems"
		orientation="horizontal"
		color="primary"
		size="lg"
		class="w-full min-w-0"
		:ui="{
			root: 'w-full min-w-0',
			item: 'min-w-0 flex-1 cursor-pointer gap-3',
			container: 'min-w-0',
			indicator: 'ring-1 ring-primary/35 data-[state=inactive]:ring-white/15 data-[state=active]:bg-primary/20 data-[state=completed]:bg-primary data-[state=completed]:text-inverted',
			separator: 'bg-default data-[state=completed]:bg-primary/70',
			wrapper: 'min-w-0',
			title: 'truncate text-sm font-semibold',
			description: 'hidden truncate text-xs text-muted sm:block',
		}"
		@select="selectStep"
	>
		<template #indicator="{ item }">
			<UIcon
				v-if="item.value < modelValue"
				name="i-lucide-check"
				class="size-4"
			/>
			<span v-else class="text-sm font-semibold">
				{{ item.value + 1 }}
			</span>
		</template>
	</UTimeline>
</template>
