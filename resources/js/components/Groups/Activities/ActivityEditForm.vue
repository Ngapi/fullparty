<script setup lang="ts">
import type { ActivityMetadataOptions, ActivityTypeOption, OrganizerCharacterOption } from "@/Types/ActivityCore";
import { computed, ref, toRef, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useActivityFormFields } from "@/components/Groups/Activities/useActivityFormFields";
import { usePage } from "@inertiajs/vue3";

const props = defineProps<{
	activityTypes: ActivityTypeOption[]
	organizerCharacters: OrganizerCharacterOption[]
	activityOptions: ActivityMetadataOptions
	submitLabel?: string
	form: {
		activity_type_id: number | null
		organized_by_user_id: number | null
		organized_by_character_id: number | null
		status: string
		title: string
		notes: string
		starts_at: string | null
		duration_hours: number
		datacenter: string | null
		intensity: string
		min_item_level: number | null
		beginner_friendly: boolean
		run_style: string
		target_prog_point_key: string | null
		needs_application: boolean
		allow_guest_applications: boolean
		errors: Record<string, string | undefined>
		processing: boolean
	}
}>();

const emit = defineEmits<{
	submit: []
}>();

const { t } = useI18n();
const page = usePage();
const datacenterOptions = computed(() => page.props.lookups?.datacenters ?? []);
const minimumItemLevelTouched = ref(false);
const {
	organizerCharacterItems,
	selectedOrganizerCharacter,
	progPointItems,
	updateOrganizerCharacter,
	startDate,
	startHour,
	startMinute,
	hourItems,
	minuteItems,
	durationItems,
	selectedDurationOption,
	isCustomDuration,
} = useActivityFormFields(
	toRef(props, 'activityTypes'),
	toRef(props, 'organizerCharacters'),
	props.form,
	{ mode: 'edit' },
);

const canSubmit = computed(() => Boolean(props.form.status));

const intensityItems = computed(() => props.activityOptions.intensities.map((value) => ({
	label: t(`groups.activities.intensities.${value}`),
	value,
})));

const runStyleItems = computed(() => props.activityOptions.runStyles.map((value) => ({
	label: t(`groups.activities.run_styles.${value}`),
	value,
})));

const minimumItemLevelEnabled = computed({
	get: () => props.form.min_item_level !== null && props.form.min_item_level !== undefined,
	set: (enabled: boolean) => {
		minimumItemLevelTouched.value = true;
		props.form.min_item_level = enabled ? 1 : null;
	},
});

const updateMinimumItemLevel = (value: unknown) => {
	minimumItemLevelTouched.value = true;
	props.form.min_item_level = value === '' || value === null
		? null
		: Math.min(9999, Math.max(1, Number(value) || 1));
};

const submit = () => {
	emit('submit');
};

watch(() => props.form.needs_application, (needsApplication) => {
	if (!needsApplication) {
		props.form.allow_guest_applications = false;
	}
}, { immediate: true });
</script>

