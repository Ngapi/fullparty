<script setup lang="ts">
import type { RunDiscoveryClassOption, RunDiscoveryClassRoleGroup, RunDiscoveryRoleCategory } from "../../Types/RunDiscovery";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	options: RunDiscoveryClassOption[]
	filterRole: RunDiscoveryRoleCategory
}>();

const isOpen = defineModel<boolean>("open", { required: true });
const selectedKeys = defineModel<string[]>("selectedKeys", { required: true });

const { t } = useI18n();

const roleGroups: Array<{ key: RunDiscoveryClassRoleGroup, labelKey: string }> = [
	{ key: "tank", labelKey: "runs.discovery.filters.class_picker.categories.tank" },
	{ key: "healer", labelKey: "runs.discovery.filters.class_picker.categories.healer" },
	{ key: "melee", labelKey: "runs.discovery.filters.class_picker.categories.melee" },
	{ key: "phys", labelKey: "runs.discovery.filters.class_picker.categories.phys" },
	{ key: "magic", labelKey: "runs.discovery.filters.class_picker.categories.magic" },
];

const visibleGroupKeys = computed<RunDiscoveryClassRoleGroup[]>(() => {
	if (props.filterRole === "tank") {
		return ["tank"];
	}

	if (props.filterRole === "healer") {
		return ["healer"];
	}

	if (props.filterRole === "dps") {
		return ["melee", "phys", "magic"];
	}

	return ["tank", "healer", "melee", "phys", "magic"];
});

const groupedOptions = computed(() => roleGroups
	.filter((group) => visibleGroupKeys.value.includes(group.key))
	.map((group) => ({
		key: group.key,
		label: t(group.labelKey),
		options: props.options.filter((option) => option.group === group.key),
	}))
	.filter((group) => group.options.length > 0));

const selectedOptions = computed(() => props.options.filter((option) => selectedKeys.value.includes(option.key)));

const toggleOption = (option: RunDiscoveryClassOption) => {
	selectedKeys.value = selectedKeys.value.includes(option.key)
		? selectedKeys.value.filter((key) => key !== option.key)
		: [...selectedKeys.value, option.key];
};

const isSelected = (option: RunDiscoveryClassOption) => selectedKeys.value.includes(option.key);

const clearSelection = () => {
	selectedKeys.value = [];
};
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
						<h3 class="text-lg font-semibold text-toned">
							{{ t("runs.discovery.filters.class_picker.title") }}
						</h3>
						<p class="text-sm text-muted">
							{{ t("runs.discovery.filters.class_picker.description") }}
						</p>
					</div>

					<UButton
						color="neutral"
						variant="ghost"
						icon="i-lucide-x"
						@click="isOpen = false"
					/>
				</div>

				<div
					v-if="selectedOptions.length > 0"
					class="flex flex-wrap gap-2"
				>
					<UBadge
						v-for="option in selectedOptions"
						:key="option.key"
						color="neutral"
						variant="soft"
						:label="option.shorthand"
					/>
				</div>

				<div class="flex flex-row flex-wrap gap-5">
					<section
						v-for="group in groupedOptions"
						:key="group.key"
						class="space-y-2"
					>
						<div class="flex items-center gap-3">
							<p class="text-sm font-medium text-toned">
								{{ group.label }}
							</p>
							<div class="h-px flex-1 bg-default" />
						</div>

						<div class="flex flex-row flex-wrap gap-2">
							<button
								v-for="option in group.options"
								:key="option.key"
								type="button"
								class="relative flex items-center justify-center rounded-lg border-2 transition-transform duration-150 ease-out hover:scale-105"
								:class="isSelected(option)
									? 'border-primary bg-primary/10 text-toned'
									: 'border-default bg-muted/10 text-muted hover:border-primary'"
								:title="option.label"
								@click="toggleOption(option)"
							>
								<img
									v-if="option.icon_url"
									:src="option.icon_url"
									:alt="option.label"
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

				<div class="flex justify-end gap-3">
					<UButton
						color="neutral"
						variant="outline"
						:label="t('runs.discovery.filters.class_picker.clear')"
						@click="clearSelection"
					/>
					<UButton
						color="neutral"
						variant="outline"
						:label="t('general.close')"
						@click="isOpen = false"
					/>
				</div>
			</div>
		</template>
	</UModal>
</template>
