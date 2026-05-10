import { useToast } from "@nuxt/ui/composables";
import { useI18n } from "vue-i18n";

export const useGroupNotificationToast = () => {
	const toast = useToast();
	const { t } = useI18n();

	const showGroupNotificationsToast = (enabled: boolean) => {
		toast.add({
			title: t("general.success"),
			description: enabled
				? t("groups.notifications.toasts.unmuted")
				: t("groups.notifications.toasts.muted"),
			color: "success",
			icon: enabled ? "i-lucide-bell" : "i-lucide-bell-off",
		});
	};

	return {
		showGroupNotificationsToast,
	};
};
