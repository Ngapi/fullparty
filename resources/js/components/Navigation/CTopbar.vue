<script setup lang="ts">
import NotificationBell from "@/components/Navigation/NotificationBell.vue";
import UserMenu from "@/components/Navigation/UserMenu.vue";
import AppLocaleSelect from "@/components/Navigation/AppLocaleSelect.vue";
import { usePersistentLocale } from "@/composables/usePersistentLocale";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n({ useScope: 'global' })
const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

defineProps({
	title: {
		type: String,
		default: "Title"
	}
});
</script>

<template>
	<UDashboardNavbar>
		<template #leading>
<!--			<UDashboardSidebarCollapse />-->
			<UInput :placeholder="t('navigation.topbar.search_bar')" :ui="{base: 'rounded-none placeholder:text-neutral-500'}" leading-icon="i-lucide-search" size="xl"  class="min-w-96"/>
		</template>

		<template #trailing>
<!--			<UBadge label="4" variant="subtle" />-->
		</template>

		<template #right>
			<NotificationBell v-if="user" />
			<AppLocaleSelect variant="ghost" />
			<UserMenu />
		</template>
	</UDashboardNavbar>
</template>

<style scoped>
/* Optional: Add custom scrollbar styling for the notifications panel */
.max-h-96::-webkit-scrollbar {
	width: 6px;
}

.max-h-96::-webkit-scrollbar-track {
	background: transparent;
}

.max-h-96::-webkit-scrollbar-thumb {
	background: rgba(156, 163, 175, 0.3);
	border-radius: 3px;
}

.max-h-96::-webkit-scrollbar-thumb:hover {
	background: rgba(156, 163, 175, 0.5);
}
</style>
