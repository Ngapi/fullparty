<script setup lang="ts">
import type { LandingPageProps } from "../Types/Landing"
import HeroLandingLayout from "@/Layouts/HeroLandingLayout.vue"
import LandingAudienceSection from "@/components/Landing/LandingAudienceSection.vue"
import LandingFeaturesSection from "@/components/Landing/LandingFeaturesSection.vue"
import LandingFooterSection from "@/components/Landing/LandingFooterSection.vue"
import LandingHeroSection from "@/components/Landing/LandingHeroSection.vue"
import LandingThisWeekSection from "@/components/Landing/LandingThisWeekSection.vue"
import SeoHead from "@/components/Shared/SeoHead.vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n()
const props = defineProps<LandingPageProps>()
</script>

<template>
	<SeoHead
		:title="t('meta.seo.home.title')"
		:description="t('meta.seo.home.description')"
	/>

	<main class="relative min-h-screen scroll-smooth overflow-hidden bg-neutral-950 text-white">
		<LandingHeroSection />
		<LandingFeaturesSection />
		<LandingThisWeekSection :this-week="props.landing.this_week" />
		<LandingAudienceSection />
		<LandingFooterSection />
	</main>
</template>

<script lang="ts">
export default {
	layout: HeroLandingLayout,
}
</script>
