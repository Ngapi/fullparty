<script setup lang="ts">
import type { GroupDashboardGroup } from "@/Types/Groups";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";
// @ts-ignore
import { useConfirmationModal } from "@/composables/useConfirmationModal";
import { useGroupNotificationToast } from "@/composables/useGroupNotificationToast";

const props = defineProps<{
	group: GroupDashboardGroup
}>();

const { t } = useI18n();
const confirmationModal = useConfirmationModal();
const { showGroupNotificationsToast } = useGroupNotificationToast();

const openRuns = () => {
	router.get(route("groups.dashboard.activities.index", props.group.slug));
};

const openMembers = () => {
	router.get(route("groups.dashboard.members", props.group.slug));
};

const openSettings = () => {
	router.get(route("groups.dashboard.settings", props.group.slug));
};

const toggleNotifications = () => {
	if (!props.group.permissions.can_toggle_notifications) {
		return;
	}

	const enabled = !props.group.follow.notifications_enabled;

	router.patch(route("groups.follow-notifications.update", props.group.slug), {
		enabled,
	}, {
		preserveScroll: true,
		onSuccess: () => {
			showGroupNotificationsToast(enabled);
		},
	});
};

const leaveGroup = async () => {
	if (!props.group.permissions.can_leave) {
		return;
	}

	await confirmationModal.open({
		title: t("groups.dashboard.leave_group_modal.title", { name: props.group.name }),
		description: t("groups.dashboard.leave_group_modal.description", { name: props.group.name }),
		severity: "error",
		warningText: t("groups.dashboard.leave_group_modal.warning"),
		confirmLabel: t("groups.dashboard.leave_group_modal.confirm"),
		confirmIcon: "i-lucide-log-out",
		onConfirm: async ({ patch }) => {
			patch({ confirmLoading: true });

			return await new Promise<boolean>((resolve) => {
				router.post(route("groups.leave", props.group.slug), {
					redirect_to: props.group.is_visible ? "profile" : "groups",
				}, {
					preserveScroll: true,
					onSuccess: () => {
						resolve(true);
					},
					onError: () => {
						resolve(false);
					},
					onFinish: () => {
						patch({ confirmLoading: false });
					},
				});
			});
		},
	});
};

const openDiscordInvite = () => {
	if (!props.group.discord_invite_url || typeof window === "undefined") {
		return;
	}

	window.open(props.group.discord_invite_url, "_blank", "noopener,noreferrer");
};
</script>

<template>
	<div class="w-full max-w-md ">
		<div class="flex flex-row justify-end items-center gap-4">
			<UButton
				v-if="group.permissions.can_view_members"
				color="neutral"
				icon="i-lucide-users"
				:label="t('groups.dashboard.actions.view_members')"
				class="justify-center"
				@click="openMembers"
			/>
			<UButton
				color="neutral"
				icon="i-lucide-calendar-range"
				:label="t('groups.dashboard.actions.view_runs')"
				class="justify-center"
				@click="openRuns"
			/>
			<UButton
				v-if="group.discord_invite_url"
				color="neutral"
				variant="ghost"
				icon="ic:baseline-discord"
				size="xl"
				class="justify-center"
				@click="openDiscordInvite"
			/>
			<UButton
				v-if="group.permissions.can_toggle_notifications"
				color="neutral"
				variant="ghost"
				:icon="group.follow.notifications_enabled ? 'i-lucide-bell' : 'i-lucide-bell-off'"
				class="justify-center sm:col-span-2"
				@click="toggleNotifications"
				size="xl"
			/>
			<UButton
				v-if="group.permissions.can_leave"
				color="error"
				variant="ghost"
				icon="i-lucide-log-out"
				class="justify-center sm:col-span-2"
				@click="leaveGroup"
			/>
		</div>
	</div>
</template>
