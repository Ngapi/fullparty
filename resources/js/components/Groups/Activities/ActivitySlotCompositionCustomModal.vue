<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import type { ActivityCompositionClassOption, ActivitySlot, ActivitySlotCompositionHintInput } from "@/Types/ActivityRoster";

const props = defineProps<{
	slot: ActivitySlot | null
	classOptions: ActivityCompositionClassOption[]
	isSubmitting?: boolean
}>();

const emit = defineEmits<{
	save: [payload: { slotId: number, compositionHints: ActivitySlotCompositionHintInput[] }]
}>();

const isOpen = defineModel<boolean>("open", { required: true });

const { t } = useI18n();
const selectedClassKeys = ref<string[]>([]);

const roleGroups = [
	{ key: "tank", labelKey: "groups.activities.application.class_picker.categories.tank" },
	{ key: "healer", labelKey: "groups.activities.application.class_picker.categories.healer" },
	{ key: "melee dps", labelKey: "groups.activities.application.class_picker.categories.melee" },
	{ key: "physical ranged dps", labelKey: "groups.activities.application.class_picker.categories.phys" },
	{ key: "magic ranged dps", labelKey: "groups.activities.application.class_picker.categories.magic" },
];

const groupedOptions = computed(() => roleGroups
	.map((group) => ({
		key: group.key,
		label: t(group.labelKey),
		options: props.classOptions.filter((option) => option.role === group.key),
	}))
	.filter((group) => group.options.length > 0));

const selectedOptions = computed(() => props.classOptions.filter((option) => selectedClassKeys.value.includes(option.shorthand)));
const canSave = computed(() => Boolean(props.slot && selectedClassKeys.value.length > 0 && !props.isSubmitting));

const resetFromSlot = () => {
	selectedClassKeys.value = props.slot?.composition_hints
		.filter((hint) => hint.type === "class")
		.map((hint) => hint.key)
		.filter((key) => props.classOptions.some((option) => option.shorthand === key)) ?? [];
};

const toggleOption = (option: ActivityCompositionClassOption) => {
	if (props.isSubmitting) {
		return;
	}

	selectedClassKeys.value = selectedClassKeys.value.includes(option.shorthand)
		? selectedClassKeys.value.filter((key) => key !== option.shorthand)
		: [...selectedClassKeys.value, option.shorthand];
};

const isSelected = (option: ActivityCompositionClassOption) => selectedClassKeys.value.includes(option.shorthand);

const save = () => {
	if (!props.slot || !canSave.value) {
		return;
	}

	emit("save", {
		slotId: props.slot.id,
		compositionHints: selectedClassKeys.value.map((key) => ({
			type: "class",
			key,
		})),
	});
};

watch(() => [isOpen.value, props.slot?.id] as const, ([open]) => {
	if (open) {
		resetFromSlot();
	}
});
</script>

<template>
	<UModal
		v-model:open="isOpen"
		:ui="{ content: 'max-w-3xl' }"
	>
		<template #content>
			<div class="flex flex-col gap-5 p-4">
				<div class="flex items-start justify-between gap-4">
					<div class="space-y-1">
						<h3 class="font-semibold text-lg text-toned">
							{{ t('groups.activities.management.roster.composition_custom_title') }}
						</h3>
						<p class="text-sm text-muted">
							{{ t('groups.activities.management.roster.composition_custom_description') }}
						</p>
					</div>

					<UButton
						color="neutral"
						variant="ghost"
						icon="i-lucide-x"
						:disabled="isSubmitting"
						@click="isOpen = false"
					/>
				</div>

				<div
					v-if="selectedOptions.length > 0"
					class="flex flex-wrap gap-2"
				>
					<UBadge
						v-for="option in selectedOptions"
						:key="option.shorthand"
						color="neutral"
						variant="soft"
						:label="option.shorthand"
					/>
				</div>

				<div
					v-if="groupedOptions.length > 0"
					class="flex flex-row flex-wrap gap-5"
				>
					<section
						v-for="group in groupedOptions"
						:key="group.key"
						class="space-y-2"
					>
						<div class="flex items-center gap-3">
							<p class="font-medium text-sm text-toned">
								{{ group.label }}
							</p>
							<div class="h-px flex-1 bg-default" />
						</div>

						<div class="flex flex-row flex-wrap gap-2">
							<button
								v-for="option in group.options"
								:key="option.id"
								type="button"
								class="relative flex items-center justify-center rounded-lg border-2 transition-transform duration-150 ease-out hover:scale-105"
								:class="isSelected(option)
									? 'border-primary bg-primary/10 text-toned'
									: 'border-transparent bg-muted/10 text-muted hover:border-primary'"
								:disabled="isSubmitting"
								:title="`${option.name} (${option.shorthand})`"
								@click="toggleOption(option)"
							>
								<img
									v-if="option.icon_url"
									:src="option.icon_url"
									:alt="option.name"
									class="size-10 rounded-sm object-contain"
								>
								<div
									v-else
									class="flex size-10 items-center justify-center rounded-sm bg-muted text-xs font-semibold text-toned"
								>
									{{ option.shorthand }}
								</div>
							</button>
						</div>
					</section>
				</div>

				<p
					v-else
					class="rounded-sm border border-default bg-muted/30 px-4 py-3 text-sm text-muted"
				>
					{{ t('groups.activities.management.roster.composition_custom_no_options') }}
				</p>

				<div class="flex justify-end gap-3">
					<UButton
						color="neutral"
						variant="outline"
						:label="t('general.cancel')"
						:disabled="isSubmitting"
						@click="isOpen = false"
					/>
					<UButton
						color="primary"
						:label="t('groups.activities.management.roster.composition_custom_save')"
						:loading="isSubmitting"
						:disabled="!canSave"
						@click="save"
					/>
				</div>
			</div>
		</template>
	</UModal>
</template>
