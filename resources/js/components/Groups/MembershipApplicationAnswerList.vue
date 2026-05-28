<script setup lang="ts">
import type { MembershipApplicationAnswerValue, MembershipApplicationFormField } from "@/Types/Groups";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { localizedValue } from "@/utils/localizedValue";

const props = defineProps<{
	fields: MembershipApplicationFormField[]
	answers: Record<string, MembershipApplicationAnswerValue>
}>();

const { locale, t } = useI18n();

const rows = computed(() => props.fields.map((field) => {
	const answer = props.answers[field.id] ?? null;
	const option = field.options.find((entry) => entry.id === answer);

	return {
		id: field.id,
		label: localizedValue(field.name, locale.value),
		description: localizedValue(field.description, locale.value),
		value: field.type === "toggle"
			? (answer === null ? null : (answer ? t("groups.membership_applications.answers.yes") : t("groups.membership_applications.answers.no")))
			: field.type === "select"
				? localizedValue(option?.label, locale.value)
				: answer,
	};
}));
</script>

<template>
	<div class="min-w-0 overflow-hidden divide-y divide-default border border-default bg-default">
		<div
			v-for="row in rows"
			:key="row.id"
			class="grid min-w-0 gap-2 p-4 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]"
		>
			<div class="min-w-0">
				<p class="text-sm font-medium text-highlighted break-words [overflow-wrap:anywhere]">
					{{ row.label }}
				</p>
				<p v-if="row.description" class="mt-1 text-xs text-muted break-words [overflow-wrap:anywhere]">
					{{ row.description }}
				</p>
			</div>

			<p
				v-if="row.value !== null && row.value !== ''"
				class="min-w-0 max-w-full text-sm text-toned whitespace-pre-wrap break-words [overflow-wrap:anywhere]"
			>
				{{ row.value }}
			</p>
			<p v-else class="min-w-0 max-w-full text-sm text-muted">
				{{ t("groups.membership_applications.answers.no_answer") }}
			</p>
		</div>
	</div>
</template>
