<script setup lang="ts">
import {useForm, Link, Head} from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import LoginWithXIVAuth from "@/components/LoginWithXIVAuth.vue";
import LoginWithGoogle from "@/components/LoginWithGoogle.vue";
import LoginWithDiscord from "@/components/LoginWithDiscord.vue";
import { route } from "ziggy-js";
import {useI18n} from "vue-i18n";
import { usePasswordVisibility } from "@/composables/usePasswordVisibility";

const { t } = useI18n();
const passwordVisibility = usePasswordVisibility(['password', 'password_confirmation'] as const);
const form = useForm({
	username: '',
	email: '',
	password: '',
	password_confirmation: '',
})

const submit = () => {
	form.post(route('register.store'))
}
defineOptions({
	layout: AuthLayout
})
</script>

<template>
	<Head title="Register -" />
	<div>
		<div class="mb-1 mx-auto">
			<p class="italic text-center text-gray-600 dark:text-gray-300">{{ t('auth.express_options') }}</p>
		</div>
		<div class="space-y-2 mb-4">
			<LoginWithXIVAuth />
			<LoginWithGoogle />
			<LoginWithDiscord />
		</div>

		<div class="flex items-center gap-4 mb-4">
			<div class="h-px flex-1 bg-slate-300 dark:bg-slate-600"></div>
			<span class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Or</span>
			<div class="h-px flex-1 bg-slate-300 dark:bg-slate-600"></div>
		</div>

		<div class="flex items-center w-full mb-4">
			<form class="space-y-4 w-full" @submit.prevent="submit">
				<UFormField name="username" class="w-full" :error="form.errors.username">
					<UInput v-model="form.username" size="xl" class="w-full" :placeholder="t('general.username')"/>
				</UFormField>

				<UFormField name="email" class="w-full" :error="form.errors.email">
					<UInput v-model="form.email" type="email" size="xl" class="w-full" :placeholder="t('general.email')"/>
				</UFormField>

				<UFormField name="password" :error="form.errors.password">
					<UInput
						v-model="form.password"
						:type="passwordVisibility.inputType('password')"
						size="xl"
						class="w-full"
						:placeholder="t('auth.password')"
						:ui="{ trailing: 'pe-1' }"
					>
						<template #trailing>
							<UButton
								type="button"
								color="neutral"
								variant="ghost"
								size="sm"
								:icon="passwordVisibility.icon('password')"
								:aria-label="t('auth.password')"
								@click="passwordVisibility.toggle('password')"
							/>
						</template>
					</UInput>
				</UFormField>

				<UFormField name="password_confirmation">
					<UInput
						v-model="form.password_confirmation"
						:type="passwordVisibility.inputType('password_confirmation')"
						size="xl"
						class="w-full"
						:placeholder="t('auth.password_confirmation')"
						:ui="{ trailing: 'pe-1' }"
					>
						<template #trailing>
							<UButton
								type="button"
								color="neutral"
								variant="ghost"
								size="sm"
								:icon="passwordVisibility.icon('password_confirmation')"
								:aria-label="t('auth.password_confirmation')"
								@click="passwordVisibility.toggle('password_confirmation')"
							/>
						</template>
					</UInput>
				</UFormField>
				<UButton type="submit" color="brand" size="xl" class="w-full justify-center" :disabled="form.processing">
					{{ t('auth.register') }}
				</UButton>
			</form>
		</div>

		<div class="flex items-center justify-center flex-col space-y-2 w-full">
			<p>{{ t('auth.existing_account') }} <Link :href="route('login')" class="text-brand">{{ t('auth.log_in_now') }}</Link></p>
		</div>
	</div>
</template>

<style scoped>

</style>
