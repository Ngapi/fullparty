<script setup lang="ts">
import { useI18n } from "vue-i18n"

const { t } = useI18n()

const featureCards = [
	{ key: "applications", icon: "i-lucide-clipboard-check" },
	{ key: "rosters", icon: "i-lucide-layout-grid" },
	{ key: "groups", icon: "i-lucide-users-round" },
	{ key: "scheduling", icon: "i-lucide-calendar-clock" },
]
</script>

<template>
	<section
		id="features"
		class="relative scroll-mt-8 bg-neutral-950 px-6 py-8 lg:px-10"
		:aria-label="t('landing.nav.features')"
	>
		<div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-violet-400/30 to-transparent" />
		<div class="mx-auto grid max-w-7xl grid-cols-1 items-start border-white/10 md:grid-cols-2 xl:grid-cols-4">
			<article
				v-for="(feature, index) in featureCards"
				:key="feature.key"
				class="relative flex min-h-80 flex-col items-center justify-center px-8 py-12 text-center"
				:class="{
					'border-t border-white/10 md:border-t-0': index > 0,
					'md:border-t md:border-white/10 xl:border-t-0': index > 1,
					'md:border-l md:border-white/10': index % 2 === 1,
					'xl:border-l xl:border-white/10': index > 0,
				}"
			>
				<div class="flex h-20 w-20 items-center justify-center rounded-full border border-violet-300/20 bg-violet-500/10 shadow-2xl shadow-violet-950/40">
					<UIcon
						:name="feature.icon"
						class="h-9 w-9 text-violet-200"
					/>
				</div>
				<h2 class="mt-8 text-xl font-semibold text-neutral-100">
					{{ t(`landing.features.items.${feature.key}.title`) }}
				</h2>
				<p class="mt-5 max-w-xs text-base leading-8 text-neutral-400">
					{{ t(`landing.features.items.${feature.key}.description`) }}
				</p>
			</article>
		</div>
	</section>
</template>
