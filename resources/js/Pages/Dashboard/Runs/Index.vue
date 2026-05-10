<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import PageHeader from "@/components/PageHeader.vue";

const { t } = useI18n();
</script>

<template>
	<Head :title="`${t('runs.discovery.title')} -`" />

	<div class="w-full">
		<PageHeader
			:title="t('runs.discovery.title')"
			:subtitle="t('runs.discovery.subtitle')"
		/>
	</div>
</template>
