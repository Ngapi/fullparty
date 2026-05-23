<script setup lang="ts">
import type { SettingsCharacter, SettingsUser } from "@/Types/Settings";
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	user: SettingsUser
	characters: SettingsCharacter[]
}>();

const { t } = useI18n();
const form = useForm({
	username: props.user.name ?? '',
});
const isSavingPrimaryCharacter = ref(false);
const selectedPrimaryCharacterId = ref<number | null>(
	props.characters.find((character) => character.is_primary)?.id ?? null,
);

const primaryCharacterOptions = computed(() => props.characters.map((character) => ({
	label: character.world ? `${character.name} · ${character.world}` : character.name,
	value: character.id,
})));

const canSavePrimaryCharacter = computed(() => {
	const currentPrimaryCharacterId = props.characters.find((character) => character.is_primary)?.id ?? null;

	return selectedPrimaryCharacterId.value !== null && selectedPrimaryCharacterId.value !== currentPrimaryCharacterId;
});

const submit = () => {
	form.post(route('settings.username'));
};

const savePrimaryCharacter = () => {
	if (selectedPrimaryCharacterId.value === null || isSavingPrimaryCharacter.value) {
		return;
	}

	isSavingPrimaryCharacter.value = true;

	router.post(route('characters.make-primary', selectedPrimaryCharacterId.value), {}, {
		preserveScroll: true,
		onFinish: () => {
			isSavingPrimaryCharacter.value = false;
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

		<form @submit.prevent="submit" class="w-full flex flex-col items-start gap-4">
			<UFormField class="w-full" :label="t('general.username')">
				<UInput
					v-model="form.username"
					:placeholder="t('general.username')"
					size="xl"
					class="w-full"
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

			<UFormField
				class="w-full"
				:label="t('settings.account.primary_character')"
				:description="t('settings.account.primary_character_description')"
			>
				<div class="flex w-full flex-col gap-3 sm:flex-row sm:items-end">
					<USelect
						v-model="selectedPrimaryCharacterId"
						class="w-full"
						value-key="value"
						:items="primaryCharacterOptions"
						:placeholder="t('settings.account.primary_character_placeholder')"
						:disabled="primaryCharacterOptions.length === 0"
					/>
					<UButton
						type="button"
						color="neutral"
						variant="outline"
						:label="t('settings.account.save_primary_character')"
						:disabled="!canSavePrimaryCharacter"
						:loading="isSavingPrimaryCharacter"
						@click="savePrimaryCharacter"
					/>
				</div>
			</UFormField>
		</form>
	</UCard>
</template>
