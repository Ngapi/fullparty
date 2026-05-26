<script setup lang="ts">
import type { GroupIndexRecord } from "@/Types/Groups";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import GroupDiscoveryBadge from "@/components/Groups/GroupDiscoveryBadge.vue";
import { formatRelativeTime } from "@/utils/formatRelativeTime";

const props = defineProps<{
	group: GroupIndexRecord
}>();
const emit = defineEmits<{
	openGroup: [group: GroupIndexRecord]
}>();

const { locale, t } = useI18n();

const languageLabelMap: Record<string, string> = {
	en: "English",
	de: "Deutsch",
	fr: "Français",
	ja: "日本語",
};

const experienceBadge = computed(() => props.group.badge_meta.experience_expectation);
const voiceBadge = computed(() => props.group.badge_meta.voice_expectation);
const focusBadges = computed(() => props.group.badge_meta.primary_focuses ?? []);
const extraTagBadges = computed(() => props.group.badge_meta.tags ?? []);
const languageText = computed(() => {
	if (props.group.preferred_languages.length === 0) {
		return null;
	}

	return props.group.preferred_languages
		.map((language) => languageLabelMap[language] ?? language.toUpperCase())
		.join(", ");
});
const memberCountText = computed(() => `${props.group.stats.member_count} ${t("general.members")}`);
const lastActivityText = computed(() => formatRelativeTime(
	props.group.stats.last_activity_at,
	locale.value,
	t("notifications.ui.just_now"),
	t("groups.index.table.no_activity"),
));
const bannerUrl = computed(() => props.group.banner_image_url ?? "/prereqimages/forked.jpg");
const ownerName = computed(() => props.group.owner.name ?? "—");
const descriptionText = computed(() => props.group.description || t("groups.index.table.no_description"));
const joinModeLabel = computed(() => t(`groups.common.join_modes.${props.group.join_mode}.label`));
const joinModeIcon = computed(() => {
	if (props.group.join_mode === "open") {
		return "i-lucide-door-open";
	}

	if (props.group.join_mode === "application") {
		return "i-lucide-file-check-2";
	}

	return "i-lucide-ticket";
});

function badgeLabel(value: string, group: "experience_expectation" | "voice_expectation" | "primary_focuses") {
	if (group === "voice_expectation") {
		return t(`groups.common.voice_expectations.${value}`);
	}

	return t(`groups.index.create_modal.fields.${group}.options.${value}`);
}

function openGroup() {
	emit("openGroup", props.group);
}
</script>

<template>
	<div
		class="border border-neutral-900 bg-neutral-900/50 hover:scale-101 transition-all cursor-pointer hover:border-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
		role="button"
		tabindex="0"
		@click="openGroup"
		@keydown.enter.prevent="openGroup"
		@keydown.space.prevent="openGroup"
	>
		<div class="flex flex-col lg:flex-row">
			<div class="h-32 w-full shrink-0 border-b border-neutral-900 lg:h-auto lg:w-56 lg:border-r lg:border-b-0">
				<img
					:src="bannerUrl"
					:alt="group.name"
					class="h-full w-full object-cover"
				>
			</div>

			<div class="flex min-w-0 flex-1 items-stretch">
				<div class="w-4/12 flex min-w-0 flex-col justify-evenly gap-3 px-4 py-4">
					<div class="flex min-w-0 flex-col gap-1">
						<h3 class="line-clamp-2 text-lg font-semibold text-highlighted break-words [overflow-wrap:anywhere]">
							{{ group.name }}
						</h3>
						<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-dimmed">
							<span class="inline-flex items-center gap-1">
								<UIcon name="i-lucide-server" class="size-3.5" />
								{{ group.datacenter }}
							</span>
							<span v-if="group.region" class="inline-flex items-center gap-1">
								<UIcon name="i-lucide-globe" class="size-3.5" />
								{{ group.region }}
							</span>
							<span class="inline-flex items-center gap-1">
								<UIcon :name="joinModeIcon" class="size-3.5" />
								{{ joinModeLabel }}
							</span>
						</div>
					</div>

					<div class="flex flex-wrap items-center gap-2">
						<GroupDiscoveryBadge
							v-if="experienceBadge"
							:color="experienceBadge.color"
							:label="badgeLabel(experienceBadge.value, 'experience_expectation')"
						/>
						<GroupDiscoveryBadge
							v-if="voiceBadge"
							:color="voiceBadge.color"
							:label="badgeLabel(voiceBadge.value, 'voice_expectation')"
						/>
						<GroupDiscoveryBadge
							v-for="focus in focusBadges"
							:key="focus.value"
							:color="focus.color"
							:label="badgeLabel(focus.value, 'primary_focuses')"
						/>
					</div>
				</div>

				<div class="hidden w-px bg-neutral-900 xl:block" />

				<div class="w-3/12 flex flex-col justify-between gap-1 p-4">
					<div class="flex flex-col">
						<h3 class="text-md font-semibold">
							About Us
						</h3>
						<p class="text-sm text-toned break-words [overflow-wrap:anywhere]">
							{{ descriptionText }}
						</p>
					</div>
					<div class="flex flex-row flex-wrap gap-2">
						<GroupDiscoveryBadge
							v-for="tag in extraTagBadges"
							:key="tag.value"
							:color="tag.color"
							:label="tag.label"
						/>
					</div>
				</div>

				<div class="hidden w-px bg-neutral-900 xl:block" />

				<div class="w-2/12 flex flex-col justify-between gap-1 p-4">
					<div class="flex flex-col">
						<p class="text-[11px] font-semibold text-dimmed">
							{{ t("groups.index.discovery.placeholder.owner_label") }}
						</p>
						<p class="truncate text-sm font-medium text-highlighted">
							<UUser
								:name="ownerName"
								size="xs"
								:avatar="group.owner.avatar_url ? { src: group.owner.avatar_url, size: 'xs' } : undefined"
							/>
						</p>
					</div>
					<div class="flex flex-row w-full items-center gap-2">
						<UIcon name="i-lucide-users" class="size-4 text-dimmed" />
						<p class="text-sm text-center">
							{{ memberCountText }}
						</p>
					</div>
				</div>

				<div class="hidden w-px bg-neutral-900 xl:block" />

				<div class="w-2/12 flex flex-col justify-between gap-1 p-4">
					<div class="flex flex-col">
						<p class="text-[11px] font-semibold text-dimmed">
							{{ t("groups.index.discovery.placeholder.activity_label") }}
						</p>
						<div class="flex flex-row w-full items-center gap-2">
							<UIcon name="i-lucide-trending-up" class="size-4 text-dimmed" />
							<p class="text-sm text-center">
								{{ lastActivityText }}
							</p>
						</div>
					</div>
					<div v-if="languageText" class="flex flex-row w-full items-center gap-2">
						<UIcon name="i-lucide-languages" class="size-4 text-dimmed" />
						<p class="text-sm text-center">
							{{ languageText }}
						</p>
					</div>
				</div>

				<div class="w-1/12 hidden shrink-0 items-center justify-center px-4 lg:flex">
					<UIcon name="i-lucide-chevron-right" class="size-5 text-dimmed" />
				</div>
			</div>
		</div>
	</div>
</template>
