<script setup lang="ts">
import type { SettingsUser } from "@/Types/Settings";
import {useI18n} from "vue-i18n";
import PageHeader from "@/components/PageHeader.vue";
import AccountSettings from "@/components/Settings/AccountSettings.vue";
import ConnectedAccounts from "@/components/Settings/ConnectedAccounts.vue";
import Notifications from "@/components/Settings/Notifications.vue";
import PrivacySecurity from "@/components/Settings/PrivacySecurity.vue";
import {usePage} from "@inertiajs/vue3";
import {computed, watch} from "vue";
import {useToast} from "@nuxt/ui/composables";

const { t } = useI18n();

const page = usePage()
const toast = useToast()
const user = computed(() => page.props.auth?.user as SettingsUser)

watch(
	() => page.props.flash?.success,
	(success) => {
		if (!success) return

		if (success.includes('username_updated')) {
			toast.add({
				title: t('settings.toasts.title'),
				description: t('settings.toasts.username_updated'),
				color: 'success',
				icon: 'i-lucide-check'
			})
		}
		if(success.includes('notification_settings_updated')){
			toast.add({
				title: t('settings.toasts.title'),
				description: t('settings.toasts.notification_updated'),
				color: 'success',
				icon: 'i-lucide-check'
			})
		}
		if(success.includes('privacy_settings_updated')){
			toast.add({
				title: t('settings.toasts.title'),
				description: t('settings.toasts.privacy_updated'),
				color: 'success',
				icon: 'i-lucide-check'
			})
		}
		if(success.includes('social_account_unlinked')){
			toast.add({
				title: t('settings.toasts.title'),
				description: t('settings.toasts.social_account_unlinked'),
				color: 'success',
				icon: 'i-lucide-check'
			})
		}
		if(success.includes('password_updated')){
			toast.add({
				title: t('settings.toasts.title'),
				description: t('settings.toasts.password_updated'),
				color: 'success',
				icon: 'i-lucide-check'
			})
		}
	},
	{ immediate: true }
)

watch(
	() => page.props.errors?.error,
	(error) => {
		if (!error) return

		if (error === 'social_account_unlink_last_login_method') {
			toast.add({
				title: t('settings.toasts.title'),
				description: t('settings.toasts.social_account_unlink_blocked'),
				color: 'error',
				icon: 'i-lucide-triangle-alert'
			})
		}
	},
	{ immediate: true }
)
</script>

<template>
	<div class="w-full">
		<PageHeader :title="t('settings.title')" :subtitle="t('settings.subtitle')" />

		<div class="w-full flex flex-col items-start mt-4 gap-8">
			<div class="grid w-full grid-cols-1 gap-8 xl:grid-cols-2">
				<AccountSettings :user="user" />
				<ConnectedAccounts :user="user" />
			</div>
			<Notifications :user="user" />
			<PrivacySecurity :user="user" />
		</div>
	</div>
</template>
