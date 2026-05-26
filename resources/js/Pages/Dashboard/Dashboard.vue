<script setup lang="ts">
import type { AccountApplication } from "../../Types/ActivityCore"
import type { DashboardPageProps } from "../../Types/Dashboard"
import type { NotificationRecord } from "../../Types/Notifications"
import { computed } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { useI18n } from "vue-i18n"
import { localizedValue } from "@/utils/localizedValue"
import { getActivityStatusMeta } from "@/utils/activityStatusMeta"
import { createDateTimeFormatter, createRelativeTimeFormatter } from "@/utils/dateTimeFormat"
import {
	formatNotificationTime,
	resolveNotificationDescription,
	resolveNotificationMeta,
	resolveNotificationTitle,
} from "@/utils/notificationPresentation"

const props = defineProps<DashboardPageProps>()

const { t, locale } = useI18n()
const page = usePage()

const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"))
const latestNotifications = computed(() => (page.props.notifications?.latest ?? []) as NotificationRecord[])
const leadParticipation = computed(() => props.upcomingParticipations[0] ?? null)
const trailingParticipations = computed(() => props.upcomingParticipations.slice(1, 4))
const recentApplicationsPreview = computed(() => props.recentApplications.slice(0, 4))

const formatProviderLabel = (provider: string) => ({
	discord: "Discord",
	xivauth: "XIVAuth",
}[provider] ?? provider)

const connectedAccountsLabel = computed(() => (
	props.setup.connected_providers.length > 0
		? props.setup.connected_providers.map(formatProviderLabel).join(" · ")
		: t("dashboard.identity.no_connected_accounts")
))

const setupItems = computed(() => [
	{
		key: "primary_character",
		label: t("dashboard.setup.items.primary_character"),
		icon: "i-lucide-star",
		complete: props.setup.has_primary_character,
	},
	{
		key: "verified_characters",
		label: t("dashboard.setup.items.verified_characters"),
		icon: "i-lucide-shield-check",
		complete: props.setup.has_verified_characters,
	},
	{
		key: "xivauth",
		label: t("dashboard.setup.items.xivauth"),
		icon: "i-lucide-key-round",
		complete: props.setup.connected_providers.includes("xivauth"),
	},
	{
		key: "discord",
		label: t("dashboard.setup.items.discord"),
		icon: "i-lucide-message-circle-more",
		complete: props.setup.connected_providers.includes("discord"),
	},
	{
		key: "public_profile",
		label: t("dashboard.setup.items.public_profile"),
		icon: "i-lucide-eye",
		complete: props.setup.public_profile,
	},
	{
		key: "public_characters",
		label: t("dashboard.setup.items.public_characters"),
		icon: "i-lucide-users-round",
		complete: props.setup.public_characters,
	},
])

const setupCompleteCount = computed(() => setupItems.value.filter((item) => item.complete).length)
const setupCompletionPercent = computed(() => Math.round((setupCompleteCount.value / setupItems.value.length) * 100))

const heroStats = computed(() => [
	{
		label: t("dashboard.hero_stats.unread"),
		value: props.summary.unread_notification_count,
		hint: t("dashboard.hero_stats.unread_hint"),
		icon: "i-lucide-bell-ring",
		iconClass: "text-sky-600 dark:text-sky-300",
	},
	{
		label: t("dashboard.hero_stats.active_applications"),
		value: props.summary.active_application_count,
		hint: t("dashboard.hero_stats.active_applications_hint", { count: props.summary.pending_application_count }),
		icon: "i-lucide-file-pen-line",
		iconClass: "text-brand dark:text-brand",
	},
	{
		label: t("dashboard.hero_stats.confirmed_runs"),
		value: props.summary.confirmed_participation_count,
		hint: t("dashboard.hero_stats.confirmed_runs_hint"),
		icon: "i-lucide-calendar-check-2",
		iconClass: "text-emerald-600 dark:text-emerald-300",
	},
	{
		label: t("dashboard.hero_stats.groups"),
		value: props.summary.group_count,
		hint: t("dashboard.hero_stats.groups_hint", { count: props.summary.owned_group_count }),
		icon: "i-lucide-shield",
		iconClass: "text-amber-600 dark:text-amber-300",
	},
])

