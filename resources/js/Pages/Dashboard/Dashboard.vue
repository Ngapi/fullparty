<script setup lang="ts">
import HomepageActivityOverview from "@/components/Home/HomepageActivityOverview.vue"
import HomepageBanner from "@/components/Home/HomepageBanner.vue"
import type { DashboardPageProps } from "../../Types/Dashboard"

const props = defineProps<DashboardPageProps>()
</script>

<template>
	<div class="w-full">
		<HomepageBanner
			:profile="props.profile"
			:home-profile-options="props.homeProfileOptions"
			:home-banner="props.homeBanner"
			:home-banner-details="props.homeBannerDetails"
			:home-account-completion="props.homeAccountCompletion"
		/>
		<HomepageActivityOverview :overview="props.homeActivityOverview" />
	</div>
</template>
