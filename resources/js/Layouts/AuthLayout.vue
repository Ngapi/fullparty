<script setup lang="ts">
import AppLocaleSelect from "@/components/Navigation/AppLocaleSelect.vue";
import { useI18n } from 'vue-i18n'
import { usePersistentLocale } from "@/composables/usePersistentLocale";
const char1 = '/ft.jpg'

const { t } = useI18n({ useScope: 'global' })
const { currentUiLocale } = usePersistentLocale();
</script>

<template>
	<UApp :locale="currentUiLocale">
		<div class="relative min-h-screen overflow-hidden bg-white dark:bg-neutral-950">
			<div class="absolute inset-0 lg:hidden">
				<img class="h-full w-full object-cover" :src="char1" alt="">
				<div class="absolute inset-0 bg-white/40 dark:bg-neutral-950/62" />
				<div class="absolute inset-0 bg-linear-to-t from-white via-white/30 to-transparent dark:from-neutral-950 dark:via-neutral-950/35" />
			</div>

			<div class="relative z-10 flex min-h-screen flex-col lg:grid lg:grid-cols-12">
				<div class="hidden lg:col-span-7 lg:block">
					<img class="h-full min-h-screen w-full object-cover" :src="char1" alt="">
				</div>

				<div class="flex min-h-screen items-center justify-center overflow-y-auto px-4 py-20 sm:px-6 lg:col-span-5 lg:bg-white lg:px-10 lg:py-12 dark:lg:bg-neutral-950">
					<div class="w-full max-w-md border border-white/20 bg-white/88 p-5 shadow-2xl shadow-neutral-950/30 backdrop-blur-md sm:p-7 lg:max-w-lg lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none lg:backdrop-blur-none dark:border-white/10 dark:bg-neutral-950/82">
						<div class="mb-6 w-full text-center lg:text-left">
							<h1 class="text-4xl font-bold italic text-gray-950 sm:text-5xl lg:text-6xl dark:text-white">{{ t('meta.title') }}</h1>
							<p class="mt-2 italic text-gray-600 dark:text-gray-300 lg:mx-4">{{ t('meta.tagline') }}</p>
						</div>
						<slot />
					</div>
				</div>
			</div>

			<div class="fixed top-0 right-0 z-30 p-4 space-x-2">
				<AppLocaleSelect variant="ghost" />
			</div>
		</div>
	</UApp>
</template>
