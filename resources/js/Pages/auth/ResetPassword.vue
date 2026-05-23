<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	token: string
	email: string | null
}>();

const { t } = useI18n();

const form = useForm({
	token: props.token,
	email: props.email ?? '',
	password: '',
	password_confirmation: '',
});

const submit = () => {
	form.post(route('password.update'));
};

defineOptions({
	layout: AuthLayout,
});
</script>

<template>
	<Head :title="`${t('auth.reset_password_page.title')} -`" />

	<div>
		<div class="mb-6 text-center">
			<p class="text-2xl font-semibold text-toned">{{ t('auth.reset_password_page.title') }}</p>
			<p class="mt-2 text-sm text-muted">{{ t('auth.reset_password_page.subtitle') }}</p>
		</div>

		<form class="space-y-4" @submit.prevent="submit">
			<UFormField name="email" class="w-full" :error="form.errors.email">
				<UInput
					v-model="form.email"
					type="email"
					size="xl"
					class="w-full"
					:placeholder="t('general.email')"
					readonly
				/>
			</UFormField>

			<UFormField name="password" class="w-full" :error="form.errors.password">
				<UInput
					v-model="form.password"
					type="password"
					size="xl"
					class="w-full"
					:placeholder="t('auth.password')"
				/>
			</UFormField>

			<UFormField name="password_confirmation" class="w-full">
				<UInput
					v-model="form.password_confirmation"
					type="password"
					size="xl"
					class="w-full"
					:placeholder="t('auth.password_confirmation')"
				/>
			</UFormField>

			<UButton
				type="submit"
				color="brand"
				size="xl"
				class="w-full justify-center"
				:disabled="form.processing"
			>
				{{ form.processing ? t('auth.reset_password_page.resetting') : t('auth.reset_password_page.submit') }}
			</UButton>
		</form>

		<div class="mt-6 flex items-center justify-center">
			<Link :href="route('login')" class="text-brand">{{ t('auth.back_to_login') }}</Link>
		</div>
	</div>
</template>
