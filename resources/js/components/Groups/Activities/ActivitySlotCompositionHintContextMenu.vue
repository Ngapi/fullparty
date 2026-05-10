<script setup lang="ts">
import type { ContextMenuItem } from "@nuxt/ui";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { ActivitySlot, ActivitySlotCompositionHintInput } from "@/Types/ActivityRoster";

type RoleHintKey = "tank" | "healer" | "dps";

const props = withDefaults(defineProps<{
	slot: ActivitySlot
	disabled?: boolean
}>(), {
	disabled: false,
});

const emit = defineEmits<{
	replaceHints: [payload: { slotId: number, compositionHints: ActivitySlotCompositionHintInput[] }]
	customize: [slot: ActivitySlot]
}>();

const { t } = useI18n();

const roleOptions: Array<{ key: RoleHintKey, label: string, icon: string }> = [
	{ key: "tank", label: "groups.activities.management.roster.roles.tank", icon: "i-lucide-shield" },
	{ key: "healer", label: "groups.activities.management.roster.roles.healer", icon: "i-lucide-heart-pulse" },
	{ key: "dps", label: "groups.activities.management.roster.roles.dps", icon: "i-lucide-swords" },
];

const hasCustomHints = computed(() => props.slot.composition_hints.some((hint) => hint.type === "class"));
const activeRoleHints = computed<RoleHintKey[]>(() => (
	hasCustomHints.value
		? []
		: props.slot.composition_hints
			.filter((hint) => hint.type === "role")
			.map((hint) => hint.key)
			.filter((key): key is RoleHintKey => key === "tank" || key === "healer" || key === "dps")
));
const isDisabled = computed(() => (
	props.disabled
	|| props.slot.is_bench
	|| props.slot.assigned_character_id !== null
));

const toggleRoleHint = (roleKey: RoleHintKey) => {
	if (isDisabled.value) {
		return;
	}

	const nextRoles = activeRoleHints.value.includes(roleKey)
		? activeRoleHints.value.filter((key) => key !== roleKey)
		: [...activeRoleHints.value, roleKey];

	emit("replaceHints", {
		slotId: props.slot.id,
		compositionHints: nextRoles.map((key) => ({
			type: "role",
			key,
		})),
	});
};

const openCustomPicker = () => {
	if (isDisabled.value) {
		return;
	}

	emit("customize", props.slot);
};

const contextMenuItems = computed<ContextMenuItem[][]>(() => [
	roleOptions.map((role) => ({
		type: "checkbox",
		label: t(role.label),
		icon: role.icon,
		checked: activeRoleHints.value.includes(role.key),
		disabled: isDisabled.value,
		onSelect: () => toggleRoleHint(role.key),
	})),
	[
		{
			label: t("groups.activities.management.roster.composition_custom"),
			icon: "i-lucide-sliders-horizontal",
			disabled: isDisabled.value,
			type: "checkbox",
			checked: hasCustomHints.value,
			onSelect: openCustomPicker,
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