const groupSections = computed(() => [
	{
		key: "owned",
		label: t("dashboard.groups.sections.owned"),
		count: props.groups.owned.count,
		items: props.groups.owned.items,
		color: "warning" as const,
	},
	{
		key: "moderated",
		label: t("dashboard.groups.sections.moderated"),
		count: props.groups.moderated.count,
		items: props.groups.moderated.items,
		color: "primary" as const,
	},
	{
		key: "member",
		label: t("dashboard.groups.sections.member"),
		count: props.groups.member.count,
		items: props.groups.member.items,
		color: "neutral" as const,
	},
].filter((section) => section.count > 0))

const profileFacts = computed(() => [
	{
		key: "primary_character",
		label: t("dashboard.profile.details.primary_character"),
		icon: "i-lucide-swords",
		value: props.profile.primary_character
			? `${props.profile.primary_character.name} · ${props.profile.primary_character.world || t("dashboard.labels.not_available")}`
			: t("dashboard.profile.none_yet"),
	},
	{
		key: "linked_accounts",
		label: t("dashboard.profile.details.linked_accounts"),
		icon: "i-lucide-link",
		value: connectedAccountsLabel.value,
	},
	{
		key: "public_profile",
		label: t("dashboard.profile.details.public_profile"),
		icon: "i-lucide-eye",
		value: props.setup.public_profile ? t("dashboard.profile.visible") : t("dashboard.profile.hidden"),
	},
	{
		key: "public_characters",
		label: t("dashboard.profile.details.public_characters"),
		icon: "i-lucide-users-round",
		value: props.setup.public_characters ? t("dashboard.profile.visible") : t("dashboard.profile.hidden"),
	},
	{
		key: "group_presence",
		label: t("dashboard.profile.details.group_presence"),
		icon: "i-lucide-shield",
		value: t("dashboard.profile.group_presence_value", {
			owned: props.summary.owned_group_count,
			moderated: props.summary.moderated_group_count,
			member: props.summary.member_group_count,
		}),
	},
])

const activityTypeName = (application: AccountApplication) => (
	localizedValue(application.activity.type_name, locale.value, fallbackLocale.value)
	|| t("groups.activities.cards.unknown_type")
)

const applicationTitle = (application: AccountApplication) => (
	application.activity.title || activityTypeName(application)
)

const applicationStatusMeta = (status: string) => ({
	pending: { color: "warning", label: t("groups.activities.application.confirmation.statuses.pending") },
	approved: { color: "success", label: t("groups.activities.application.confirmation.statuses.approved") },
	on_bench: { color: "info", label: t("groups.activities.application.confirmation.statuses.on_bench") },
	declined: { color: "error", label: t("groups.activities.application.confirmation.statuses.declined") },
	cancelled: { color: "neutral", label: t("groups.activities.application.confirmation.statuses.cancelled") },
	withdrawn: { color: "neutral", label: t("applications.statuses.withdrawn") },
}[status] ?? { color: "neutral", label: status })

const formatDateTime = (value: string | null, fallback: string, options?: Intl.DateTimeFormatOptions) => {
	if (!value) {
		return fallback
	}

	return createDateTimeFormatter(locale.value, {
		weekday: "short",
		day: "numeric",
		month: "short",
		hour: "2-digit",
		minute: "2-digit",
		...options,
	}).format(new Date(value))
}

const formatRelativeTime = (value: string | null, fallback: string) => {
	if (!value) {
		return fallback
	}

	const target = new Date(value).getTime()
	const diffMs = target - Date.now()
	const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
		["day", 1000 * 60 * 60 * 24],
		["hour", 1000 * 60 * 60],
		["minute", 1000 * 60],
	]

	for (const [unit, threshold] of units) {
		if (Math.abs(diffMs) >= threshold) {
			return createRelativeTimeFormatter(locale.value, { numeric: "auto" }).format(
				Math.round(diffMs / threshold),
				unit,
			)
		}
	}

	return t("dashboard.labels.just_now")
}

const formatDuration = (hours: number | null) => {
	if (!hours) {
		return t("groups.activities.management.overview.no_duration")
	}

	return t("groups.activities.management.overview.duration", { count: hours })
}

