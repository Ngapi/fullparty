<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { useI18n } from "vue-i18n"

const { t } = useI18n()

const imageUrl_l = "/fmiqo.png"
const imageUrl_r = "/mmiqo.png"
const logoUrl = "/logos/compact.png"

const playerPoints = [
	{ key: "verify", icon: "i-lucide-badge-check" },
	{ key: "apply", icon: "i-lucide-send" },
	{ key: "progress", icon: "i-lucide-trending-up" },
	{ key: "groups", icon: "i-lucide-users-round" },
	{ key: "updates", icon: "i-lucide-bell-ring" },
]

const leaderPoints = [
	{ key: "recruit", icon: "i-lucide-user-check" },
	{ key: "manage", icon: "i-lucide-layout-dashboard" },
	{ key: "schedule", icon: "i-lucide-calendar-clock" },
	{ key: "communicate", icon: "i-lucide-messages-square" },
	{ key: "ready", icon: "i-lucide-list-checks" },
]

const goToRegister = () => {
	router.get(route("register"))
}
</script>

<template>
	<section
		id="players"
		class="relative scroll-mt-8 bg-neutral-950 px-6 py-12 lg:px-10"
		:aria-label="t('landing.audience.title')"
	>
		<span id="leaders" class="absolute top-0 h-px w-px scroll-mt-8" aria-hidden="true" />
		<div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-violet-400/30 to-transparent" />

		<div class="overflow-hidden">
			<div class="grid gap-0 py-10 xl:grid-cols-[minmax(14rem,1fr)_minmax(0,58rem)_minmax(14rem,1fr)]">
				<div class="relative hidden min-h-80 overflow-hidden xl:flex xl:items-end xl:justify-end" aria-hidden="true">
					<img
						:src="imageUrl_l"
						alt=""
						class="h-full max-h-[34rem] w-full object-contain object-bottom opacity-90"
					>
					<div class="absolute inset-x-8 bottom-0 h-px bg-linear-to-r from-transparent via-violet-300/70 to-transparent" />
				</div>

				<div class="px-6 py-4 md:px-8 xl:px-10">
					<div class="text-center">
						<h2 class="landing-display-font text-2xl font-semibold text-neutral-100 md:text-3xl">
							{{ t("landing.audience.title") }}
						</h2>
						<div class="mx-auto mt-5 flex max-w-xs items-center gap-4 text-violet-300">
							<div class="h-px flex-1 bg-linear-to-r from-transparent to-violet-400/60" />
							<UIcon name="i-lucide-sparkle" class="size-4 shrink-0" />
							<div class="h-px flex-1 bg-linear-to-l from-transparent to-violet-400/60" />
						</div>
					</div>

					<div class="mt-10 grid gap-8 xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] xl:gap-0">
						<div class="grid gap-6 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1fr)] md:items-center xl:block">
							<div class="relative hidden min-h-72 overflow-hidden md:flex md:items-end md:justify-center xl:hidden" aria-hidden="true">
								<img
									:src="imageUrl_l"
									alt=""
									class="h-full max-h-80 w-full object-contain object-bottom opacity-90"
								>
								<div class="absolute inset-x-8 bottom-0 h-px bg-linear-to-r from-transparent via-violet-300/70 to-transparent" />
							</div>

							<div class="mx-auto flex max-w-md flex-col justify-start py-4 md:mx-0 md:max-w-none md:px-8 xl:min-h-80 xl:px-10">
								<h3 class="text-center text-2xl font-semibold text-violet-300 md:text-left">
									{{ t("landing.audience.players.title") }}
								</h3>
								<ul class="mt-7 space-y-4">
									<li
										v-for="point in playerPoints"
										:key="point.key"
										class="flex items-start gap-3 text-sm leading-6 text-neutral-300"
									>
										<UIcon :name="point.icon" class="mt-1 size-4 shrink-0 text-violet-300" />
										<span>{{ t(`landing.audience.players.points.${point.key}`) }}</span>
									</li>
								</ul>
							</div>
						</div>

						<div class="flex items-center justify-center gap-5 xl:w-28 xl:flex-col">
							<div class="h-px flex-1 bg-violet-500/25 xl:h-24 xl:w-px xl:flex-none" />
							<div class="flex size-20 shrink-0 items-center justify-center rounded-full bg-violet-700/10 blur-out-2xl border border-white/10 shadow-lg shadow-violet-700/40">
								<img :src="logoUrl" alt="FullParty" class="max-h-9 max-w-14 object-contain">
							</div>
							<div class="h-px flex-1 bg-violet-500/25 xl:h-24 xl:w-px xl:flex-none" />
						</div>

						<div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)] md:items-center xl:block">
							<div class="mx-auto flex max-w-md flex-col justify-start py-4 md:mx-0 md:max-w-none md:px-8 xl:min-h-80 xl:px-10">
								<h3 class="text-center text-2xl font-semibold text-violet-300 md:text-left">
									{{ t("landing.audience.leaders.title") }}
								</h3>
								<ul class="mt-7 space-y-4">
									<li
										v-for="point in leaderPoints"
										:key="point.key"
										class="flex items-start gap-3 text-sm leading-6 text-neutral-300"
									>
										<UIcon :name="point.icon" class="mt-1 size-4 shrink-0 text-violet-300" />
										<span>{{ t(`landing.audience.leaders.points.${point.key}`) }}</span>
									</li>
								</ul>
							</div>

							<div class="relative hidden min-h-72 overflow-hidden md:flex md:items-end md:justify-center xl:hidden" aria-hidden="true">
								<img
									:src="imageUrl_r"
									alt=""
									class="h-full max-h-80 w-full object-contain object-bottom opacity-90"
								>
								<div class="absolute inset-x-8 bottom-0 h-px bg-linear-to-r from-transparent via-violet-300/70 to-transparent" />
							</div>
						</div>
					</div>
				</div>

				<div class="relative hidden min-h-80 overflow-hidden xl:flex xl:items-end xl:justify-start" aria-hidden="true">
					<img
						:src="imageUrl_r"
						alt=""
						class="h-full max-h-[34rem] w-full object-contain object-bottom opacity-90"
					>
					<div class="absolute inset-x-8 bottom-0 h-px bg-linear-to-r from-transparent via-violet-300/70 to-transparent" />
				</div>
			</div>

			<div class="flex flex-col items-center justify-center gap-5 border-t border-white/10 px-6 py-7 text-center md:flex-row md:text-left">
				<p class="landing-display-font text-lg font-medium text-neutral-100">
					{{ t("landing.audience.cta_text") }}
				</p>
				<UButton
					color="primary"
					size="lg"
					icon="i-lucide-sparkles"
					:label="t('landing.audience.cta_action')"
					@click="goToRegister"
				/>
			</div>
		</div>
	</section>
</template>
