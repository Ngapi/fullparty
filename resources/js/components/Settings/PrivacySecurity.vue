<script setup lang="ts">
// @ts-ignore
import { useConfirmationModal } from "@/composables/useConfirmationModal";
import type { SettingsUser } from "@/Types/Settings";
import { router, useForm } from "@inertiajs/vue3";
import { useToast } from "@nuxt/ui/composables";
import { ref } from "vue";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	user: SettingsUser
}>();

const { t } = useI18n();
const toast = useToast();
const confirmationModal = useConfirmationModal();

const form = useForm({
	public_profile: props.user.public_profile,
	public_characters: props.user.public_characters,
});

const items = ref([
	{
		label: t('general.public'),
		value: true,
	},
	{
		label: t('general.private'),
		value: false,
	},
]);

const submit = () => {
	form.post(route('settings.privacy'));
};

const promptDeleteAccount = async () => {
	await confirmationModal.open({
		title: t('settings.privacy.delete_account_modal.title'),
		description: t('settings.privacy.delete_account_modal.description'),
		severity: 'error',
		warningText: t('settings.privacy.delete_account_modal.warning'),
		confirmLabel: t('settings.privacy.delete_account_modal.confirm'),
		confirmIcon: 'i-lucide-trash-2',
		onConfirm: async ({ patch }) => {
			patch({ confirmLoading: true });

			return await new Promise<boolean>((resolve) => {
				router.delete(route('settings.account.destroy'), {
					preserveScroll: true,
					onSuccess: () => {
						resolve(true);
					},
					onError: (errors) => {
						if (errors.error === 'account_delete_group_owner') {
							toast.add({
								title: t('general.error'),
								description: t('settings.privacy.delete_account_owner_blocked'),
								color: 'error',
								icon: 'i-lucide-triangle-alert',
							});
						}

						resolve(false);
					},
					onFinish: () => {
						patch({ confirmLoading: false });
					},
				});
			});
		},
	});
};
</script>

<template>
	<UCard class="w-full dark:bg-elevated/25">
		<template #header>
			<div class="flex flex-row items-center font-semibold text-md">
				<UIcon name="i-lucide-lock" class="mr-2" size="22" />
				<p>{{ t('settings.privacy.title') }}</p>
			</div>
		</template>

		<form @submit.prevent="submit" class="w-full flex flex-col items-stretch gap-4 mb-4">
			<div class="option">
				<div>
					<p class="font-semibold">{{ t('settings.privacy.profile_visibility') }}</p>
					<p class="text-sm">{{ t('settings.privacy.profile_visibility_description') }}</p>
				</div>
				<USelect class="min-w-24" v-model="form.public_profile" :items="items" />
			</div>

			<div class="option">
				<div>
					<p class="font-semibold">{{ t('settings.privacy.show_character_data') }}</p>
					<p class="text-sm">{{ t('settings.privacy.show_character_data_description') }}</p>
				</div>
				<UCheckbox v-model="form.public_characters" />
			</div>

			<div class="flex">
				<UButton type="submit" :label="t('settings.privacy.save')" size="lg" color="neutral" />
			</div>
		</form>

		<div class="flex flex-col items-start gap-4 mb-4 border-t border-neutral-200 pt-4">
			<div class="w-full">
				<p class="font-semibold">{{ t('settings.privacy.danger_zone') }}</p>
				<p class="text-sm">{{ t('settings.privacy.delete_account_description') }}</p>
			</div>
			<UButton
				:label="t('settings.privacy.delete_account')"
				size="lg"
				color="error"
				icon="i-lucide-trash-2"
				@click="promptDeleteAccount"
			/>
		</div>
	</UCard>
</template>

<style scoped>
@reference "../../../css/app.css";

.option {
	@apply w-full flex flex-row items-center justify-between;
}
</style>
