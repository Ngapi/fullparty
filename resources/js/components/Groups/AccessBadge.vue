<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = withDefaults(defineProps<{
	role: string | null | undefined
	fallbackRole?: 'member' | 'moderator' | 'admin'
	compact?: boolean
}>(), {
	fallbackRole: 'member',
	compact: false,
});

const { t } = useI18n();

const badge = computed(() => {
	if (props.role === 'owner') {
		return {
			label: t('groups.access.owner'),
			color: 'warning',
			icon: 'i-lucide-crown',
		};
	}

	if (props.role === 'admin' || props.fallbackRole === 'admin') {
		return {
			label: t('groups.access.admin'),
			color: 'secondary',
			icon: 'i-lucide-shield-check',
		};
	}

	if (props.role === 'moderator' || props.fallbackRole === 'moderator') {
		return {
			label: t('groups.access.moderator'),
			color: 'primary',
			icon: 'i-lucide-shield',
		};
	}

	return {
		label: t('groups.access.member'),
		color: 'neutral',
		icon: 'i-lucide-user',
	};
});
</script>

<template>
	<UBadge
		:size="compact ? 'md' : 'lg'"
		variant="subtle"
		:class="compact ? 'py-1.5' : 'min-w-44 justify-center py-2'"
		:color="badge.color"
		:icon="badge.icon"
		:label="badge.label"
	/>
</template>
