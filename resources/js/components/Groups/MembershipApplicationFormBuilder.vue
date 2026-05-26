<script setup lang="ts">
import type { MembershipApplicationFieldType, MembershipApplicationFormField, MembershipApplicationFormOption, MembershipApplicationLocalizedText } from "@/Types/Groups";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import LocalizedTextFields from "@/components/Admin/ActivityTypes/LocalizedTextFields.vue";

const props = defineProps<{
	modelValue: MembershipApplicationFormField[]
	locales: string[]
	maxQuestions: number
	errors?: Record<string, string>
}>();

const emit = defineEmits<{
	"update:modelValue": [value: MembershipApplicationFormField[]]
}>();

const { t } = useI18n();

const fieldTypeOptions = computed(() => [
	{ label: t("groups.membership_applications.form.field_types.small_text"), value: "small_text" },
	{ label: t("groups.membership_applications.form.field_types.big_text"), value: "big_text" },
	{ label: t("groups.membership_applications.form.field_types.select"), value: "select" },
	{ label: t("groups.membership_applications.form.field_types.toggle"), value: "toggle" },
]);

const fields = computed({
	get: () => props.modelValue,
	set: (value: MembershipApplicationFormField[]) => emit("update:modelValue", value),
});

const createLocalizedText = (value = ""): MembershipApplicationLocalizedText => ({
	en: value,
	de: "",
	fr: "",
	ja: "",
});

