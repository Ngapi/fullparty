<script setup lang="ts">
import { computed } from "vue";
import { usePersistentLocale } from "@/composables/usePersistentLocale";

const props = withDefaults(defineProps<{
	variant?: string
	size?: string
}>(), {
	variant: "ghost",
	size: "md",
});

const { currentLocale, localeOptions, updateLocale } = usePersistentLocale();

const flagByLocaleCode: Record<string, string> = {
	en: "/flags/us.svg",
	de: "/flags/de.svg",
	fr: "/flags/fr.svg",
	ja: "/flags/jp.svg",
};

const items = computed(() => localeOptions.value.map((locale) => ({
	...locale,
	flagSrc: flagByLocaleCode[locale.code] ?? null,
})));

const selectedLocale = computed(() => (
	items.value.find((locale) => locale.code === currentLocale.value)
	?? items.value[0]
	?? null
));
</script>

<template>
	<USelectMenu
		:model-value="currentLocale"
		:items="items"
		value-key="code"
		label-key="name"
		:search-input="false"
		:variant="variant"
		:size="size"
		@update:model-value="updateLocale"
	>
		<template #leading>
			<img
				v-if="selectedLocale?.flagSrc"
				:src="selectedLocale.flagSrc"
				:alt="selectedLocale.name"
				class="h-4 w-5 rounded-[2px] border border-default object-cover"
			>
		</template>

		<template #item-leading="{ item }">
			<img
				v-if="item.flagSrc"
				:src="item.flagSrc"
				:alt="item.name"
				class="h-4 w-5 rounded-[2px] border border-default object-cover"
			>
		</template>
	</USelectMenu>
</template>
