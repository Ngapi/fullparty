<script setup lang="ts">
import axios from "axios"
import { router, usePage } from "@inertiajs/vue3"
import { computed, onBeforeUnmount, ref, watch } from "vue"
import { useI18n } from "vue-i18n"
import { route } from "ziggy-js"

type OnboardingStepKey = "welcome" | "discord" | "discord_warning" | "notifications" | "next"

type OnboardingState = {
	required: boolean
	current_step: string
	discord_skipped_at: string | null
	notification_preferences_completed_at: string | null
	completed_at: string | null
}

type AuthDiscordUserIntegration = {
	id: number
	discord_user_id: string
	username: string | null
	global_name: string | null
	avatar_url: string | null
	user_app_installed_at: string | null
}

type AuthNotificationUser = {
	application_notifications: boolean
	run_and_reminder_notifications: boolean
	group_update_notifications: boolean
	assignment_notifications: boolean
	account_character_notifications: boolean
	system_notice_notifications: boolean
	email_notifications: boolean
	discord_notifications: boolean
	notification_preferences: NotificationPreferencePayload
	notification_preferences_reviewed_at: string | null
	discord_user_integration: AuthDiscordUserIntegration | null
}

type NotificationChannelKey = "in_app" | "email" | "discord"
type ExternalNotificationChannelKey = "email" | "discord"
type NotificationPreferencePayload = Record<string, Partial<Record<NotificationChannelKey, boolean>>>

type NotificationPreferences = {
	application_notifications: boolean
	run_and_reminder_notifications: boolean
	group_update_notifications: boolean
	assignment_notifications: boolean
	account_character_notifications: boolean
	system_notice_notifications: boolean
	email_notifications: boolean
	discord_notifications: boolean
}

type NotificationSettingsResponse = Partial<NotificationPreferences> & {
	notification_preferences?: NotificationPreferencePayload
}

type QuickLink = {
	label: string
	description: string
	icon: string
	to: string
}

const externalNotificationTopics = [
	{ key: "applications.submitted", email: true, discord: true },
	{ key: "applications.host_updates", email: true, discord: true },
	{ key: "applications.outcomes", email: true, discord: true },
	{ key: "assignments.roster", email: true, discord: true },
	{ key: "assignments.bench", email: true, discord: true },
	{ key: "assignments.status", email: true, discord: true },
	{ key: "assignments.designations", email: true, discord: true },
	{ key: "runs.reminders", email: true, discord: true },
	{ key: "runs.lifecycle", email: true, discord: true },
	{ key: "account.connected_accounts", email: true, discord: true },
	{ key: "characters.changes", email: true, discord: true },
	{ key: "system.maintenance", email: true, discord: true },
	{ key: "system.announcements", email: true, discord: true },
] as const

const { t } = useI18n()
const page = usePage()

const isOpen = ref(false)
const currentStepKey = ref<OnboardingStepKey>("welcome")
const notificationSaveState = ref<"idle" | "saving" | "saved" | "error">("idle")
const notificationRequestId = ref(0)
const onboardingSaveState = ref<"idle" | "saving" | "error">("idle")
const localOnboardingState = ref<OnboardingState | null>(null)
const notificationPreferenceChannels = ref<NotificationPreferencePayload>({})
let notificationSaveStateTimer: number | null = null
const forceOpenForTesting = false

const user = computed(() => page.props.auth?.user as AuthNotificationUser | null)
const sharedOnboardingState = computed(() => page.props.onboarding as OnboardingState | null)
const hasDiscordIntegration = computed(() => user.value?.discord_user_integration !== null)

const notificationPreferences = ref<NotificationPreferences>({
	application_notifications: true,
	run_and_reminder_notifications: true,
	group_update_notifications: true,
	assignment_notifications: true,
	account_character_notifications: true,
	system_notice_notifications: true,
	email_notifications: true,
	discord_notifications: false,
})

