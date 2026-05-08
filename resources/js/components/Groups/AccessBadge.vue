<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = withDefaults(defineProps<{
	role: string | null | undefined
	fallbackRole?: 'member' | 'moderator'
}>(), {
	fallbackRole: 'member',
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
		size="lg"
		variant="subtle"
		class="min-w-44 justify-center py-2"
		:color="badge.color"
		:icon="badge.icon"
		:label="badge.label"
	/>
</template>
