import type { GroupNotificationPreferences, NotificationPreferenceChannel } from "@/Types/Groups";

export const GROUP_NOTIFICATION_TOPIC_KEYS = [
	"group_updates.run_posts",
	"group_updates.membership",
	"group_updates.roles",
] as const;

const channel: NotificationPreferenceChannel = "in_app";

type GroupNotificationState = {
	enabled: boolean
	preferences?: GroupNotificationPreferences
};

export const groupNotificationIcon = (notifications: GroupNotificationState) => {
	if (!notifications.enabled) {
		return "i-lucide-bell-off";
	}

	const disabledCount = GROUP_NOTIFICATION_TOPIC_KEYS.filter((topic) => (
		notifications.preferences?.[topic]?.[channel] === false
	)).length;

	if (disabledCount === GROUP_NOTIFICATION_TOPIC_KEYS.length) {
		return "i-lucide-bell-off";
	}

	if (disabledCount > 0) {
		return "i-lucide-bell-minus";
	}

	return "i-lucide-bell";
};
