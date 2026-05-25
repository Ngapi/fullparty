<script setup lang="ts">
import type { GroupDashboardGroup } from "@/Types/Groups";
import GroupDashboardBanner from "@/components/Groups/GroupDashboardBanner.vue";
import GroupDashboardInfoCard from "@/components/Groups/GroupDashboardInfoCard.vue";

defineProps<{
	group: GroupDashboardGroup
}>();
</script>

<template>
	<div class="w-full">
		<GroupDashboardBanner :group="group" />
		<div class="relative -mt-20 px-4 sm:-mt-24 sm:px-6 lg:-mt-28 lg:px-8">
			<GroupDashboardInfoCard :group="group" />
		</div>
	</div>
</template>