const stepMeta = computed(() => ({
	welcome: {
		icon: "i-lucide-sparkles",
		title: t("dashboard.onboarding.steps.welcome.title"),
		description: t("dashboard.onboarding.steps.welcome.description"),
	},
	discord: {
		icon: hasDiscordIntegration.value ? "i-lucide-circle-check" : "ic:baseline-discord",
		title: t("dashboard.onboarding.steps.discord.title"),
		description: hasDiscordIntegration.value
			? t("dashboard.onboarding.steps.discord.connected_description")
			: t("dashboard.onboarding.steps.discord.description"),
	},
	discord_warning: {
		icon: "i-lucide-triangle-alert",
		title: t("dashboard.onboarding.steps.discord_warning.title"),
		description: t("dashboard.onboarding.steps.discord_warning.description"),
	},
	notifications: {
		icon: "i-lucide-bell-ring",
		title: t("dashboard.onboarding.steps.notifications.title"),
		description: t("dashboard.onboarding.steps.notifications.description"),
	},
	next: {
		icon: "i-lucide-compass",
		title: t("dashboard.onboarding.steps.next.title"),
		description: t("dashboard.onboarding.steps.next.description"),
	},
}))

const currentStep = computed(() => stepMeta.value[currentStepKey.value])
const isWelcomeStep = computed(() => currentStepKey.value === "welcome")
const canGoBack = computed(() => currentStepKey.value !== "welcome")
const isOnboardingSaving = computed(() => onboardingSaveState.value === "saving")
const stepNumber = computed(() => {
	return {
		welcome: 1,
		discord: 2,
		discord_warning: 2,
		notifications: 3,
		next: 4,
	}[currentStepKey.value]
})
const totalSteps = 4
const progress = computed(() => (stepNumber.value / totalSteps) * 100)
const notificationStatusLabel = computed(() => {
	if (notificationSaveState.value === "saving") {
		return t("dashboard.onboarding.notifications.saving")
	}

	if (notificationSaveState.value === "saved") {
		return t("dashboard.onboarding.notifications.saved")
	}

	if (notificationSaveState.value === "error") {
		return t("dashboard.onboarding.notifications.error")
	}

	return t("dashboard.onboarding.notifications.saved_hint")
})

const quickLinks = computed<QuickLink[]>(() => [
	{
		label: t("dashboard.onboarding.actions.add_character"),
		description: t("dashboard.onboarding.quick_links.character"),
		icon: "i-lucide-user-round-plus",
		to: route("account.characters"),
	},
	{
		label: t("dashboard.onboarding.actions.runs"),
		description: t("dashboard.onboarding.quick_links.runs"),
		icon: "i-lucide-calendar-search",
		to: route("dashboard.runs.index"),
	},
	{
		label: t("dashboard.onboarding.actions.groups"),
		description: t("dashboard.onboarding.quick_links.groups"),
		icon: "i-lucide-users-round",
		to: route("groups.index"),
	},
])

const clearNotificationSaveStateTimer = () => {
	if (notificationSaveStateTimer === null) {
		return
	}

	window.clearTimeout(notificationSaveStateTimer)
	notificationSaveStateTimer = null
}

const channelHasEnabledPreference = (channel: ExternalNotificationChannelKey) => externalNotificationTopics.some((topic) => (
	topic[channel] && notificationPreferenceChannels.value[topic.key]?.[channel] === true
))

const syncNotificationPreferences = () => {
	if (!user.value) {
		return
	}

	notificationPreferenceChannels.value = user.value.notification_preferences ?? {}

	notificationPreferences.value = {
		application_notifications: user.value.application_notifications,
		run_and_reminder_notifications: user.value.run_and_reminder_notifications,
		group_update_notifications: user.value.group_update_notifications,
		assignment_notifications: user.value.assignment_notifications,
		account_character_notifications: user.value.account_character_notifications,
		system_notice_notifications: user.value.system_notice_notifications,
		email_notifications: channelHasEnabledPreference("email"),
		discord_notifications: hasDiscordIntegration.value && channelHasEnabledPreference("discord"),
	}
}

