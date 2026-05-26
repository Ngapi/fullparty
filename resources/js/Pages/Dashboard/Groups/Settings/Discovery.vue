<script setup lang="ts">
import type { GroupDashboardGroup } from '@/Types/Groups'
import AccessBadge from '@/components/Groups/AccessBadge.vue'
import GroupDiscoverySettings from '@/components/Groups/GroupDiscoverySettings.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useI18n } from 'vue-i18n'

defineProps<{
	group: GroupDashboardGroup
}>()

const { t } = useI18n()
</script>

<template>
	<div class="w-full">
		<PageHeader
			:title="t('groups.settings.discovery.title')"
			:subtitle="t('groups.settings.discovery.subtitle')"
		>
			<AccessBadge
				:role="group.current_user_role"
				fallback-role="admin"
			/>
		</PageHeader>

		<div class="mt-4">
			<GroupDiscoverySettings :group="group" />
		</div>
	</div>
</template>