const canOpenOverview = (application: AccountApplication) => (
	Boolean(application.activity.id && application.group.slug)
)

const openOverview = (application: AccountApplication) => {
	if (!application.activity.id || !application.group.slug) {
		return
	}

	router.get(route("groups.activities.overview", {
		group: application.group.slug,
		activity: application.activity.id,
		secretKey: application.activity.secret_key || undefined,
	}))
}

const openApplication = (application: AccountApplication) => {
	if (!application.activity.id || !application.group.slug) {
		return
	}

	router.get(route("groups.activities.application", {
		group: application.group.slug,
		activity: application.activity.id,
		secretKey: application.activity.secret_key || undefined,
	}))
}

const goToCharacters = () => {
	router.get(route("account.characters"))
}

const goToApplications = () => {
	router.get(route("account.applications"))
}

const goToGroups = () => {
	router.get(route("groups.index"))
}

const goToNotifications = () => {
	router.get(route("account.notifications.index"))
}

const goToSettings = () => {
	router.get(route("settings"))
}
</script>

<template>
	<div class="w-full">

		<section class="relative overflow-hidden border border-default bg-background">
<!--			<div class="h-40 border-b border-default bg-[linear-gradient(135deg,rgba(14,165,233,0.18),rgba(251,191,36,0.16),rgba(16,185,129,0.18))] sm:h-48" />-->
<!--			<div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.26),transparent_34%),linear-gradient(to_bottom,transparent,rgba(255,255,255,0.02))]" />-->

			<div class="relative mt-4 px-5 pb-6 sm:px-7">
				<div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
					<div class="flex flex-col gap-5 sm:flex-row sm:items-end">
						<div class="flex h-28 w-28 items-center justify-center border-4 border-background bg-background shadow-sm">
							<UAvatar
								class="h-24 w-24"
								size="3xl"
								icon="i-lucide-user-round"
								:src="profile.primary_character?.avatar_url || profile.avatar_url || undefined"
								:alt="profile.primary_character?.name || profile.name"
							/>
						</div>

						<div class="min-w-0">

							<h1 class="mt-2 text-3xl font-semibold tracking-tight text-toned sm:text-4xl">
								{{ profile.primary_character?.name || profile.name }}
							</h1>

							<div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
								<div class="flex items-center gap-2">
									<UIcon name="i-lucide-user-round" class="text-base" />
									<span>{{ profile.name }}</span>
								</div>
								<div class="flex items-center gap-2">
									<UIcon name="i-lucide-map-pinned" class="text-base" />
									<span>
										{{ profile.primary_character
											? `${profile.primary_character.world || t("dashboard.labels.not_available")} · ${profile.primary_character.datacenter || t("dashboard.labels.not_available")}`
											: t("dashboard.profile.none_yet") }}
									</span>
								</div>
								<div class="flex items-center gap-2">
									<UIcon name="i-lucide-link" class="text-base" />
									<span>{{ connectedAccountsLabel }}</span>
								</div>
							</div>

							<p class="mt-4 max-w-3xl text-sm leading-6 text-toned">
								{{ profile.primary_character
									? t("dashboard.identity.with_character")
									: t("dashboard.identity.without_character") }}
							</p>
						</div>
					</div>

					<div class="flex flex-wrap gap-2 xl:justify-end">
						<UButton
							color="primary"
							icon="i-lucide-user-circle"
							:label="t('dashboard.actions.characters')"
							@click="goToCharacters"
						/>
						<UButton
							color="neutral"
							variant="outline"
							icon="i-lucide-file-text"
							:label="t('dashboard.actions.applications')"
							@click="goToApplications"
						/>
						<UButton
							color="neutral"
							variant="ghost"
							icon="i-lucide-bell"
							:label="t('dashboard.actions.notifications')"
							@click="goToNotifications"
						/>
					</div>
				</div>

				<div class="mt-6 grid gap-px border border-default bg-muted/25 sm:grid-cols-2 xl:grid-cols-4">
					<div
						v-for="item in heroStats"
						:key="item.label"
						class="bg-background px-4 py-4"
					>
						<div class="flex items-start justify-between gap-3">
							<div class="min-w-0">
								<p class="text-xs uppercase tracking-[0.18em] text-muted">{{ item.label }}</p>
								<p class="mt-3 text-2xl font-semibold text-toned">{{ item.value }}</p>
								<p class="mt-2 text-sm text-muted">{{ item.hint }}</p>
							</div>

							<div class="flex h-10 w-10 shrink-0 items-center justify-center border border-default bg-muted/10">
								<UIcon :name="item.icon" :class="['text-lg', item.iconClass]" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<div class="mt-6 grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
			<aside class="space-y-6">
				<section class="border border-default bg-background">
					<div class="border-b border-default px-5 py-4">
						<p class="font-semibold text-md text-toned">{{ t("dashboard.profile.title") }}</p>
					</div>

					<div class="divide-y divide-default">
						<div
							v-for="fact in profileFacts"
							:key="fact.key"
							class="flex items-start gap-3 px-5 py-4"
						>
							<div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center border border-default bg-muted/10">
								<UIcon :name="fact.icon" class="text-base text-muted" />
							</div>

							<div class="min-w-0">
								<p class="text-xs uppercase tracking-[0.16em] text-muted">{{ fact.label }}</p>
								<p class="mt-1 break-words text-sm font-medium text-toned">{{ fact.value }}</p>
							</div>
						</div>
					</div>
				</section>

				<section class="border border-default bg-background">
					<div class="border-b border-default px-5 py-4">
						<div class="flex items-start justify-between gap-3">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("dashboard.setup.title") }}</p>
								<p class="mt-1 text-sm text-muted">{{ t("dashboard.setup.subtitle") }}</p>
							</div>

							<UBadge
								:label="t('dashboard.identity.setup_score', {
									configured: setupCompleteCount,
									total: setupItems.length,
								})"
								color="primary"
								variant="subtle"
							/>
						</div>
					</div>

					<div class="px-5 py-5">
						<div class="space-y-2">
							<div class="flex items-center justify-between text-sm">
								<span class="text-muted">{{ t("dashboard.setup.coverage") }}</span>
								<span class="font-medium text-toned">{{ setupCompletionPercent }}%</span>
							</div>
							<UProgress :model-value="setupCompletionPercent" color="primary" />
						</div>

						<div class="mt-5 space-y-3">
							<div
								v-for="item in setupItems"
								:key="item.key"
								class="flex items-center justify-between gap-3 border border-default bg-muted/10 px-4 py-3"
							>
								<div class="flex items-center gap-3">
									<UIcon :name="item.icon" class="text-base text-muted" />
									<p class="text-sm font-medium text-toned">{{ item.label }}</p>
								</div>

								<UBadge
									:label="item.complete ? t('dashboard.setup.complete') : t('dashboard.setup.incomplete')"
									:color="item.complete ? 'success' : 'warning'"
									variant="subtle"
								/>
							</div>
						</div>

						<UButton
							class="mt-5"
							color="neutral"
							variant="outline"
							icon="i-lucide-settings-2"
							:label="t('dashboard.actions.settings')"
							@click="goToSettings"
						/>
					</div>
				</section>

				<section class="border border-default bg-background">
					<div class="border-b border-default px-5 py-4">
						<div class="flex items-start justify-between gap-3">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("dashboard.groups.title") }}</p>
								<p class="mt-1 text-sm text-muted">{{ t("dashboard.groups.subtitle") }}</p>
							</div>

							<UButton
								color="neutral"
								variant="ghost"
								icon="i-lucide-arrow-right"
								:label="t('dashboard.actions.groups')"
								@click="goToGroups"
							/>
						</div>
					</div>

					<div v-if="groupSections.length > 0" class="space-y-4 px-5 py-5">
						<div
							v-for="section in groupSections"
							:key="section.key"
							class="border border-default bg-muted/10"
						>
							<div class="flex items-center justify-between gap-3 border-b border-default px-4 py-3">
								<p class="font-medium text-toned">{{ section.label }}</p>
								<UBadge
									:label="t('dashboard.groups.count', { count: section.count })"
									:color="section.color"
									variant="subtle"
								/>
							</div>

							<div class="flex flex-col">
								<Link
									v-for="group in section.items"
									:key="group.id"
									:href="group.href"
									class="inline-flex items-center justify-between gap-3 border-t border-default px-4 py-3 text-sm text-toned transition first:border-t-0 hover:bg-background"
								>
									<span class="truncate">{{ group.name }}</span>
									<UIcon name="i-lucide-arrow-up-right" class="h-4 w-4 shrink-0 text-muted" />
								</Link>
							</div>
						</div>
					</div>

					<div v-else class="px-5 py-10 text-center">
						<p class="font-medium text-toned">{{ t("dashboard.groups.empty_title") }}</p>
						<p class="mt-2 text-sm text-muted">{{ t("dashboard.groups.empty_description") }}</p>
					</div>
				</section>
			</aside>

			<div class="space-y-6">
				<div class="grid gap-6 2xl:grid-cols-[1.05fr_0.95fr]">
					<section class="border border-default bg-background">
						<div class="flex items-start justify-between gap-4 border-b border-default px-5 py-4">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("dashboard.next_up.title") }}</p>
							</div>

							<UButton
								color="neutral"
								variant="ghost"
								icon="i-lucide-arrow-right"
								:label="t('dashboard.actions.applications')"
								@click="goToApplications"
							/>
						</div>

						<div v-if="leadParticipation" class="px-5 py-5">
							<div class="border border-default bg-muted/10 px-5 py-5">
								<div class="flex flex-wrap items-center gap-2">
									<UBadge
										:color="applicationStatusMeta(leadParticipation.status).color"
										variant="soft"
										:label="applicationStatusMeta(leadParticipation.status).label"
									/>
									<UBadge
										v-if="leadParticipation.activity.status"
										:color="getActivityStatusMeta(leadParticipation.activity.status).color"
										variant="subtle"
										:icon="getActivityStatusMeta(leadParticipation.activity.status).icon"
										:label="t(`groups.activities.statuses.${leadParticipation.activity.status}`)"
									/>
								</div>

								<p class="mt-4 text-xs font-medium uppercase tracking-[0.18em] text-muted">
									{{ t("dashboard.next_up.queue_label") }}
								</p>
								<h2 class="mt-2 text-2xl font-semibold tracking-tight text-toned">
									{{ applicationTitle(leadParticipation) }}
								</h2>
								<p class="mt-3 text-sm leading-6 text-muted">
									{{ leadParticipation.activity.description || t("dashboard.next_up.no_description") }}
								</p>

								<div class="mt-5 grid gap-3 sm:grid-cols-2">
									<div class="border border-default bg-background px-4 py-3">
										<p class="text-xs uppercase tracking-[0.16em] text-muted">{{ t("groups.activities.management.organizer") }}</p>
										<p class="mt-2 text-sm font-medium text-toned">
											{{ leadParticipation.group.name || t("applications.unknown_group") }}
										</p>
									</div>
									<div class="border border-default bg-background px-4 py-3">
										<p class="text-xs uppercase tracking-[0.16em] text-muted">{{ t("groups.activities.create.summary.starts_at_st") }}</p>
										<p class="mt-2 text-sm font-medium text-toned">
											{{ formatDateTime(leadParticipation.activity.starts_at, t("groups.activities.cards.no_time")) }}
										</p>
									</div>
									<div class="border border-default bg-background px-4 py-3">
										<p class="text-xs uppercase tracking-[0.16em] text-muted">{{ t("groups.activities.management.character") }}</p>
										<p class="mt-2 text-sm font-medium text-toned">
											{{ leadParticipation.character.name || t("applications.unknown_character") }}
										</p>
									</div>
									<div class="border border-default bg-background px-4 py-3">
										<p class="text-xs uppercase tracking-[0.16em] text-muted">{{ t("groups.activities.create.summary.duration") }}</p>
										<p class="mt-2 text-sm font-medium text-toned">
											{{ formatDuration(leadParticipation.activity.duration_hours) }}
										</p>
									</div>
								</div>

								<div class="mt-5 flex flex-wrap items-center justify-between gap-3">
									<p class="text-sm text-muted">
										{{ t("dashboard.next_up.submitted", {
											time: formatRelativeTime(leadParticipation.submitted_at, t("dashboard.labels.not_available")),
										}) }}
									</p>

									<div class="flex flex-wrap gap-2">
										<UButton
											v-if="canOpenOverview(leadParticipation)"
											color="primary"
											icon="i-lucide-arrow-up-right"
											:label="t('applications.view_run')"
											@click="openOverview(leadParticipation)"
										/>
										<UButton
											v-if="leadParticipation.can_edit"
											color="neutral"
											variant="outline"
											icon="i-lucide-pencil-line"
											:label="t('applications.edit')"
											@click="openApplication(leadParticipation)"
										/>
									</div>
								</div>
							</div>

							<div v-if="trailingParticipations.length > 0" class="mt-5">
								<p class="text-xs font-medium uppercase tracking-[0.18em] text-muted">
									{{ t("dashboard.next_up.more_title") }}
								</p>

								<div class="mt-3 divide-y divide-default border border-default">
									<div
										v-for="application in trailingParticipations"
										:key="application.id"
										class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
									>
										<div class="min-w-0">
											<div class="flex flex-wrap items-center gap-2">
												<p class="truncate font-medium text-toned">{{ applicationTitle(application) }}</p>
												<UBadge
													:color="applicationStatusMeta(application.status).color"
													variant="soft"
													:label="applicationStatusMeta(application.status).label"
												/>
											</div>
											<p class="mt-2 text-sm text-muted">
												{{ formatDateTime(application.activity.starts_at, t("groups.activities.cards.no_time")) }}
											</p>
										</div>

										<UButton
											v-if="canOpenOverview(application)"
											color="neutral"
											variant="ghost"
											icon="i-lucide-arrow-up-right"
											:label="t('applications.view_run')"
											@click="openOverview(application)"
										/>
									</div>
								</div>
							</div>
						</div>

						<div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
							<div class="flex h-14 w-14 items-center justify-center border border-default bg-muted/15">
								<UIcon name="i-lucide-calendar-check-2" class="text-2xl text-muted" />
							</div>
							<p class="font-semibold text-toned">{{ t("dashboard.next_up.empty_title") }}</p>
							<p class="max-w-lg text-sm text-muted">{{ t("dashboard.next_up.empty_description") }}</p>
						</div>
					</section>

					<section class="border border-default bg-background">
						<div class="flex items-start justify-between gap-4 border-b border-default px-5 py-4">
							<div>
								<p class="font-semibold text-md text-toned">{{ t("dashboard.alerts.title") }}</p>
							</div>

							<UButton
								color="neutral"
								variant="ghost"
								icon="i-lucide-arrow-right"
								:label="t('dashboard.actions.notifications')"
								@click="goToNotifications"
							/>
						</div>

						<div v-if="latestNotifications.length > 0" class="divide-y divide-default">
							<Link
								v-for="notification in latestNotifications"
								:key="notification.id"
								:href="notification.open_url"
								class="block px-5 py-4 transition hover:bg-muted/10"
								:class="{ 'bg-sky-50/40 dark:bg-sky-950/15': notification.is_unread }"
							>
								<div class="flex items-start gap-4">
									<div class="flex h-11 w-11 shrink-0 items-center justify-center border border-default bg-background">
										<UIcon
											:name="resolveNotificationMeta(notification).icon"
											:class="[resolveNotificationMeta(notification).iconColor, 'text-lg']"
										/>
									</div>

									<div class="min-w-0 flex-1">
										<div class="flex flex-wrap items-center gap-2">
											<UBadge
												v-if="notification.is_unread"
												color="primary"
												variant="soft"
												:label="t('notifications.ui.new_badge')"
											/>
											<UBadge
												v-if="notification.is_mandatory"
												color="warning"
												variant="soft"
												:label="t('notifications.ui.important_badge')"
											/>
										</div>

										<p class="mt-2 font-medium text-toned">
											{{ resolveNotificationTitle(notification, t) }}
										</p>
										<p
											v-if="resolveNotificationDescription(notification, t)"
											class="mt-1 text-sm text-muted"
										>
											{{ resolveNotificationDescription(notification, t) }}
										</p>
									</div>

									<p class="shrink-0 text-sm text-muted">
										{{ formatNotificationTime(notification.created_at, locale, t) }}
									</p>
								</div>
							</Link>
						</div>

						<div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
							<div class="flex h-14 w-14 items-center justify-center border border-default bg-muted/15">
								<UIcon name="i-lucide-bell" class="text-2xl text-muted" />
							</div>
							<p class="font-semibold text-toned">{{ t("dashboard.alerts.empty_title") }}</p>
							<p class="max-w-lg text-sm text-muted">{{ t("dashboard.alerts.empty_description") }}</p>
						</div>
					</section>
				</div>

				<section class="border border-default bg-background">
					<div class="flex items-start justify-between gap-4 border-b border-default px-5 py-4">
						<div>
							<p class="font-semibold text-md text-toned">{{ t("dashboard.activity.title") }}</p>
							<p class="mt-1 text-sm text-muted">{{ t("dashboard.activity.subtitle") }}</p>
						</div>

						<UButton
							color="neutral"
							variant="ghost"
							icon="i-lucide-arrow-right"
							:label="t('dashboard.actions.applications')"
							@click="goToApplications"
						/>
					</div>

					<div v-if="recentApplicationsPreview.length > 0" class="divide-y divide-default">
						<div
							v-for="application in recentApplicationsPreview"
							:key="application.id"
							class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_230px]"
						>
							<div class="min-w-0">
								<div class="flex flex-wrap items-center gap-2">
									<p class="truncate font-semibold text-toned">{{ applicationTitle(application) }}</p>
									<UBadge
										:color="applicationStatusMeta(application.status).color"
										variant="soft"
										:label="applicationStatusMeta(application.status).label"
									/>
									<UBadge
										v-if="application.activity.status"
										:color="getActivityStatusMeta(application.activity.status).color"
										variant="subtle"
										:icon="getActivityStatusMeta(application.activity.status).icon"
										:label="t(`groups.activities.statuses.${application.activity.status}`)"
									/>
								</div>

								<div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted">
									<div class="flex items-center gap-2">
										<UIcon name="i-lucide-shield" class="text-base" />
										<span>{{ application.group.name || t("applications.unknown_group") }}</span>
									</div>
									<div class="flex items-center gap-2">
										<UIcon name="i-lucide-user-round" class="text-base" />
										<span>{{ application.character.name || t("applications.unknown_character") }}</span>
									</div>
									<div class="flex items-center gap-2">
										<UIcon name="i-lucide-clock-3" class="text-base" />
										<span>{{ t("dashboard.activity.submitted", {
											time: formatRelativeTime(application.submitted_at, t("dashboard.labels.not_available")),
										}) }}</span>
									</div>
								</div>
							</div>

							<div class="flex flex-col items-start justify-between gap-3 lg:items-end">
								<div class="text-sm text-muted lg:text-right">
									<p class="font-medium text-toned">
										{{ formatDateTime(application.activity.starts_at, t("groups.activities.cards.no_time")) }}
									</p>
									<p class="mt-1">
										{{ formatDuration(application.activity.duration_hours) }}
									</p>
								</div>

								<div class="flex flex-wrap gap-2 lg:justify-end">
									<UButton
										v-if="canOpenOverview(application)"
										color="neutral"
										variant="ghost"
										icon="i-lucide-arrow-up-right"
										:label="t('applications.view_run')"
										@click="openOverview(application)"
									/>
									<UButton
										v-if="application.can_edit"
										color="neutral"
										variant="outline"
										icon="i-lucide-pencil-line"
										:label="t('applications.edit')"
										@click="openApplication(application)"
									/>
								</div>
							</div>
						</div>
					</div>

					<div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
						<div class="flex h-14 w-14 items-center justify-center border border-default bg-muted/15">
							<UIcon name="i-lucide-file-text" class="text-2xl text-muted" />
						</div>
						<p class="font-semibold text-toned">{{ t("dashboard.activity.empty_title") }}</p>
						<p class="max-w-lg text-sm text-muted">{{ t("dashboard.activity.empty_description") }}</p>
					</div>
				</section>
			</div>
		</div>
	</div>
</template>
