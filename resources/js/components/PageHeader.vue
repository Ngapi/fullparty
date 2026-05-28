<script setup lang="ts">
defineProps({
	title: {
		type: String,
		default: "Title",
	},
	subtitle: {
		type: String,
		default: "Subtitle",
	}
});
</script>

<template>
	<div class="flex max-w-full flex-col items-center gap-4 overflow-x-hidden py-4 text-center xl:flex-row xl:items-start xl:justify-between xl:text-left">
		<div class="flex w-full min-w-0 flex-1 flex-col items-center gap-2 xl:items-start">
			<h1 class="break-words wrap-anywhere text-3xl font-black">{{title}}</h1>
			<p class="break-words wrap-anywhere text-muted text-md">{{subtitle}}</p>
		</div>
		<div class="mt-2 flex w-full min-w-0 max-w-full flex-col items-center xl:w-auto xl:max-w-full xl:shrink xl:items-end">
			<slot />
		</div>
	</div>
</template>

<style scoped>

</style>
