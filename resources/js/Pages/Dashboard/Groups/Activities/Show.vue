<script setup lang="ts">
import {useI18n} from "vue-i18n";
import {router} from "@inertiajs/vue3";
// @ts-ignore
import {route} from "ziggy-js";
import ApplicantQueue from "@/resources/js/components/Groups/Activities/ApplicantQueue.vue";
import {ActivityData} from "@/resources/js/Types/ActivityManagement";
import {GroupMemberData} from "@/resources/js/Types/Groups";
// @ts-ignore
import ActivityOverview from "@/components/Groups/Activities/ActivityOverview.vue";
import {computed} from "vue";

const props = defineProps<{
	group: GroupMemberData,
	activity: ActivityData
}>();

const { t } = useI18n()

const isEditable = computed(() => !(props.activity.status === "complete" || props.activity.status === "cancelled"))

const goBack = () => {
	router.get(route('groups.dashboard.activities.show', {
		group: props.group.slug,
		activity: props.activity.id,
	}));
}
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

		<ActivityOverview
			:activity="activity"
			:group_member_data="group"
			:is-editable="isEditable"
		>
<!--			<RosterSummary />-->
<!--			<CompletitionSummary />-->
		</ActivityOverview>

		<div class="flex flex-row gap-4 items-start justify-start">
<!--			<Roster class="w-8/12"/>-->
<!--			<ApplicantQueue class="w-4/12"/>-->
		</div>
	</div>
</template>
