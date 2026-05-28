<script setup lang="ts">
import type { MembershipApplicationAnswerValue, MembershipApplicationFormField, MembershipApplicationRecord } from "@/Types/Groups";
import { computed } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { route } from "ziggy-js";
import MembershipApplicationAnswerList from "@/components/Groups/MembershipApplicationAnswerList.vue";
import PageHeader from "@/components/PageHeader.vue";
import { localizedValue } from "@/utils/localizedValue";

const props = defineProps<{
	group: {
		id: number
		name: string
		description: string | null
		profile_picture_url: string | null
		banner_image_url: string | null
		datacenter: string | null
		slug: string
		group_type: string
		join_mode: string
		owner: {
			id: number | null
			name: string | null
			avatar_url: string | null
		}
	}
	formSchema: MembershipApplicationFormField[]
	existingApplication: MembershipApplicationRecord | null
}>();

const { locale, t } = useI18n();
const FREE_TEXT_ANSWER_MAX_LENGTH = 1000;

const answerForField = (field: MembershipApplicationFormField): MembershipApplicationAnswerValue => {
	const existingAnswer = props.existingApplication?.answers?.[field.id];

	if (existingAnswer !== null && existingAnswer !== undefined) {
		return existingAnswer;
	}

	if (field.type === "toggle") {
		return false;
	}

	return "";
};

const initialAnswers = Object.fromEntries(
	props.formSchema.map((field) => [field.id, answerForField(field)]),
) as Record<string, MembershipApplicationAnswerValue>;

const form = useForm({
	answers: initialAnswers,
});

const hasPendingApplication = computed(() => props.existingApplication?.status === "pending");
const isLockedApplication = computed(() => props.existingApplication?.status === "approved");
const submitLabel = computed(() => hasPendingApplication.value
	? t("groups.membership_applications.apply.actions.update")
	: t("groups.membership_applications.apply.actions.submit"));
const statusColor = computed(() => {
	if (props.existingApplication?.status === "approved") {
		return "success";
	}

	if (props.existingApplication?.status === "declined") {
		return "error";
	}

	return "warning";
});
const bannerUrl = computed(() => props.group.banner_image_url ?? "/prereqimages/forked.jpg");

const optionsForField = (field: MembershipApplicationFormField) => field.options.map((option) => ({
	label: localizedValue(option.label, locale.value),
	value: option.id,
}));

const fieldError = (fieldId: string) => form.errors[`answers.${fieldId}`] ?? null;
const isFreeTextField = (field: MembershipApplicationFormField) => field.type === "small_text" || field.type === "big_text";
const answerTextLength = (fieldId: string) => {
	const value = form.answers[fieldId];

	return typeof value === "string" ? Array.from(value).length : 0;
};
const answerCounter = (field: MembershipApplicationFormField) => {
	if (!isFreeTextField(field)) {
		return undefined;
	}

	return `${answerTextLength(field.id)} / ${FREE_TEXT_ANSWER_MAX_LENGTH}`;
};

const submit = () => {
	const options = {
		preserveScroll: true,
	};

	if (hasPendingApplication.value) {
		form.put(route("groups.membership-applications.update", props.group.slug), options);

		return;
	}

	form.post(route("groups.membership-applications.store", props.group.slug), options);
};

const backToGroups = () => {
	router.get(route("groups.index"));
};
</script>

