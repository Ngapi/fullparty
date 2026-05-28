<script setup lang="ts">
import AppLocaleSelect from "@/components/Navigation/AppLocaleSelect.vue"
import { router, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { computed } from "vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n()
const page = usePage()

const heroStyle = {
	backgroundImage: 'url("/landing.png")',
}
const logoUrl = "/logo_white.png"
const discordUrl = computed(() => {
	const siteLinks = page.props.site_links as { discord?: string | null } | undefined

	return siteLinks?.discord ?? null
})
const trustBadges = [
	{ key: "ffxiv", icon: "i-lucide-gamepad-2" },
	{ key: "raiders", icon: "i-lucide-shield-check" },
	{ key: "communities", icon: "i-lucide-users" },
]

const goToHome = () => {
	router.get(route("home"))
}

const goToLogin = () => {
	router.get(route("login"))
}

const goToRegister = () => {
	router.get(route("register"))
}

const startFirstRun = () => {
	goToRegister()
}

const scrollToSection = (sectionId: string) => {
	document.getElementById(sectionId)?.scrollIntoView({
		behavior: "smooth",
		block: "start",
	})
}
</script>

<template>
	<section
		class="relative flex min-h-screen bg-cover bg-center bg-no-repeat"
		:style="heroStyle"
	>
		<div class="absolute inset-0 bg-neutral-950/55" />
		<div class="absolute inset-0 bg-linear-to-r from-neutral-950/90 via-neutral-950/45 to-neutral-950/20" />
		<div class="absolute inset-x-0 bottom-0 h-48 bg-linear-to-t from-neutral-950 to-transparent" />

		<header class="absolute inset-x-0 top-0 z-20 grid grid-cols-2 items-center gap-4 px-4 py-5 sm:px-6 md:grid-cols-[1fr_auto_1fr] lg:px-10">
			<div class="flex min-w-0 items-center justify-start">
				<button
					type="button"
					class="flex shrink-0 items-center"
					:aria-label="t('landing.nav.home')"
					@click="goToHome"
				>
					<img
						:src="logoUrl"
						alt="FullParty"
						class="h-14 w-auto"
					>
				</button>
			</div>

			<nav class="order-3 col-span-2 hidden items-center justify-center gap-5 text-md font-medium text-neutral-200 md:order-none md:col-span-1 md:flex">
				<a href="#features" class="transition hover:text-white" @click.prevent="scrollToSection('features')">
					{{ t("landing.nav.features") }}
				</a>
				<a href="#this-week" class="transition hover:text-white" @click.prevent="scrollToSection('this-week')">
					{{ t("landing.nav.this_week") }}
				</a>
				<a href="#players" class="transition hover:text-white" @click.prevent="scrollToSection('players')">
					{{ t("landing.nav.players") }}
				</a>
				<a href="#leaders" class="transition hover:text-white" @click.prevent="scrollToSection('leaders')">
					{{ t("landing.nav.leaders") }}
				</a>
				<a
					:href="discordUrl ?? '#'"
					target="_blank"
					rel="noopener noreferrer"
					class="transition hover:text-white"
					:class="{ 'pointer-events-none opacity-60': !discordUrl }"
				>
					{{ t("landing.nav.discord") }}
				</a>
			</nav>

			<div class="flex shrink-0 items-center justify-end gap-1 sm:gap-2">
				<AppLocaleSelect variant="ghost" />
				<UButton
					color="neutral"
					variant="ghost"
					:label="t('auth.login')"
					class="text-neutral-100 hover:text-white"
					@click="goToLogin"
				/>
				<UButton
					color="neutral"
					variant="solid"
					:label="t('auth.register')"
					@click="goToRegister"
				/>
			</div>
		</header>

		<div class="relative z-10 flex w-full items-end px-6 pb-28 pt-32 lg:px-10 lg:pb-36">
			<div class="max-w-4xl">
				<UBadge
					color="primary"
					variant="soft"
					icon="i-lucide-sparkles"
					:label="t('landing.hero.badge')"
					class="bg-violet-500/20 px-4 py-2 text-sm font-semibold text-violet-100 ring-violet-300/25"
				/>

				<h1 class="landing-display-font mt-6 max-w-4xl text-5xl font-semibold leading-tight text-white sm:text-6xl lg:text-7xl">
					{{ t("landing.hero.title_prefix") }}
					<span class="text-violet-300">{{ t("landing.hero.title_accent") }}</span>
				</h1>
				<p class="mt-6 max-w-2xl text-base leading-8 text-neutral-200 sm:text-lg">
					{{ t("landing.hero.subtitle") }}
				</p>
				<div class="mt-8 flex flex-col gap-3 sm:flex-row">
					<UButton
						size="xl"
						color="primary"
						icon="i-lucide-calendar-plus"
						:label="t('landing.hero.primary_action')"
						@click="startFirstRun"
					/>
					<UButton
						size="xl"
						color="neutral"
						variant="outline"
						icon="i-lucide-search"
						:label="t('landing.hero.secondary_action')"
						class="border-white/25 bg-white/5 text-white hover:bg-white/10"
						@click="scrollToSection('features')"
					/>
				</div>

				<div class="mt-7 flex flex-wrap gap-2">
					<UBadge
						v-for="badge in trustBadges"
						:key="badge.key"
						color="neutral"
						variant="soft"
						:icon="badge.icon"
						:label="t(`landing.hero.trust_badges.${badge.key}`)"
						class="bg-white/10 text-neutral-100 ring-white/15"
					/>
				</div>
			</div>
		</div>

		<div class="absolute inset-x-0 bottom-8 z-10 px-6 lg:px-10">
			<div class="mx-auto flex max-w-4xl items-center gap-5 text-center text-sm font-semibold text-violet-200">
				<div class="h-px flex-1 bg-linear-to-r from-transparent via-violet-400/60 to-violet-400/20" />
				<UIcon name="i-lucide-sparkle" class="h-4 w-4 shrink-0 text-violet-300" />
				<p class="landing-display-font shrink-0 text-sm tracking-[0.12em] sm:text-base">
					{{ t("landing.hero.separator") }}
				</p>
				<UIcon name="i-lucide-sparkle" class="h-4 w-4 shrink-0 text-violet-300" />
				<div class="h-px flex-1 bg-linear-to-l from-transparent via-violet-400/60 to-violet-400/20" />
			</div>
		</div>
	</section>
</template>

<style>
@import url("https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap");

.landing-display-font {
	font-family: "Sora", ui-sans-serif, system-ui, sans-serif;
}
</style>
