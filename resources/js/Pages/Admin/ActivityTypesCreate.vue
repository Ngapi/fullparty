<script setup lang="ts">
import ActivityTypeBuilderForm from "@/components/Admin/ActivityTypes/ActivityTypeBuilderForm.vue";
import PageHeader from "@/components/PageHeader.vue";
import { router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

defineProps<{
	schemaReference: {
		supportedFieldTypes: string[]
		supportedOptionSources: string[]
		rosterSummarySources: string[]
		rosterSummaryComparisonModes: string[]
		rosterSummaryScopeTypes: string[]
		activityDifficulties: string[]
		rosterSummarySourceOptions: Record<string, Array<{ value: number, label: string }>>
	}
	existingTags: string[]
}>();

const { t } = useI18n();

const createLocalizedRecord = () => ({ en: '' });

const form = useForm({
	slug: '',
	draft_name: createLocalizedRecord(),
	draft_description: createLocalizedRecord(),
	draft_small_image: null as File | null,
	draft_banner_image: null as File | null,
	draft_small_image_url: null as string | null,
	draft_banner_image_url: null as string | null,
	draft_difficulty: 'normal',
	draft_default_min_item_level: null as number | null,
	tags: [],
	draft_layout_schema: {
		groups: [
			{
				key: 'party-1',
				label: {
					en: 'Party 1',
					de: '',
					fr: '',
					ja: '',
				},
				size: 8,
			},
		],
	},
	draft_slot_schema: [],
	draft_application_schema: [],
	draft_roster_summary_presets: [],
	draft_progress_schema: {
		milestones: [],
	},
	draft_bench_size: 0,
	draft_prog_points: [],
	draft_fflogs_zone_id: null,
	is_active: true,
});

const goBack = () => {
	router.get('/admin/activity-types');
};

const submit = () => {
	form.post('/admin/activity-types', {
		forceFormData: true,
	});
};
</script>

<template>
	<div class="w-full">
		<UButton
			:label="t('admin.activity_types.back')"
			icon="i-lucide-arrow-left"
			variant="ghost"
			color="neutral"
			@click.stop="goBack"
		/>
		<PageHeader
			:title="t('admin.activity_types.create_title')"
			:subtitle="t('admin.activity_types.create_subtitle')"
		/>

		<div class="mt-6">
			<ActivityTypeBuilderForm
				:form="form"
				:schema-reference="schemaReference"
				:existing-tags="existingTags"
				:submit-label="t('general.create')"
				back-href="/admin/activity-types"
				@submit="submit"
			/>
		</div>
	</div>
</template>
