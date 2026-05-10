<script setup lang="ts">
import type { ContextMenuItem } from "@nuxt/ui";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { useToast } from "@nuxt/ui/composables";
import { utcToDiscordTimestamp } from "@/utils/discordTimestamp";

const props = withDefaults(defineProps<{
	startsAt: string | null
	disabled?: boolean
}>(), {
	disabled: false,
});

const { t } = useI18n();
const toast = useToast();

const discordTimestamp = computed(() => props.startsAt ? utcToDiscordTimestamp(props.startsAt) : null);
const isDisabled = computed(() => props.disabled || !discordTimestamp.value);

const copyDiscordTimestamp = async () => {
	if (!discordTimestamp.value) {
		return;
	}

	console.log("Discord timestamp:", discordTimestamp.value);

	try {
		await navigator.clipboard.writeText(discordTimestamp.value);

		toast.add({
			title: t("groups.activities.context_menu.copy_discord_timestamp_success_title"),
			description: t("groups.activities.context_menu.copy_discord_timestamp_success_description"),
			color: "success",
		});
	} catch (error) {
		console.error(error);

		toast.add({
			title: t("groups.activities.context_menu.copy_discord_timestamp_error_title"),
			description: t("groups.activities.context_menu.copy_discord_timestamp_error_description"),
			color: "error",
		});
	}
};

const contextMenuItems = computed<ContextMenuItem[][]>(() => [
	[
		{
			label: t("groups.activities.context_menu.copy_discord_timestamp"),
			icon: "i-lucide-copy",
			disabled: isDisabled.value,
			onSelect: copyDiscordTimestamp,
		},
	],
]);
</script>

<template>
	<UContextMenu
		:items="contextMenuItems"
		:disabled="isDisabled"
	>
		<slot />
	</UContextMenu>
</template>