<template>
	<UCard class="dark:bg-elevated/25">
		<template #header>
			<div class="flex flex-col gap-1">
				<p class="font-semibold text-md">{{ t('groups.activities.edit.form.title') }}</p>
				<p class="text-sm text-muted">{{ t('groups.activities.edit.form.subtitle') }}</p>
			</div>
		</template>

		<form class="flex flex-col gap-8" @submit.prevent="submit">
			<section class="space-y-5">
				<div class="space-y-1">
					<p class="font-medium text-sm">{{ t('groups.activities.create.sections.basics.title') }}</p>
					<p class="text-sm text-muted">{{ t('groups.activities.edit.sections.basics.subtitle') }}</p>
				</div>

				<div class="grid grid-cols-1 gap-5">
					<UFormField
						:label="t('groups.activities.create.fields.organizer.label')"
						:error="form.errors.organized_by_character_id || form.errors.organized_by_user_id"
						required
					>
						<USelectMenu
							:model-value="selectedOrganizerCharacter"
							class="w-full"
							size="lg"
							:avatar="{
								src: selectedOrganizerCharacter?.avatar_url,
								loading: 'lazy'
							}"
							:items="organizerCharacterItems"
							:placeholder="t('groups.activities.create.fields.organizer.placeholder')"
							@update:model-value="updateOrganizerCharacter"
						/>
					</UFormField>
				</div>

				<div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
					<UFormField
						:label="t('groups.activities.create.fields.title.label')"
						:error="form.errors.title"
					>
						<UInput
							v-model="form.title"
							size="lg"
							class="w-full"
							:placeholder="t('groups.activities.create.fields.title.placeholder')"
						/>
					</UFormField>

					<UFormField
						v-if="progPointItems.length > 0"
						:label="t('groups.activities.create.fields.prog_point.label')"
						:error="form.errors.target_prog_point_key"
					>
						<USelectMenu
							v-model="form.target_prog_point_key"
							size="lg"
							class="w-full"
							:items="progPointItems"
							value-key="value"
							:placeholder="t('groups.activities.create.fields.prog_point.placeholder')"
						/>
					</UFormField>
				</div>

			</section>

			<div class="border-t border-default"></div>

			<section class="space-y-5">
				<div class="space-y-1">
					<p class="font-medium text-sm">{{ t('groups.activities.create.sections.schedule.title') }}</p>
					<p class="text-sm text-muted">{{ t('groups.activities.create.sections.schedule.subtitle') }}</p>
					<p class="text-xs text-muted">{{ t('groups.activities.create.fields.starts_at.server_time_hint') }}</p>
				</div>

				<div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
					<UFormField
						:label="t('groups.activities.create.fields.start_date.label')"
						:error="form.errors.starts_at"
					>
						<UInput
							v-model="startDate"
							type="date"
							size="lg"
							class="w-full"
						/>
					</UFormField>

					<UFormField
						:label="t('groups.activities.create.fields.start_time.label')"
						:error="form.errors.starts_at"
					>
						<div class="grid grid-cols-[minmax(0,1fr)_24px_minmax(0,1fr)] items-center gap-3">
							<USelect
								v-model="startHour"
								size="lg"
								class="w-full"
								:items="hourItems"
								value-key="value"
								:placeholder="t('groups.activities.create.fields.start_time.hour_placeholder')"
							/>

							<div class="text-center font-medium text-muted">:</div>

							<USelect
								v-model="startMinute"
								size="lg"
								class="w-full"
								:items="minuteItems"
								value-key="value"
								:placeholder="t('groups.activities.create.fields.start_time.minute_placeholder')"
							/>
						</div>
					</UFormField>
				</div>

				<UFormField
					:label="t('groups.activities.create.fields.duration.label')"
					:error="form.errors.duration_hours"
					required
				>
					<div class="flex flex-col gap-3 xl:flex-row xl:items-center">
						<USelectMenu
							v-model="selectedDurationOption"
							size="lg"
							class="w-full xl:max-w-xs"
							:items="durationItems"
							value-key="value"
						/>

						<UInput
							:model-value="String(form.duration_hours ?? '')"
							type="number"
							min="1"
							max="24"
							size="lg"
							class="w-full xl:w-32"
							:disabled="!isCustomDuration"
							:placeholder="t('groups.activities.create.fields.duration.placeholder')"
							@focus="selectedDurationOption = 'custom'"
							@update:model-value="(value) => form.duration_hours = Math.min(24, Math.max(1, Number(value) || 1))"
						/>
					</div>
				</UFormField>
			</section>

			<div class="border-t border-default"></div>

			<section class="space-y-5">
				<div class="space-y-1">
					<p class="font-medium text-sm">{{ t('groups.activities.create.sections.run_details.title') }}</p>
					<p class="text-sm text-muted">{{ t('groups.activities.edit.sections.run_details.subtitle') }}</p>
				</div>

				<div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
					<UFormField
						:label="t('groups.activities.create.fields.datacenter.label')"
						:error="form.errors.datacenter"
						required
					>
						<USelect
							v-model="form.datacenter"
							size="lg"
							class="w-full"
							:items="datacenterOptions"
							value-key="value"
							:placeholder="t('groups.activities.create.fields.datacenter.placeholder')"
						/>
					</UFormField>

					<UFormField
						:label="t('groups.activities.create.fields.run_style.label')"
						:error="form.errors.run_style"
						required
					>
						<USelect
							v-model="form.run_style"
							size="lg"
							class="w-full"
							:items="runStyleItems"
							value-key="value"
							:placeholder="t('groups.activities.create.fields.run_style.placeholder')"
						/>
					</UFormField>

					<UFormField
						:label="t('groups.activities.create.fields.intensity.label')"
						:error="form.errors.intensity"
						required
					>
						<USelect
							v-model="form.intensity"
							size="lg"
							class="w-full"
							:items="intensityItems"
							value-key="value"
							:placeholder="t('groups.activities.create.fields.intensity.placeholder')"
						/>
					</UFormField>

					<div class="flex flex-col gap-3 rounded-lg border border-default px-4 py-4">
						<UFormField
							:label="t('groups.activities.create.fields.min_item_level.enabled_label')"
							:description="t('groups.activities.create.fields.min_item_level.enabled_help')"
							orientation="horizontal"
						>
							<USwitch v-model="minimumItemLevelEnabled" />
						</UFormField>

						<UFormField
							:label="t('groups.activities.create.fields.min_item_level.label')"
							:error="form.errors.min_item_level"
						>
							<UInput
								:model-value="form.min_item_level ?? ''"
								type="number"
								min="1"
								max="9999"
								size="lg"
								class="w-full"
								:disabled="!minimumItemLevelEnabled"
								:placeholder="t('groups.activities.create.fields.min_item_level.placeholder')"
								@update:model-value="updateMinimumItemLevel"
							/>
						</UFormField>
					</div>

					<UFormField
						:label="t('groups.activities.create.fields.beginner_friendly.label')"
						:description="t('groups.activities.create.fields.beginner_friendly.help')"
						:error="form.errors.beginner_friendly"
						orientation="horizontal"
						class="rounded-lg border border-default px-4 py-4"
					>
						<USwitch v-model="form.beginner_friendly" />
					</UFormField>
				</div>
			</section>

			<div class="border-t border-default"></div>

			<section class="space-y-5">
				<div class="space-y-1">
					<p class="font-medium text-sm">{{ t('groups.activities.create.sections.notes.title') }}</p>
					<p class="text-sm text-muted">{{ t('groups.activities.create.sections.notes.subtitle') }}</p>
				</div>

				<UFormField
					:label="t('groups.activities.create.fields.notes.label')"
					:error="form.errors.notes"
				>
					<UTextarea
						v-model="form.notes"
						size="lg"
						class="w-full"
						:rows="5"
						:placeholder="t('groups.activities.create.fields.notes.placeholder')"
					/>
				</UFormField>
			</section>

			<div class="border-t border-default"></div>

			<section class="space-y-5">
				<div class="space-y-1">
					<p class="font-medium text-sm">{{ t('groups.activities.create.sections.access.title') }}</p>
					<p class="text-sm text-muted">{{ t('groups.activities.edit.sections.access.subtitle') }}</p>
				</div>

				<div class="grid grid-cols-1 gap-4">
					<UFormField
						v-if="form.needs_application"
						:label="t('groups.activities.create.fields.allow_guest_applications.label')"
						:description="t('groups.activities.create.fields.allow_guest_applications.help')"
						:error="form.errors.allow_guest_applications"
						orientation="horizontal"
						class="rounded-lg border border-default px-4 py-4"
					>
						<USwitch v-model="form.allow_guest_applications" />
					</UFormField>
				</div>
			</section>

			<div class="flex items-center gap-3 border-t border-default pt-2">
				<UButton
					type="submit"
					color="neutral"
					icon="i-lucide-save"
					size="lg"
					:label="submitLabel || t('groups.activities.edit.submit')"
					:disabled="!canSubmit"
					:loading="form.processing"
				/>
			</div>
		</form>
	</UCard>
</template>