const mergeChannelPreferencePayload = (
	payload: NotificationPreferencePayload,
	channel: ExternalNotificationChannelKey,
	enabled: boolean,
) => {
	externalNotificationTopics.forEach((topic) => {
		if (!topic[channel]) {
			return
		}

		payload[topic.key] = {
			...payload[topic.key],
			[channel]: enabled,
		}
	})
}

const buildNotificationPreferencesPayload = (
	changes: Partial<NotificationPreferences>,
) => {
	const payload: NotificationPreferencePayload = {}

	if ("email_notifications" in changes) {
		mergeChannelPreferencePayload(payload, "email", Boolean(changes.email_notifications))
	}

	if ("discord_notifications" in changes && hasDiscordIntegration.value) {
		mergeChannelPreferencePayload(payload, "discord", Boolean(changes.discord_notifications))
	}

	return payload
}

const close = () => {
	isOpen.value = false
}

const normalizeStep = (step: string | null | undefined): OnboardingStepKey => {
	if (step === "discord-warning") {
		return "discord_warning"
	}

	if (["welcome", "discord", "discord_warning", "notifications", "next"].includes(step ?? "")) {
		return step as OnboardingStepKey
	}

	return "welcome"
}

const applyOnboardingState = (state: OnboardingState | null) => {
	if (forceOpenForTesting) {
		currentStepKey.value = normalizeStep(state?.current_step ?? "welcome")
		isOpen.value = true
		return
	}

	if (!state?.required) {
		isOpen.value = false
		return
	}

	currentStepKey.value = normalizeStep(state.current_step)
	isOpen.value = true
}

const persistOnboardingStep = async (
	step: OnboardingStepKey,
	options: { notification_preferences_reviewed?: boolean } = {},
) => {
	onboardingSaveState.value = "saving"

	try {
		const response = await axios.patch(route("onboarding.update"), {
			current_step: step,
			...options,
		}, {
			headers: {
				Accept: "application/json",
			},
		})

		localOnboardingState.value = response.data?.onboarding ?? localOnboardingState.value
		onboardingSaveState.value = "idle"

		return true
	} catch {
		onboardingSaveState.value = "error"

		return false
	}
}

const goToStep = async (
	step: OnboardingStepKey,
	options: { notification_preferences_reviewed?: boolean } = {},
) => {
	const previousStep = currentStepKey.value
	currentStepKey.value = step

	const saved = await persistOnboardingStep(step, options)

	if (!saved) {
		currentStepKey.value = previousStep
	}

	return saved
}

const goBack = () => {
	void goToStep({
		welcome: "welcome",
		discord: "welcome",
		discord_warning: "discord",
		notifications: "discord",
		next: "notifications",
	}[currentStepKey.value] as OnboardingStepKey)
}

const nextStep = () => {
	const next = {
		welcome: "discord",
		discord: "notifications",
		discord_warning: "notifications",
		notifications: "next",
		next: "next",
	}[currentStepKey.value] as OnboardingStepKey

	void goToStep(next, {
		notification_preferences_reviewed: currentStepKey.value === "notifications",
	})
}

const connectDiscord = async () => {
	const saved = await persistOnboardingStep("discord")

	if (!saved) {
		return
	}

	window.location.href = route("discord-app.user.redirect")
}

const skipDiscord = () => {
	void goToStep("discord_warning")
}

const completeOnboarding = async () => {
	onboardingSaveState.value = "saving"

	try {
		const response = await axios.post(route("onboarding.complete"), {}, {
			headers: {
				Accept: "application/json",
			},
		})

		localOnboardingState.value = response.data?.onboarding ?? localOnboardingState.value
		onboardingSaveState.value = "idle"
		close()

		return true
	} catch {
		onboardingSaveState.value = "error"

		return false
	}
}

const openQuickLink = async (link: QuickLink) => {
	const completed = await completeOnboarding()

	if (!completed) {
		return
	}

	router.get(link.to)
}

