<script setup lang="ts">
import type { LandingThisWeek, LandingThisWeekRun } from "../../Types/Landing"
import { router, usePage } from "@inertiajs/vue3"
import { computed } from "vue"
import { useI18n } from "vue-i18n"
import { route } from "ziggy-js"
import { createDateTimeFormatter } from "@/utils/dateTimeFormat"
import { localizedValue } from "@/utils/localizedValue"

const props = defineProps<{
	thisWeek: LandingThisWeek
}>()

const { t, locale } = useI18n()
const page = usePage()

const avatarStyles = [
	"from-sky-300 to-blue-600",
	"from-rose-300 to-pink-600",
	"from-amber-200 to-orange-600",
	"from-emerald-300 to-teal-600",
	"from-violet-300 to-purple-700",
	"from-cyan-200 to-indigo-600",
]

const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"))
const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
const dayNumberFormatter = computed(() => createDateTimeFormatter(locale.value, { day: "numeric" }))
const dateRangeFormatter = computed(() => createDateTimeFormatter(locale.value, {
	month: "short",
	day: "numeric",
}))
const timeFormatter = computed(() => createDateTimeFormatter(locale.value, {
	hour: "2-digit",
	minute: "2-digit",
}))
const weekRangeLabel = computed(() => {
	const start = new Date(`${props.thisWeek.start}T00:00:00`)
	const end = new Date(`${props.thisWeek.end}T00:00:00`)
	const year = new Intl.DateTimeFormat(locale.value, { year: "numeric" }).format(end)

	return `${dateRangeFormatter.value.format(start)} - ${dateRangeFormatter.value.format(end)}, ${year}`
})

const dayNumber = (date: string) => dayNumberFormatter.value.format(new Date(`${date}T00:00:00`))

const runTime = (run: LandingThisWeekRun) => (
	run.starts_at
		? timeFormatter.value.format(new Date(run.starts_at))
		: t("groups.activities.cards.no_time")
)

const runTitle = (run: LandingThisWeekRun) => (
	run.title
		|| localizedValue(run.activity_type_name, locale.value, fallbackLocale.value)
		|| t("groups.activities.cards.unknown_type")
)

const difficultyLabel = (difficulty: string | null) => {
	if (!difficulty) {
		return t("landing.this_week.styles.unknown")
	}

	const key = `groups.activities.difficulties.${difficulty}`
	const label = t(key)

	return label === key ? difficulty : label
}

const styleBadgeClass = (difficulty: string | null) => {
	if (difficulty === "ultimate" || difficulty === "chaotic") {
		return "bg-violet-500/25 text-violet-100 ring-violet-300/20"
	}

	if (difficulty === "savage") {
		return "bg-amber-500/20 text-amber-200 ring-amber-300/20"
	}

	return "bg-sky-500/15 text-sky-100 ring-sky-300/20"
}

const applicationBadgeClass = (statusKey: LandingThisWeekRun["application_status_key"]) => (
	statusKey === "open"
		? "bg-emerald-500/15 text-emerald-200 ring-emerald-300/20"
		: "bg-neutral-500/15 text-neutral-300 ring-neutral-300/15"
)

const openRun = (href: string | null) => {
	if (!href) {
		return
	}

	router.get(href)
}

const openMoreRuns = () => {
	router.get(isAuthenticated.value ? route("dashboard.runs.index") : route("login"))
}
</script>

