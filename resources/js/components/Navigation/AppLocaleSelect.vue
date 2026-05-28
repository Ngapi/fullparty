<script setup lang="ts">
import type { DropdownMenuItem } from "@nuxt/ui";
import { computed } from "vue";
import { usePersistentLocale } from "@/composables/usePersistentLocale";

const props = withDefaults(defineProps<{
	variant?: string
	size?: string
	compact?: boolean
}>(), {
	variant: "ghost",
	size: "md",
	compact: false,
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

const dropdownItems = computed<DropdownMenuItem[][]>(() => [
	items.value.map((locale) => ({
		label: locale.name,
		avatar: locale.flagSrc ? { src: locale.flagSrc, alt: locale.name } : undefined,
		onSelect: () => updateLocale(locale.code),
	})),
]);
</script>

<template>
	<div class="inline-flex">
		<UDropdownMenu
			v-if="compact"
			:items="dropdownItems"
			:ui="{content:'bg-linear-to-b to-brand-900/50 from-neutral-950 rounded-none cursor-pointer', item:'before:rounded-none hover:bg-brand-600/50'}"
		>
			<UButton
				color="neutral"
				:variant="variant"
				:size="size"
				icon="i-lucide-languages"
				:aria-label="selectedLocale?.name"
			/>
		</UDropdownMenu>

		<USelectMenu
			v-else
			:model-value="currentLocale"
			:items="items"
			value-key="code"
			label-key="name"
			:search-input="false"
			:variant="variant"
			:size="size"
			:ui="{content:'bg-linear-to-b to-brand-900/50 from-neutral-950 rounded-none cursor-pointer', item:'before:rounded-none hover:bg-brand-600/50'}"
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
	</div>
</template>
