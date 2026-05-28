<script setup lang="ts">
import { usePersistentLocale } from "@/composables/usePersistentLocale"

const { currentUiLocale } = usePersistentLocale()
</script>

<template>
	<UApp :locale="currentUiLocale">
		<slot />
	</UApp>
</template>