<template>
	<div class="mx-auto w-full max-w-5xl">
		<PageHeader
			:title="t('groups.membership_applications.apply.title', { group: group.name })"
			:subtitle="t('groups.membership_applications.apply.subtitle')"
		>
			<UButton
				color="neutral"
				variant="outline"
				icon="i-lucide-arrow-left"
				:label="t('groups.membership_applications.apply.actions.back')"
				@click="backToGroups"
			/>
		</PageHeader>

		<div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
			<div class="space-y-6">
				<UAlert
					v-if="existingApplication"
					:color="statusColor"
					variant="soft"
					icon="i-lucide-clipboard-check"
					:title="t(`groups.membership_applications.statuses.${existingApplication.status}`)"
					:description="t(`groups.membership_applications.apply.status_messages.${existingApplication.status}`)"
				/>

				<UCard :ui="{ root: 'rounded-sm', body: 'p-4 sm:p-4' }">
					<form class="space-y-5" @submit.prevent="submit">
						<div
							v-for="field in formSchema"
							:key="field.id"
							class="border-b border-default pb-5 last:border-b-0 last:pb-0"
						>
							<UFormField
								:label="localizedValue(field.name, locale)"
								:help="localizedValue(field.description, locale)"
								:hint="answerCounter(field)"
								:error="fieldError(field.id)"
								:required="field.required"
							>
								<UInput
									v-if="field.type === 'small_text'"
									v-model="form.answers[field.id]"
									class="w-full"
									:maxlength="FREE_TEXT_ANSWER_MAX_LENGTH"
									:disabled="isLockedApplication || form.processing"
									:ui="{ base: 'rounded-none' }"
								/>

								<UTextarea
									v-else-if="field.type === 'big_text'"
									v-model="form.answers[field.id]"
									class="w-full"
									:rows="5"
									:maxlength="FREE_TEXT_ANSWER_MAX_LENGTH"
									:disabled="isLockedApplication || form.processing"
									:ui="{ base: 'rounded-none' }"
								/>

								<USelect
									v-else-if="field.type === 'select'"
									v-model="form.answers[field.id]"
									class="w-full"
									:items="optionsForField(field)"
									value-key="value"
									:disabled="isLockedApplication || form.processing"
									:ui="{ base: 'rounded-none' }"
								/>

								<div v-else class="flex items-center justify-between gap-3 border border-default bg-muted/20 px-3 py-3">
									<p class="text-sm text-muted">
										{{ t("groups.membership_applications.apply.toggle_answer") }}
									</p>
									<USwitch
										:model-value="Boolean(form.answers[field.id])"
										:disabled="isLockedApplication || form.processing"
										@update:model-value="(value) => form.answers[field.id] = Boolean(value)"
									/>
								</div>
							</UFormField>
						</div>

						<div class="flex flex-wrap items-center justify-end gap-2 pt-2">
							<UButton
								type="submit"
								color="primary"
								icon="i-lucide-send"
								:label="submitLabel"
								:loading="form.processing"
								:disabled="isLockedApplication"
							/>
						</div>
					</form>
				</UCard>

				<div v-if="existingApplication && !hasPendingApplication" class="space-y-3">
					<p class="text-sm font-semibold text-highlighted">
						{{ t("groups.membership_applications.apply.submitted_answers") }}
					</p>
					<MembershipApplicationAnswerList
						:fields="existingApplication.form_snapshot"
						:answers="existingApplication.answers"
					/>
				</div>
			</div>

			<aside class="space-y-4">
				<UCard :ui="{ root: 'rounded-sm overflow-hidden', body: 'p-0 sm:p-0' }">
					<img
						:src="bannerUrl"
						:alt="group.name"
						class="h-32 w-full object-cover"
					>
					<div class="space-y-3 p-4">
						<div class="flex items-center gap-3">
							<div class="flex size-12 shrink-0 items-center justify-center overflow-hidden border border-default bg-muted">
								<img
									v-if="group.profile_picture_url"
									:src="group.profile_picture_url"
									:alt="group.name"
									class="h-full w-full object-cover"
								>
								<UIcon v-else name="i-lucide-users" class="size-5 text-muted" />
							</div>
							<div class="min-w-0">
								<p class="font-semibold text-highlighted break-words [overflow-wrap:anywhere]">
									{{ group.name }}
								</p>
								<p class="text-sm text-muted">
									{{ group.datacenter }}
								</p>
							</div>
						</div>
						<p v-if="group.description" class="text-sm text-muted break-words [overflow-wrap:anywhere]">
							{{ group.description }}
						</p>
					</div>
				</UCard>
			</aside>
		</div>
	</div>
</template>
