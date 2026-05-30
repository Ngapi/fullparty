<script setup lang="ts">
// @ts-ignore
import { useConfirmationModal } from "@/composables/useConfirmationModal";
import type { SettingsUser } from "@/Types/Settings";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { useToast } from "@nuxt/ui/composables";
import { route } from "ziggy-js";
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	user: SettingsUser
}>();

const { t } = useI18n();
const page = usePage();
const toast = useToast();
const confirmationModal = useConfirmationModal();
const linkToken = ref<{ token: string; expires_at: string } | null>(null);
const generatingLinkToken = ref(false);

const form = useForm({
	application_notifications: props.user.application_notifications,
	run_and_reminder_notifications: props.user.run_and_reminder_notifications,
	group_update_notifications: props.user.group_update_notifications,
	assignment_notifications: props.user.assignment_notifications,
	account_character_notifications: props.user.account_character_notifications,
	system_notice_notifications: props.user.system_notice_notifications,
	email_notifications: props.user.email_notifications,
	discord_notifications: props.user.discord_notifications,
});

const hasDiscordIntegration = computed(() => props.user.discord_user_integration !== null);
const hasActiveLinkToken = computed(() => {
	if (linkToken.value) {
		return true;
	}

	if (!props.user.discord_link_token_expires_at) {
		return false;
	}

	return new Date(props.user.discord_link_token_expires_at).getTime() > Date.now();
});
const linkTokenExpiresAt = computed(() => linkToken.value?.expires_at ?? props.user.discord_link_token_expires_at);

const submit = () => {
	form.post(route('settings.notifications'));
};

const disconnectDiscord = async () => {
	await confirmationModal.open({
		title: t('settings.notifications.disconnect_discord_modal.title'),
		description: t('settings.notifications.disconnect_discord_modal.description'),
		severity: 'warning',
		warningText: t('settings.notifications.disconnect_discord_modal.warning'),
		confirmLabel: t('settings.notifications.disconnect_discord_modal.confirm'),
		confirmIcon: 'i-lucide-unplug',
		onConfirm: async ({ patch }) => {
			patch({ confirmLoading: true });

			return await new Promise<boolean>((resolve) => {
				router.delete(route('settings.discord-integration.destroy'), {
					preserveScroll: true,
					onSuccess: () => resolve(true),
					onError: () => resolve(false),
					onFinish: () => patch({ confirmLoading: false }),
				});
			});
		},
	});
};

const generateDiscordLinkToken = () => {
	generatingLinkToken.value = true;

	router.post(route('settings.discord-integration.link-token'), {}, {
		preserveScroll: true,
		onFinish: () => {
			generatingLinkToken.value = false;
		},
	});
};

const copyDiscordLinkToken = async () => {
	if (!linkToken.value) {
		return;
	}

	await navigator.clipboard.writeText(linkToken.value.token);

	toast.add({
		title: t('settings.notifications.discord_link_token_copied'),
		color: 'success',
		icon: 'i-lucide-copy-check',
	});
};

watch(
	() => page.props.flash?.data?.discord_user_link_token,
	(value) => {
		linkToken.value = (value as { token: string; expires_at: string } | null) ?? null;
	},
	{ immediate: true },
);
</script>

