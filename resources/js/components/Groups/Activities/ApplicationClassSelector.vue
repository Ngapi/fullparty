<script setup lang="ts">
import type { ApplicationQuestionOption } from "@/Types/ActivityApplications";
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { usePage } from "@inertiajs/vue3";
import { localizedValue } from "@/utils/localizedValue";

const props = defineProps<{
	label: string
	description?: string
	required?: boolean
	error?: string
	options: ApplicationQuestionOption[]
	modelValue: unknown
	multiple?: boolean
	disabled?: boolean
	favoriteOptionKeys?: string[]
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown]
}>();

const { t, locale } = useI18n();
const page = usePage();
const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? 'en'));
const isOpen = ref(false);

const roleGroups = [
	{ key: 'tank', icon: 'i-lucide-shield', label: computed(() => t('groups.activities.application.class_picker.categories.tank')) },
	{ key: 'healer', icon: 'i-lucide-heart-pulse', label: computed(() => t('groups.activities.application.class_picker.categories.healer')) },
	{ key: 'melee dps', icon: 'i-lucide-swords', label: computed(() => t('groups.activities.application.class_picker.categories.melee')) },
	{ key: 'physical ranged dps', icon: 'i-lucide-crosshair', label: computed(() => t('groups.activities.application.class_picker.categories.phys')) },
	{ key: 'magic ranged dps', icon: 'i-lucide-sparkles', label: computed(() => t('groups.activities.application.class_picker.categories.magic')) },
];

const selectedKeys = computed<string[]>({
	get: () => {
		if (props.multiple) {
			return Array.isArray(props.modelValue)
				? props.modelValue.filter((value): value is string => typeof value === 'string')
				: [];
		}

		return typeof props.modelValue === 'string' && props.modelValue !== ''
			? [props.modelValue]
			: [];
	},
	set: (value) => {
		if (props.multiple) {
			emit('update:modelValue', value);

			return;
		}

		emit('update:modelValue', value[0] ?? '');
	},
});

const selectedItems = computed(() => props.options.filter((option) => selectedKeys.value.includes(option.key)));
const favoriteOptions = computed(() => {
	const favoriteKeys = new Set(props.favoriteOptionKeys ?? []);

	return props.options.filter((option) => favoriteKeys.has(option.key));
});

const groupedOptions = computed(() => roleGroups
	.map((group) => ({
		key: group.key,
		icon: group.icon,
		label: group.label.value,
		options: props.options.filter((option) => option.meta?.role === group.key),
	}))
	.filter((group) => group.options.length > 0));

const summaryLabel = computed(() => {
	if (selectedItems.value.length === 0) {
		return t('groups.activities.application.class_picker.empty');
	}

	if (!props.multiple && selectedItems.value[0]) {
		return localizedValue(selectedItems.value[0].label, locale.value, fallbackLocale.value) || selectedItems.value[0].key;
	}

	return t('groups.activities.application.class_picker.selected_count', { count: selectedItems.value.length });
});

const toggleOption = (optionKey: string) => {
	if (props.disabled) {
		return;
	}

	if (props.multiple) {
		selectedKeys.value = selectedKeys.value.includes(optionKey)
			? selectedKeys.value.filter((key) => key !== optionKey)
			: [...selectedKeys.value, optionKey];

		return;
	}

	emit('update:modelValue', selectedKeys.value.includes(optionKey) ? '' : optionKey);
	isOpen.value = false;
};

const toggleOptionGroup = (options: ApplicationQuestionOption[]) => {
	if (props.disabled || !props.multiple) {
		return;
	}

	if (areOptionsSelected(options)) {
		const optionKeys = new Set(options.map((option) => option.key));

		selectedKeys.value = selectedKeys.value.filter((key) => !optionKeys.has(key));

		return;
	}

	const nextKeys = [...selectedKeys.value];
	options.forEach((option) => {
		if (!nextKeys.includes(option.key)) {
			nextKeys.push(option.key);
		}
	});

	selectedKeys.value = nextKeys;
};

const areOptionsSelected = (options: ApplicationQuestionOption[]) => options.length > 0 && options
	.every((option) => selectedKeys.value.includes(option.key));

const isSelected = (optionKey: string) => selectedKeys.value.includes(optionKey);
</script>