const saveNotificationPreferences = async (changes: Partial<NotificationPreferences>) => {
	notificationPreferences.value = {
		...notificationPreferences.value,
		...changes,
	}

	if (!hasDiscordIntegration.value) {
		notificationPreferences.value.discord_notifications = false
	}

	const requestId = notificationRequestId.value + 1
	notificationRequestId.value = requestId
	notificationSaveState.value = "saving"
	clearNotificationSaveStateTimer()

	try {
		const notificationPreferencesPayload = buildNotificationPreferencesPayload(changes)
		const response = await axios.post(route("settings.notifications"), {
			...notificationPreferences.value,
			notification_preferences: notificationPreferencesPayload,
		}, {
			headers: {
				Accept: "application/json",
			},
		})

		if (notificationRequestId.value !== requestId) {
			return
		}

		const notifications = response.data?.notifications as NotificationSettingsResponse | undefined

		if (notifications) {
			notificationPreferenceChannels.value = notifications.notification_preferences ?? notificationPreferenceChannels.value

			notificationPreferences.value = {
				application_notifications: Boolean(notifications.application_notifications),
				run_and_reminder_notifications: Boolean(notifications.run_and_reminder_notifications),
				group_update_notifications: Boolean(notifications.group_update_notifications),
				assignment_notifications: Boolean(notifications.assignment_notifications),
				account_character_notifications: Boolean(notifications.account_character_notifications),
				system_notice_notifications: Boolean(notifications.system_notice_notifications),
				email_notifications: channelHasEnabledPreference("email"),
				discord_notifications: hasDiscordIntegration.value && channelHasEnabledPreference("discord"),
			}
		}
		notificationSaveState.value = "saved"
		notificationSaveStateTimer = window.setTimeout(() => {
			notificationSaveState.value = "idle"
		}, 2200)
	} catch {
		if (notificationRequestId.value !== requestId) {
			return
		}

		notificationSaveState.value = "error"
	}
}

watch(
	user,
	syncNotificationPreferences,
	{ immediate: true },
)

watch(
	sharedOnboardingState,
	(state) => {
		localOnboardingState.value = state
		applyOnboardingState(state)
	},
	{ immediate: true },
)

onBeforeUnmount(() => {
	clearNotificationSaveStateTimer()
})
</script>

