<script setup>
import {usePage} from "@inertiajs/vue3";
import {computed, onMounted, ref} from "vue";
import {useI18n} from "vue-i18n";

const { t } = useI18n();
const page = usePage();
const storageKey = 'fullparty.development_notice.dismissed';
const isReady = ref(false);
const isDismissed = ref(false);

const siteLinks = computed(() => page.props.site_links ?? {
	discord: null,
});

onMounted(() => {
	try {
		isDismissed.value = window.localStorage.getItem(storageKey) === 'true';
	} catch {
		isDismissed.value = false;
	}

	isReady.value = true;
});

const dismissNotice = () => {
	isDismissed.value = true;

	try {
		window.localStorage.setItem(storageKey, 'true');
	} catch {
		// Dismiss for this render even if the browser blocks storage.
	}
};
</script>

<template>
	<div v-if="isReady && !isDismissed" class="relative mt-6">
		<div class="relative rounded-none border border-brand-500/50 bg-linear-to-br from-brand-800 to-neutral-950 p-4 pr-9 text-sm text-white/80">
			<UButton
				class="absolute right-2 top-2"
				color="neutral"
				variant="ghost"
				icon="i-lucide-x"
				size="xs"
				:aria-label="t('navigation.sidebar.notice.dismiss')"
				@click="dismissNotice"
			/>
			<p class="font-semibold text-white">{{t('navigation.sidebar.notice.title')}}</p>
			<p class="mt-1 leading-5">
				{{t('navigation.sidebar.notice.description')}}
			</p>

			<div v-if="siteLinks.discord" class="mt-3 flex gap-2">
				<a
					:href="siteLinks.discord"
					target="_blank"
					rel="noopener noreferrer"
					class="inline-flex items-center rounded-none border border-white/10 px-3 py-1.5 text-xs font-medium bg-primary-800 hover:bg-primary-600 transition"
				>
					{{t('navigation.sidebar.notice.discord')}}
				</a>
			</div>

			<div class="absolute -bottom-2 left-6 h-4 w-4 rotate-45 border-r-2 border-b-2 border-brand-500/50 bg-linear-to-r from-brand-950 via-brand-950 via-65% to-neutral-900"></div>
		</div>

		<div class="mt-4 pl-3">
			<UUser
				:ui="{
					name: 'text-white',
					description: 'text-white/60',
				}"
				name="Giki"
				:description="t('navigation.sidebar.notice.dev_tag')"
				:avatar="{
					src: 'https://img2.finalfantasyxiv.com/f/15cff6ad5af687333d4ae7545c7b4ec4_7206469080400ed57a5373d0a9c55c59fc0.jpg?1774692966',
					loading: 'lazy',
					icon: 'i-lucide-image'
				}"
				size="xl"
			/>
		</div>
	</div>
</template>

<style scoped>

</style>
