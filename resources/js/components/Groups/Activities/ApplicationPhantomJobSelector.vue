<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import type { ApplicationQuestionOption } from "@/Types/ActivityApplications";
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
	"update:modelValue": [value: unknown]
}>();

const { t, locale } = useI18n();
const page = usePage();
const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"));
const isOpen = ref(false);

const selectedKeys = computed<string[]>({
	get: () => {
		if (props.multiple) {
			return Array.isArray(props.modelValue)
				? props.modelValue.filter((value): value is string => typeof value === "string")
				: [];
		}

		return typeof props.modelValue === "string" && props.modelValue !== ""
			? [props.modelValue]
			: [];
	},
	set: (value) => {
		if (props.multiple) {
			emit("update:modelValue", value);

			return;
		}

		emit("update:modelValue", value[0] ?? "");
	},
});

const selectedItems = computed(() => props.options.filter((option) => selectedKeys.value.includes(option.key)));
const favoriteOptions = computed(() => {
	const favoriteKeys = new Set(props.favoriteOptionKeys ?? []);

	return props.options.filter((option) => favoriteKeys.has(option.key));
});

const summaryLabel = computed(() => {
	if (selectedItems.value.length === 0) {
		return t("groups.activities.application.phantom_picker.empty");
	}

	if (!props.multiple && selectedItems.value[0]) {
		return localizedValue(selectedItems.value[0].label, locale.value, fallbackLocale.value) || selectedItems.value[0].key;
	}

	return t("groups.activities.application.phantom_picker.selected_count", { count: selectedItems.value.length });
});

const optionIconUrl = (option: ApplicationQuestionOption) => option.meta?.transparent_icon_url
	|| option.meta?.icon_url
	|| option.meta?.sprite_url
	|| null;

const optionLabel = (option: ApplicationQuestionOption) => localizedValue(option.label, locale.value, fallbackLocale.value) || option.key;

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

	emit("update:modelValue", selectedKeys.value.includes(optionKey) ? "" : optionKey);
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
			<div
				v-for="item in selectedItems"
				:key="item.key"
				class="inline-flex items-center gap-2 rounded-sm border border-default bg-muted/20 px-2.5 py-1 text-xs font-medium text-toned"
			>
				<img
					v-if="optionIconUrl(item)"
					:src="optionIconUrl(item) || undefined"
					:alt="optionLabel(item)"
					class="size-5 rounded-sm object-contain"
				>
				<span>{{ optionLabel(item) }}</span>
			</div>
		</div>
	</UFormField>

	<UModal v-model:open="isOpen">
		<template #content>
			<div class="flex flex-col gap-5 p-4">
				<div class="flex items-start justify-between gap-4">
					<div class="space-y-1">
						<h3 class="text-lg font-semibold text-toned">{{ label }}</h3>
						<p v-if="description" class="text-sm text-muted">{{ description }}</p>
					</div>

					<UButton
						color="neutral"
						variant="ghost"
						icon="i-lucide-x"
						@click="isOpen = false"
					/>
				</div>

				<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
					<button
						v-for="option in options"
						:key="option.key"
						type="button"
						class="flex min-h-28 flex-col items-center justify-center gap-2 rounded-sm border p-3 text-center transition duration-150 ease-out hover:-translate-y-0.5"
						:class="isSelected(option.key)
							? 'border-primary bg-primary/10 text-toned shadow-sm shadow-primary/10'
							: 'border-default bg-muted/10 text-muted hover:border-primary hover:text-toned'"
						@click="toggleOption(option.key)"
					>
						<img
							v-if="optionIconUrl(option)"
							:src="optionIconUrl(option) || undefined"
							:alt="optionLabel(option)"
							class="size-12 rounded-sm object-contain"
						>
						<div
							v-else
							class="flex size-12 items-center justify-center rounded-sm bg-muted text-sm font-semibold text-toned"
						>
							{{ optionLabel(option).slice(0, 2) }}
						</div>

						<span class="max-w-full break-words text-xs font-medium leading-tight">
							{{ optionLabel(option) }}
						</span>
					</button>
				</div>

				<div
					v-if="multiple && options.length > 0"
					class="border-t border-default pt-4"
				>
					<p class="mb-2 text-xs font-medium uppercase text-muted">
						{{ t("groups.activities.application.quick_select.title") }}
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