<template>
	<UModal
		v-model:open="isOpen"
		:close="false"
		:dismissible="false"
		:title="t('dashboard.onboarding.title')"
		:description="t('dashboard.onboarding.step_count', { current: stepNumber, total: totalSteps })"
		:ui="{
			content: 'max-w-[calc(100vw-1rem)] rounded-sm sm:max-w-xl',
			header: 'border-0',
			body: 'max-h-[calc(100vh-12rem)] overflow-y-auto',
		}"
	>
		<template #header>
			<div class="w-full space-y-3">
				<div>
					<h2 class="text-base font-semibold text-white">
						{{ t('dashboard.onboarding.title') }}
					</h2>
					<p class="mt-1 text-sm text-neutral-400">
						{{ t('dashboard.onboarding.step_count', { current: stepNumber, total: totalSteps }) }}
					</p>
				</div>
				<UProgress color="primary" :model-value="progress" />
			</div>
		</template>

		<template #body>
			<div class="space-y-6">
				<div class="flex flex-col gap-4 sm:flex-row sm:items-start">
					<div
						class="flex shrink-0 items-center justify-center border border-violet-300/25 bg-violet-500/15 text-violet-100 shadow-lg shadow-violet-950/25 transition-all"
						:class="isWelcomeStep ? 'h-16 w-16' : 'h-14 w-14'"
					>
						<UIcon :name="currentStep.icon" :class="isWelcomeStep ? 'h-8 w-8' : 'h-7 w-7'" />
					</div>

					<div class="min-w-0">
						<p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-200">
							{{ t('dashboard.onboarding.eyebrow') }}
						</p>
						<h2 class="mt-2 text-2xl font-semibold leading-tight text-white">
							{{ currentStep.title }}
						</h2>
						<p class="mt-3 text-sm leading-6 text-neutral-300 sm:text-base">
							{{ currentStep.description }}
						</p>
					</div>
				</div>

				<div v-if="currentStepKey === 'discord'" class="space-y-3">
					<div
						class="border px-4 py-3"
						:class="hasDiscordIntegration ? 'border-emerald-300/25 bg-emerald-500/10' : 'border-white/10 bg-white/[0.03]'"
					>
						<div class="flex items-start gap-3">
							<UIcon
								:name="hasDiscordIntegration ? 'i-lucide-circle-check' : 'ic:baseline-discord'"
								class="mt-0.5 h-5 w-5 shrink-0"
								:class="hasDiscordIntegration ? 'text-emerald-300' : 'text-violet-200'"
							/>
							<div class="min-w-0">
								<p class="font-semibold text-white">
									{{ hasDiscordIntegration ? t('dashboard.onboarding.discord.connected') : t('dashboard.onboarding.discord.not_connected') }}
								</p>
								<p class="mt-1 text-sm leading-6 text-neutral-400">
									{{ t('dashboard.onboarding.discord.hint') }}
								</p>
							</div>
						</div>
					</div>
				</div>

				<div v-if="currentStepKey === 'discord_warning'" class="border border-amber-300/25 bg-amber-500/10 px-4 py-3">
					<div class="flex items-start gap-3">
						<UIcon name="i-lucide-triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-200" />
						<div class="min-w-0">
							<p class="text-sm leading-6 text-amber-50/90">
								{{ t('dashboard.onboarding.discord.warning_detail') }}
							</p>
							<ul class="mt-3 space-y-2 text-sm text-amber-50/90">
								<li class="flex gap-2">
									<UIcon name="i-lucide-bell-off" class="mt-0.5 h-4 w-4 shrink-0 text-amber-200" />
									<span>{{ t('dashboard.onboarding.discord.missing_features.notifications') }}</span>
								</li>
								<li class="flex gap-2">
									<UIcon name="i-lucide-shield-user" class="mt-0.5 h-4 w-4 shrink-0 text-amber-200" />
									<span>{{ t('dashboard.onboarding.discord.missing_features.group_role_assignment') }}</span>
								</li>
								<li class="flex gap-2">
									<UIcon name="i-lucide-badge-check" class="mt-0.5 h-4 w-4 shrink-0 text-amber-200" />
									<span>{{ t('dashboard.onboarding.discord.missing_features.nickname_sync') }}</span>
								</li>
							</ul>
						</div>
					</div>
				</div>

				<div v-if="currentStepKey === 'notifications'" class="space-y-3">
					<div class="grid w-full grid-cols-[minmax(0,1fr)_auto] items-start gap-4 border border-white/10 bg-white/[0.03] px-4 py-3">
						<div class="min-w-0">
							<p class="font-semibold text-white">
								{{ t('dashboard.onboarding.notifications.email_title') }}
							</p>
							<p class="mt-1 text-sm leading-6 text-neutral-400">
								{{ t('dashboard.onboarding.notifications.email_description') }}
							</p>
						</div>
						<USwitch
							:model-value="notificationPreferences.email_notifications"
							:disabled="notificationSaveState === 'saving'"
							@update:model-value="(value) => saveNotificationPreferences({ email_notifications: Boolean(value) })"
						/>
					</div>

					<div
						class="grid w-full grid-cols-[minmax(0,1fr)_auto] items-start gap-4 border px-4 py-3"
						:class="hasDiscordIntegration ? 'border-white/10 bg-white/[0.03]' : 'border-white/10 bg-white/[0.02] text-neutral-500'"
					>
						<div class="min-w-0">
							<p class="font-semibold" :class="hasDiscordIntegration ? 'text-white' : 'text-neutral-500'">
								{{ t('dashboard.onboarding.notifications.discord_title') }}
							</p>
							<p class="mt-1 text-sm leading-6" :class="hasDiscordIntegration ? 'text-neutral-400' : 'text-neutral-500'">
								{{ hasDiscordIntegration
									? t('dashboard.onboarding.notifications.discord_description')
									: t('dashboard.onboarding.notifications.discord_disabled')
								}}
							</p>
						</div>
						<USwitch
							:model-value="notificationPreferences.discord_notifications"
							:disabled="!hasDiscordIntegration || notificationSaveState === 'saving'"
							@update:model-value="(value) => saveNotificationPreferences({ discord_notifications: Boolean(value) })"
						/>
					</div>

					<p
						class="text-sm"
						:class="{
							'text-neutral-400': notificationSaveState === 'idle',
							'text-violet-200': notificationSaveState === 'saving',
							'text-emerald-300': notificationSaveState === 'saved',
							'text-red-300': notificationSaveState === 'error',
						}"
					>
						{{ notificationStatusLabel }}
					</p>
				</div>

				<div v-if="currentStepKey === 'next'" class="grid gap-3">
					<button
						v-for="link in quickLinks"
						:key="link.to"
						type="button"
						class="grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 border border-white/10 bg-white/[0.03] px-4 py-3 text-left transition hover:border-violet-300/35 hover:bg-violet-500/10"
						@click="openQuickLink(link)"
					>
						<UIcon :name="link.icon" class="h-5 w-5 text-violet-200" />
						<span class="min-w-0">
							<span class="block font-semibold text-white">{{ link.label }}</span>
							<span class="mt-1 block text-sm text-neutral-400">{{ link.description }}</span>
						</span>
						<UIcon name="i-lucide-arrow-up-right" class="h-4 w-4 text-neutral-400" />
					</button>
				</div>

				<div class="flex justify-end">
					<div v-if="currentStepKey === 'discord'" class="flex flex-col gap-2 sm:flex-row">
						<UButton
							type="button"
							color="neutral"
							variant="ghost"
							:label="t('dashboard.onboarding.actions.back')"
							:disabled="isOnboardingSaving"
							@click="goBack"
						/>
						<UButton
							v-if="!hasDiscordIntegration"
							type="button"
							color="neutral"
							variant="ghost"
							:label="t('dashboard.onboarding.actions.skip_discord')"
							:disabled="isOnboardingSaving"
							@click="skipDiscord"
						/>
						<UButton
							type="button"
							color="primary"
							:icon="hasDiscordIntegration ? 'i-lucide-arrow-right' : 'ic:baseline-discord'"
							:label="hasDiscordIntegration ? t('dashboard.onboarding.actions.next') : t('dashboard.onboarding.actions.connect_discord')"
							:loading="isOnboardingSaving"
							@click="hasDiscordIntegration ? nextStep() : connectDiscord()"
						/>
					</div>

					<div v-else-if="currentStepKey === 'discord_warning'" class="flex flex-col gap-2 sm:flex-row">
						<UButton
							type="button"
							color="neutral"
							variant="ghost"
							:label="t('dashboard.onboarding.actions.back')"
							:disabled="isOnboardingSaving"
							@click="goBack"
						/>
						<UButton
							type="button"
							color="warning"
							trailing-icon="i-lucide-arrow-right"
							:label="t('dashboard.onboarding.actions.continue_without_discord')"
							:loading="isOnboardingSaving"
							@click="nextStep"
						/>
					</div>

					<div v-else class="flex flex-col gap-2 sm:flex-row">
						<UButton
							v-if="canGoBack"
							type="button"
							color="neutral"
							variant="ghost"
							:label="t('dashboard.onboarding.actions.back')"
							:disabled="isOnboardingSaving"
							@click="goBack"
						/>
						<UButton
							type="button"
							color="primary"
							trailing-icon="i-lucide-arrow-right"
							:label="currentStepKey === 'next' ? t('dashboard.onboarding.actions.finish') : t('dashboard.onboarding.actions.next')"
							:loading="isOnboardingSaving"
							:disabled="notificationSaveState === 'saving'"
							@click="currentStepKey === 'next' ? completeOnboarding() : nextStep()"
						/>
					</div>
				</div>
			</div>
		</template>
	</UModal>
</template>