<template>
	<UCard class="w-full dark:bg-elevated/25">
		<template #header>
			<div class="flex flex-row items-center font-semibold text-md">
				<UIcon name="i-lucide-bell" class="mr-2" size="22" />
				<p>{{ t('settings.notifications.title') }}</p>
			</div>
		</template>

		<form @submit.prevent="submit" class="w-full flex flex-col items-stretch gap-4 mb-4">
			<div class="section">
				<div class="section-heading">
					<p class="font-semibold text-toned">{{ t('settings.notifications.categories_title') }}</p>
					<p class="text-sm text-muted">{{ t('settings.notifications.categories_description') }}</p>
				</div>

				<div class="option">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.applications') }}</p>
						<p class="text-sm">{{ t('settings.notifications.applications_description') }}</p>
					</div>
					<USwitch v-model="form.application_notifications" />
				</div>

				<div class="option">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.assignments') }}</p>
						<p class="text-sm">{{ t('settings.notifications.assignments_description') }}</p>
					</div>
					<USwitch v-model="form.assignment_notifications" />
				</div>

				<div class="option">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.runs_and_reminders') }}</p>
						<p class="text-sm">{{ t('settings.notifications.runs_and_reminders_description') }}</p>
					</div>
					<USwitch v-model="form.run_and_reminder_notifications" />
				</div>

				<div class="option">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.group_updates') }}</p>
						<p class="text-sm">{{ t('settings.notifications.group_updates_description') }}</p>
					</div>
					<USwitch v-model="form.group_update_notifications" />
				</div>

				<div class="option">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.account_character_updates') }}</p>
						<p class="text-sm">{{ t('settings.notifications.account_character_updates_description') }}</p>
					</div>
					<USwitch v-model="form.account_character_notifications" />
				</div>

				<div class="option">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.system_notices') }}</p>
						<p class="text-sm">{{ t('settings.notifications.system_notices_description') }}</p>
					</div>
					<USwitch v-model="form.system_notice_notifications" />
				</div>
			</div>

			<div class="section">
				<div class="section-heading">
					<p class="font-semibold text-toned">{{ t('settings.notifications.delivery_title') }}</p>
					<p class="text-sm text-muted">{{ t('settings.notifications.delivery_description') }}</p>
				</div>

				<div class="option">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.email_notifications') }}</p>
						<p class="text-sm">{{ t('settings.notifications.email_notifications_description') }}</p>
					</div>
					<USwitch v-model="form.email_notifications" />
				</div>

				<div :class="hasDiscordIntegration ? 'option' : 'option-muted'">
					<div class="option-copy">
						<p class="font-semibold">{{ t('settings.notifications.discord_notifications') }}</p>
						<p class="text-sm">{{ t('settings.notifications.discord_notifications_description') }}</p>
					</div>
					<div class="flex flex-col items-end gap-2">
						<USwitch v-model="form.discord_notifications" :disabled="!hasDiscordIntegration" />
						<div v-if="!hasDiscordIntegration" class="flex flex-wrap justify-end gap-2">
							<UButton
								:to="route('discord-app.user.redirect')"
								size="xs"
								color="neutral"
								variant="subtle"
								icon="ic:baseline-discord"
								:label="t('settings.notifications.install_discord_app')"
							/>
							<UButton
								size="xs"
								color="neutral"
								variant="subtle"
								icon="i-lucide-key-round"
								:loading="generatingLinkToken"
								:label="t('settings.notifications.generate_discord_link_token')"
								@click="generateDiscordLinkToken"
							/>
						</div>
						<UButton
							v-else
							size="xs"
							color="error"
							variant="subtle"
							icon="i-lucide-unplug"
							:label="t('settings.notifications.disconnect_discord')"
							@click="disconnectDiscord"
						/>
					</div>
				</div>

				<div
					v-if="!hasDiscordIntegration && (linkToken || hasActiveLinkToken)"
					class="border border-brand-400/25 bg-brand-500/10 px-4 py-3"
				>
					<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
						<div class="min-w-0">
							<p class="text-sm font-semibold text-toned">{{ t('settings.notifications.discord_link_token_title') }}</p>
							<p v-if="linkToken" class="mt-1 break-all font-mono text-base font-semibold text-highlighted">{{ linkToken.token }}</p>
							<p class="mt-1 text-xs text-muted">
								{{ t(
									linkToken
										? 'settings.notifications.discord_link_token_expires'
										: 'settings.notifications.discord_link_token_active',
									{ date: linkTokenExpiresAt ? new Date(linkTokenExpiresAt).toLocaleString() : '' },
								) }}
							</p>
						</div>
						<UButton
							v-if="linkToken"
							size="xs"
							color="neutral"
							variant="soft"
							icon="i-lucide-copy"
							:label="t('settings.notifications.copy_discord_link_token')"
							@click="copyDiscordLinkToken"
						/>
					</div>
				</div>
			</div>

			<div class="m-0 p-0">
				<UButton type="submit" :label="t('settings.notifications.save')" size="lg" color="neutral" />
			</div>
		</form>
	</UCard>
</template>

<style scoped>
@reference "../../../css/app.css";

.option {
	@apply grid w-full grid-cols-[minmax(0,1fr)_auto] items-start gap-4 border border-default bg-default/30 px-4 py-3 dark:border-white/20 dark:bg-white/5;
}

.option-muted {
	@apply grid w-full grid-cols-[minmax(0,1fr)_auto] items-start gap-4 border border-default/70 bg-muted/20 px-4 py-3 text-muted cursor-not-allowed dark:border-white/15 dark:bg-white/3;
}

.option-copy {
	@apply min-w-0 border-b border-default/80 pb-2 md:border-b-0 md:pb-0 dark:border-white/20;
}

.section {
	@apply flex flex-col gap-4;
}

.section-heading {
	@apply flex flex-col gap-1 pb-1;
}
</style>
