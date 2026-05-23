<script setup lang="ts">
import type { SettingsUser } from "@/Types/Settings";
import { useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	user: SettingsUser
}>();

const { t } = useI18n();
const form = useForm({
	username: props.user.name ?? '',
});
const passwordForm = useForm({
	current_password: '',
	password: '',
	password_confirmation: '',
});

const submit = () => {
	form.post(route('settings.username'));
};

const submitPasswordForm = () => {
	passwordForm.post(route('settings.password'), {
		preserveScroll: true,
		onSuccess: () => {
			passwordForm.reset();
			passwordForm.clearErrors();
		},
		onFinish: () => {
			passwordForm.password = '';
			passwordForm.password_confirmation = '';
		},
	});
};
</script>

<template>
	<UCard class="h-full w-full dark:bg-elevated/25">
		<template #header>
			<div class="flex flex-row items-center font-semibold text-md">
				<UIcon name="i-lucide-user" class="mr-2" size="22" />
				<p>{{ t('settings.account.title') }}</p>
			</div>
		</template>

		<div class="w-full flex flex-col items-start gap-4">
			<form @submit.prevent="submit" class="w-full flex flex-col items-start gap-4">
				<UFormField class="w-full" :label="t('general.username')">
					<UInput
						v-model="form.username"
						:placeholder="t('general.username')"
						size="xl"
						class="w-full"
						autocomplete="username"
					/>
				</UFormField>

				<UFormField class="w-full" :label="t('general.email')">
					<UInput
						:model-value="props.user.email"
						:placeholder="t('general.email')"
						size="xl"
						class="w-full"
						disabled
					/>
				</UFormField>

				<UButton
					type="submit"
					:label="t('settings.account.save')"
					size="lg"
					color="neutral"
					:loading="form.processing"
				/>
			</form>

			<div class="w-full border-t border-default pt-4">
				<form class="flex w-full flex-col items-start gap-4" @submit.prevent="submitPasswordForm">
					<UFormField
						class="w-full"
						:label="t('settings.account.old_password')"
						:error="passwordForm.errors.current_password"
					>
						<UInput
							v-model="passwordForm.current_password"
							type="password"
							size="xl"
							class="w-full"
							:placeholder="t('settings.account.old_password')"
							autocomplete="current-password"
						/>
					</UFormField>

					<div class="grid w-full grid-cols-1 gap-4 xl:grid-cols-2">
						<UFormField
							class="w-full"
							:label="t('settings.account.new_password')"
							:error="passwordForm.errors.password"
						>
							<UInput
								v-model="passwordForm.password"
								type="password"
								size="xl"
								class="w-full"
								:placeholder="t('settings.account.new_password')"
								autocomplete="new-password"
							/>
						</UFormField>

						<UFormField
							class="w-full"
							:label="t('settings.account.new_password_confirmation')"
							:error="passwordForm.errors.password_confirmation"
						>
							<UInput
								v-model="passwordForm.password_confirmation"
								type="password"
								size="xl"
								class="w-full"
								:placeholder="t('settings.account.new_password_confirmation')"
								autocomplete="new-password"
							/>
						</UFormField>
					</div>

					<UButton
						type="submit"
						:label="t('settings.account.update_password')"
						size="lg"
						color="neutral"
						:loading="passwordForm.processing"
					/>
				</form>
			</div>
		</div>
	</UCard>
</template>