const createId = (prefix: string) => `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;

const updateField = (index: number, patch: Partial<MembershipApplicationFormField>) => {
	fields.value = fields.value.map((field, fieldIndex) => fieldIndex === index ? { ...field, ...patch } : field);
};

const updateFieldType = (index: number, type: MembershipApplicationFieldType) => {
	const field = fields.value[index];
	const options = type === "select"
		? (field.options.length > 0 ? field.options : [createOption()])
		: [];

	updateField(index, { type, options });
};

const updateFieldTypeFromValue = (index: number, value: unknown) => {
	if (typeof value !== "string") {
		return;
	}

	updateFieldType(index, value as MembershipApplicationFieldType);
};

const addField = () => {
	if (fields.value.length >= props.maxQuestions) {
		return;
	}

	fields.value = [
		...fields.value,
		{
			id: createId("question"),
			type: "small_text",
			name: createLocalizedText(t("groups.membership_applications.form.new_question")),
			description: createLocalizedText(),
			required: true,
			options: [],
		},
	];
};

const removeField = (index: number) => {
	if (fields.value.length <= 1) {
		return;
	}

	fields.value = fields.value.filter((_, fieldIndex) => fieldIndex !== index);
};

const moveField = (index: number, direction: -1 | 1) => {
	const target = index + direction;

	if (target < 0 || target >= fields.value.length) {
		return;
	}

	const reordered = [...fields.value];
	const [field] = reordered.splice(index, 1);
	reordered.splice(target, 0, field);
	fields.value = reordered;
};

const createOption = (): MembershipApplicationFormOption => ({
	id: createId("option"),
	label: createLocalizedText(t("groups.membership_applications.form.new_option")),
});

const addOption = (fieldIndex: number) => {
	const field = fields.value[fieldIndex];

	updateField(fieldIndex, {
		options: [
			...field.options,
			createOption(),
		],
	});
};

const updateOption = (fieldIndex: number, optionIndex: number, label: MembershipApplicationLocalizedText) => {
	const field = fields.value[fieldIndex];

	updateField(fieldIndex, {
		options: field.options.map((option, index) => index === optionIndex ? { ...option, label } : option),
	});
};

const removeOption = (fieldIndex: number, optionIndex: number) => {
	const field = fields.value[fieldIndex];

	if (field.options.length <= 1) {
		return;
	}

	updateField(fieldIndex, {
		options: field.options.filter((_, index) => index !== optionIndex),
	});
};

const fieldError = (index: number, key: string) => props.errors?.[`fields.${index}.${key}`] ?? null;
const optionError = (fieldIndex: number, optionIndex: number) => props.errors?.[`fields.${fieldIndex}.options.${optionIndex}.label.en`] ?? null;
</script>

<template>
	<div class="space-y-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<div>
				<p class="text-sm font-medium text-highlighted">
					{{ t("groups.membership_applications.form.question_count", { count: fields.length, max: maxQuestions }) }}
				</p>
				<p v-if="errors?.fields" class="mt-1 text-sm text-error">
					{{ errors.fields }}
				</p>
			</div>

			<UButton
				color="primary"
				variant="solid"
				icon="i-lucide-plus"
				:label="t('groups.membership_applications.form.actions.add_question')"
				:disabled="fields.length >= maxQuestions"
				@click="addField"
			/>
		</div>

		<UCard
			v-for="(field, index) in fields"
			:key="field.id"
			:ui="{ root: 'rounded-sm', body: 'p-4 sm:p-4' }"
		>
			<div class="space-y-5">
				<div class="flex flex-wrap items-start justify-between gap-3">
					<div>
						<p class="text-sm font-semibold text-highlighted">
							{{ t("groups.membership_applications.form.question_title", { number: index + 1 }) }}
						</p>
						<p class="text-xs text-muted">
							{{ t("groups.membership_applications.form.question_hint") }}
						</p>
					</div>

					<div class="flex items-center gap-1">
						<UButton
							color="neutral"
							variant="ghost"
							icon="i-lucide-arrow-up"
							:aria-label="t('groups.membership_applications.form.actions.move_up')"
							:disabled="index === 0"
							@click="moveField(index, -1)"
						/>
						<UButton
							color="neutral"
							variant="ghost"
							icon="i-lucide-arrow-down"
							:aria-label="t('groups.membership_applications.form.actions.move_down')"
							:disabled="index === fields.length - 1"
							@click="moveField(index, 1)"
						/>
						<UButton
							color="error"
							variant="ghost"
							icon="i-lucide-trash-2"
							:aria-label="t('groups.membership_applications.form.actions.remove_question')"
							:disabled="fields.length <= 1"
							@click="removeField(index)"
						/>
					</div>
				</div>

				<div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem]">
					<div class="space-y-4">
						<LocalizedTextFields
							:model-value="field.name"
							:locales="locales"
							:label="t('groups.membership_applications.form.fields.name.label')"
							:description="t('groups.membership_applications.form.fields.name.help')"
							:placeholder-prefix="t('groups.membership_applications.form.fields.name.placeholder')"
							@update:model-value="(value) => updateField(index, { name: value })"
						/>
						<p v-if="fieldError(index, 'name.en')" class="text-sm text-error">
							{{ fieldError(index, 'name.en') }}
						</p>

						<LocalizedTextFields
							:model-value="field.description"
							:locales="locales"
							:label="t('groups.membership_applications.form.fields.description.label')"
							:description="t('groups.membership_applications.form.fields.description.help')"
							:placeholder-prefix="t('groups.membership_applications.form.fields.description.placeholder')"
							multiline
							:rows="2"
							@update:model-value="(value) => updateField(index, { description: value })"
						/>
					</div>

					<div class="space-y-4">
						<UFormField
							:label="t('groups.membership_applications.form.fields.type.label')"
							:error="fieldError(index, 'type')"
						>
							<USelect
								:model-value="field.type"
								class="w-full"
								:items="fieldTypeOptions"
								value-key="value"
								:ui="{ base: 'rounded-none' }"
								@update:model-value="(value) => updateFieldTypeFromValue(index, value)"
							/>
						</UFormField>

						<div class="flex items-center justify-between gap-3 border border-default bg-muted/20 px-3 py-3">
							<div>
								<p class="text-sm font-medium text-highlighted">
									{{ t("groups.membership_applications.form.fields.required.label") }}
								</p>
								<p class="text-xs text-muted">
									{{ t("groups.membership_applications.form.fields.required.help") }}
								</p>
							</div>
							<USwitch
								:model-value="field.required"
								@update:model-value="(value) => updateField(index, { required: Boolean(value) })"
							/>
						</div>
					</div>
				</div>

				<div v-if="field.type === 'select'" class="space-y-3 border-t border-default pt-4">
					<div class="flex flex-wrap items-center justify-between gap-3">
						<div>
							<p class="text-sm font-semibold text-highlighted">
								{{ t("groups.membership_applications.form.options.title") }}
							</p>
							<p class="text-xs text-muted">
								{{ t("groups.membership_applications.form.options.subtitle") }}
							</p>
							<p v-if="fieldError(index, 'options')" class="mt-1 text-sm text-error">
								{{ fieldError(index, 'options') }}
							</p>
						</div>

						<UButton
							color="neutral"
							variant="outline"
							icon="i-lucide-plus"
							:label="t('groups.membership_applications.form.actions.add_option')"
							@click="addOption(index)"
						/>
					</div>

					<div class="space-y-3">
						<div
							v-for="(option, optionIndex) in field.options"
							:key="option.id"
							class="grid gap-3 border border-default bg-muted/20 p-3 md:grid-cols-[minmax(0,1fr)_auto]"
						>
							<div>
								<LocalizedTextFields
									:model-value="option.label"
									:locales="locales"
									:label="t('groups.membership_applications.form.options.label', { number: optionIndex + 1 })"
									:placeholder-prefix="t('groups.membership_applications.form.options.placeholder')"
									@update:model-value="(value) => updateOption(index, optionIndex, value)"
								/>
								<p v-if="optionError(index, optionIndex)" class="mt-2 text-sm text-error">
									{{ optionError(index, optionIndex) }}
								</p>
							</div>

							<UButton
								color="error"
								variant="ghost"
								icon="i-lucide-trash-2"
								:aria-label="t('groups.membership_applications.form.actions.remove_option')"
								:disabled="field.options.length <= 1"
								@click="removeOption(index, optionIndex)"
							/>
						</div>
					</div>
				</div>
			</div>
		</UCard>
	</div>
</template>