<template>
	<UFormField
		:label="label"
		:description="description"
		:error="error"
		:required="required"
	>
		<UButton
			color="neutral"
			variant="outline"
			size="lg"
			class="w-full justify-between"
			:disabled="disabled"
			:label="summaryLabel"
			trailing-icon="i-lucide-chevron-down"
			@click="isOpen = true"
		/>

		<div
			v-if="selectedItems.length > 0"
			class="mt-3 flex flex-wrap gap-2"
		>
			<UBadge
				v-for="item in selectedItems"
				:key="item.key"
				color="neutral"
				variant="soft"
				:label="localizedValue(item.label, locale, fallbackLocale) || item.key"
			/>
		</div>
	</UFormField>

	<UModal v-model:open="isOpen">
		<template #content>
			<div class="flex flex-col gap-5 p-4">
				<div class="flex items-start justify-between gap-4">
					<div class="space-y-1">
						<h3 class="font-semibold text-lg text-toned">{{ label }}</h3>
						<p v-if="description" class="text-sm text-muted">{{ description }}</p>
					</div>

					<UButton
						color="neutral"
						variant="ghost"
						icon="i-lucide-x"
						@click="isOpen = false"
					/>
				</div>

				<div class="flex flex-row flex-wrap gap-5">
					<section
						v-for="group in groupedOptions"
						:key="group.key"
						class="space-y-2"
					>
						<div class="flex items-center gap-3">
							<p class="font-medium text-sm text-toned">{{ group.label }}</p>
							<div class="h-px flex-1 bg-default"></div>
						</div>

						<div class="w-full flex flex-row gap-2 ">
							<button
								v-for="option in group.options"
								:key="option.key"
								type="button"
								class="flex items-center justify-center rounded-lg border-2 transition-transform duration-150 ease-out hover:scale-105"
								:class="isSelected(option.key)
									? 'border-primary bg-primary/10 text-toned'
									: 'border-default bg-muted/10 text-muted hover:border-primary'"
								@click="toggleOption(option.key)"
							>
								<img
									v-if="option.meta?.icon_url"
									:src="option.meta.icon_url"
									:alt="localizedValue(option.label, locale, fallbackLocale) || option.key"
									class="size-10 rounded-sm"
								/>
								<div
									v-else
									class="flex size-10 items-center justify-center rounded-sm bg-muted text-xs font-semibold text-toned"
								>
									{{ option.meta?.shorthand || localizedValue(option.label, locale, fallbackLocale)?.slice(0, 2) || option.key.slice(0, 2) }}
								</div>
							</button>
						</div>
					</section>
				</div>

				<div
					v-if="multiple && options.length > 0"
					class="border-t border-default pt-4"
				>
					<p class="mb-2 text-xs font-medium uppercase text-muted">
						{{ t('groups.activities.application.quick_select.title') }}
					</p>

					<div class="flex flex-wrap gap-2">
						<UButton
							color="neutral"
							:variant="areOptionsSelected(options) ? 'solid' : 'soft'"
							size="sm"
							icon="i-lucide-check-check"
							:label="t('groups.activities.application.quick_select.all')"
							:disabled="disabled"
							@click="toggleOptionGroup(options)"
						/>

						<UButton
							color="neutral"
							:variant="areOptionsSelected(favoriteOptions) ? 'solid' : 'soft'"
							size="sm"
							icon="i-lucide-star"
							:label="t('groups.activities.application.quick_select.favorites')"
							:disabled="disabled || favoriteOptions.length === 0"
							@click="toggleOptionGroup(favoriteOptions)"
						/>

						<UButton
							v-for="group in groupedOptions"
							:key="`shortcut-${group.key}`"
							color="neutral"
							:variant="areOptionsSelected(group.options) ? 'solid' : 'soft'"
							size="sm"
							:icon="group.icon"
							:label="group.label"
							:disabled="disabled"
							@click="toggleOptionGroup(group.options)"
						/>
					</div>
				</div>

				<div class="flex justify-end">
					<UButton
						color="neutral"
						variant="outline"
						:label="t('general.close')"
						@click="isOpen = false"
					/>
				</div>
			</div>
		</template>
	</UModal>
</template>
