<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { computed } from "vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n()
const page = usePage()

const logoUrl = "/logo_white.png"
const year = new Date().getFullYear()
const discordUrl = computed(() => {
	const siteLinks = page.props.site_links as { discord?: string | null } | undefined

	return siteLinks?.discord ?? null
})
</script>

<template>
	<footer class="relative bg-neutral-950 px-6 py-10 text-neutral-400 lg:px-10">
		<div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-violet-400/30 to-transparent" />

		<div class="mx-auto flex max-w-7xl flex-col gap-8 md:flex-row md:items-end md:justify-between">
			<div class="flex flex-col items-start gap-4">
				<img
					:src="logoUrl"
					alt="FullParty"
					class="h-12 w-auto"
				>
				<p class="text-sm">
					&copy; {{ year }} {{ t("landing.footer.copyright") }}
				</p>
			</div>

			<nav class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm font-medium">
				<Link :href="route('legal.privacy')" class="transition hover:text-white">
					{{ t("landing.footer.privacy") }}
				</Link>
				<Link :href="route('legal.cookies')" class="transition hover:text-white">
					{{ t("landing.footer.cookies") }}
				</Link>
				<a
					:href="discordUrl ?? '#'"
					target="_blank"
					rel="noopener noreferrer"
					class="inline-flex items-center gap-2 transition hover:text-white"
					:class="{ 'pointer-events-none opacity-60': !discordUrl }"
				>
					<UIcon name="ic:baseline-discord" class="size-4" />
					<span>{{ t("landing.footer.discord") }}</span>
				</a>
			</nav>
		</div>
	</footer>
</template>
