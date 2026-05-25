<script setup lang="ts">
import type { GroupDashboardGroup } from "@/Types/Groups";
import GroupDashboardBanner from "@/components/Groups/GroupDashboardBanner.vue";
import GroupDashboardInfoCard from "@/components/Groups/GroupDashboardInfoCard.vue";
import GroupDashboardUpcomingRunsSection from "@/components/Groups/GroupDashboardUpcomingRunsSection.vue";
import GroupDashboardWeekOverviewSection from "@/components/Groups/GroupDashboardWeekOverviewSection.vue";
import GroupDashboardContentSection from "@/components/Groups/GroupDashboardContentSection.vue";

defineProps<{
	group: GroupDashboardGroup
}>();
</script>

<template>
	<div class="w-full">
		<GroupDashboardBanner :group="group" />
		<div class="flex flex-row  pl-4">
			<GroupDashboardInfoCard class="-mt-20 sm:-mt-24 sm:pl-6 lg:-mt-28 lg:pl-8" :group="group" />

			<div class="overflow-hidden">
				<GroupDashboardUpcomingRunsSection :activities="group.upcoming_activities" />
			</div>
		</div>
		<div class="w-full flex flex-col gap-4">
			<GroupDashboardWeekOverviewSection
				:activities="group.current_week_activities"
				:week-start-date="group.current_week.start_date"
				:week-end-date="group.current_week.end_date"
			/>
			<GroupDashboardContentSection
				:summary="group.content_summary"
				:items="group.content_items"
			/>
		</div>
	</div>
</template>
