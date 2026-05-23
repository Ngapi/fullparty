<script setup lang="ts">
import { computed } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import PageHeader from "@/components/PageHeader.vue";
import { useI18n } from "vue-i18n";
import ActivityCreateForm from "@/components/Groups/Activities/ActivityCreateForm.vue";
import ActivityCreateSummaryCard from "@/components/Groups/Activities/ActivityCreateSummaryCard.vue";
import type { ActivityMetadataOptions, ActivityTypeOption, OrganizerCharacterOption } from "@/Types/ActivityCore";

const props = defineProps<{
	group: {
		id: number
		name: string
		slug: string
		datacenter: string | null
		current_user_role: string | null
		permissions: {
			can_manage_activities: boolean
		}
	}
	activityTypes: ActivityTypeOption[]
	organizerCharacters: OrganizerCharacterOption[]
	activityOptions: ActivityMetadataOptions
	prefilledStartsAt: string | null
}>();

const { t } = useI18n();
const defaultOrganizerCharacter = props.organizerCharacters[0] ?? null;
const defaultActivityType = props.activityTypes[0] ?? null;

const form = useForm({
	activity_type_id: defaultActivityType?.id ?? null,
	organized_by_user_id: defaultOrganizerCharacter?.user_id ?? null,
	organized_by_character_id: defaultOrganizerCharacter?.id ?? null,
	status: 'planned',
	title: '',
	notes: '',
	starts_at: props.prefilledStartsAt,
	duration_hours: 2,
	datacenter: props.group.datacenter,
	intensity: props.activityOptions.intensities[0] ?? 'casual',
	min_item_level: defaultActivityType?.default_min_item_level ?? null,
	beginner_friendly: false,
	run_style: props.activityOptions.runStyles[0] ?? 'progression',
	target_prog_point_key: null as string | null,
	is_public: true,
	needs_application: true,
	allow_guest_applications: false,
});

const goBack = () => {
	router.get(route('groups.dashboard.activities.index', props.group.slug));
};

const hasActivityTypes = computed(() => props.activityTypes.length > 0);
</script>

<template>
	<div class="w-full">
		<UButton
			:label="t('groups.activities.back')"
			icon="i-lucide-arrow-left"
			variant="ghost"
			color="neutral"
			@click.stop="goBack"
		/>
		<PageHeader
			:title="t('groups.activities.create.title')"
			:subtitle="t('groups.activities.create.subtitle')"
		>
		</PageHeader>

		<UAlert
			v-if="!hasActivityTypes"
			class="mt-4"
			color="warning"
			variant="subtle"
			icon="i-lucide-triangle-alert"
			:title="t('groups.activities.create.no_types_title')"
			:description="t('groups.activities.create.no_types_description')"
		/>

		<div v-else class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
			<ActivityCreateForm
				:form="form"
				:group-slug="group.slug"
				:activity-types="activityTypes"
				:organizer-characters="organizerCharacters"
				:activity-options="activityOptions"
			/>
			<ActivityCreateSummaryCard
				:form="form"
				:activity-types="activityTypes"
				:organizer-characters="organizerCharacters"
			/>
		</div>
	</div>
</template>