<template>
	<section
		id="this-week"
		class="relative scroll-mt-8 bg-neutral-950 px-6 py-8 lg:px-10"
		:aria-label="t('landing.nav.this_week')"
	>
		<div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-violet-400/30 to-transparent" />

		<div class="w-full p-5 ">
			<div class="mb-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div class="flex flex-wrap items-baseline gap-x-5 gap-y-2">
					<h2 class="text-2xl font-semibold text-white">
						{{ t("landing.this_week.title") }}
					</h2>
					<p class="text-sm font-medium text-neutral-500">
						{{ weekRangeLabel }}
					</p>
				</div>

				<div class="flex flex-wrap items-center gap-2">
					<UButton
						color="neutral"
						variant="outline"
						icon="i-lucide-search"
						:label="t('landing.this_week.more_runs_action')"
						@click="openMoreRuns"
					/>
				</div>
			</div>

			<div class="overflow-x-auto pb-1">
				<div class="grid min-w-[88rem] grid-cols-7 gap-2">
					<article
						v-for="day in thisWeek.days"
						:key="day.date"
						class="min-h-64 border bg-white/[0.035] p-4 shadow-2xl shadow-violet-950/15"
						:class="day.is_today
							? 'border-violet-400/70 bg-violet-500/[0.07] shadow-violet-950/30'
							: 'border-white/10 shadow-neutral-950/30'"
					>
						<div class="mb-4 flex items-center justify-between gap-3">
							<p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400">
								{{ t(`landing.this_week.days.${day.key}`) }} {{ dayNumber(day.date) }}
							</p>
							<UBadge
								v-if="day.is_today"
								color="primary"
								variant="soft"
								:label="t('landing.this_week.today')"
								class="bg-violet-500/25 text-violet-100 ring-violet-300/20"
							/>
						</div>

						<div v-if="day.runs.length > 0" class="flex flex-col gap-4">
							<div
								v-for="run in day.runs"
								:key="run.id"
								class="border-b border-white/5 pb-4 transition-all last:border-b-0 last:pb-0"
								:class="run.href
									? 'cursor-pointer hover:scale-105'
									: 'cursor-default opacity-80'"
								@click="openRun(run.href)"
							>
								<div class="flex items-center gap-2">
									<p class="text-sm font-medium text-neutral-200">
										{{ runTime(run) }}
									</p>
									<UBadge
										color="neutral"
										variant="soft"
										size="xs"
										:label="difficultyLabel(run.difficulty)"
										:class="styleBadgeClass(run.difficulty)"
									/>
									<UBadge
										color="neutral"
										variant="soft"
										size="xs"
										:label="t(`landing.this_week.applications.${run.application_status_key}`)"
										:class="applicationBadgeClass(run.application_status_key)"
									/>
								</div>
								<h3 class="mt-1 line-clamp-2 min-h-12 overflow-hidden text-base font-semibold leading-6 text-neutral-100">
									{{ runTitle(run) }}
								</h3>
								<div class="mt-3 flex items-center justify-between gap-3">
									<p class="text-sm text-neutral-500">
										{{ run.datacenter }}
									</p>
									<div class="flex items-center">
										<div class="flex -space-x-2">
											<div
												v-for="(member, index) in run.assigned_members"
												:key="member.id"
												class="flex h-6 w-6 items-center justify-center rounded-full border border-neutral-950 bg-gradient-to-br text-[0.6rem] font-bold text-white"
												:class="avatarStyles[index % avatarStyles.length]"
												:title="member.name"
											>
												<img
													v-if="member.avatar_url"
													:src="member.avatar_url"
													:alt="member.name"
													class="h-full w-full rounded-full object-cover"
												>
												<span v-else>{{ member.initials }}</span>
											</div>
										</div>
										<span v-if="run.overflow_count > 0" class="ml-2 text-xs font-medium text-neutral-500">
											+{{ run.overflow_count }}
										</span>
									</div>
								</div>
							</div>
						</div>

						<div v-else class="flex min-h-40 items-center justify-center text-center text-sm text-neutral-600">
							{{ t("landing.this_week.empty_day") }}
						</div>

						<p v-if="day.hidden_run_count > 0" class="mt-4 text-center text-xs font-medium text-violet-300/80">
							{{ t("landing.this_week.more_runs", { count: day.hidden_run_count }) }}
						</p>
					</article>
				</div>
			</div>
		</div>
	</section>
</template>
