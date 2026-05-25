<script setup lang="ts">
import type { GroupDashboardGroup } from "@/Types/Groups";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import GroupDiscoveryBadge from "@/components/Groups/GroupDiscoveryBadge.vue";

const props = defineProps<{
	group: GroupDashboardGroup
}>();

const { t } = useI18n();

const recruitingBadge = computed(() => props.group.badge_meta.recruiting_status ?? null);
const experienceBadge = computed(() => props.group.badge_meta.experience_expectation ?? null);
const voiceBadge = computed(() => props.group.badge_meta.voice_expectation ?? null);
const focusBadges = computed(() => props.group.badge_meta.primary_focuses ?? []);
const tagBadges = computed(() => props.group.badge_meta.tags ?? []);

const languageLabels: Record<string, string> = {
	en: "English",
	de: "Deutsch",
	fr: "Français",
	ja: "日本語",
};

const languagesText = computed(() => (props.group.preferred_languages ?? [])
	.map((value) => languageLabels[value] ?? value.toUpperCase())
	.join(", "));

const locationText = computed(() => {
	if (props.group.region) {
		return `${props.group.datacenter} · ${props.group.region}`;
	}

	return props.group.datacenter;
});

const activityWindowText = computed(() => {
	const activeDays = props.group.active_days ?? [];

	if (!props.group.active_timezone || activeDays.length === 0) {
		return null;
	}

	const days = activeDays
		.map((day) => t(`groups.index.create_modal.fields.active_days.options.${day}`))
		.join(", ");

	if (props.group.active_start_time && props.group.active_end_time) {
		return `${days} · ${props.group.active_start_time.slice(0, 5)} - ${props.group.active_end_time.slice(0, 5)} · ${props.group.active_timezone}`;
	}

	return `${days} · ${props.group.active_timezone}`;
});

const descriptionText = computed(() => props.group.description || t("groups.dashboard.hero.no_description"));

const primaryBadges = computed(() => [
	recruitingBadge.value,
	experienceBadge.value,
	voiceBadge.value,
	...focusBadges.value,
].filter((badge): badge is NonNullable<typeof badge> => badge !== null));

const primaryFocusValues = computed(() => new Set((props.group.primary_focuses ?? []).map((value) => String(value))));

const resolveBadgeLabel = (value: string) => {
	if (value === props.group.recruiting_status) {
		return t(`groups.index.create_modal.fields.recruiting_status.options.${value}`);
	}

	if (value === props.group.experience_expectation) {
		return t(`groups.index.create_modal.fields.experience_expectation.options.${value}`);
	}

	if (value === props.group.voice_expectation) {
		return t(`groups.index.create_modal.fields.voice_expectation.options.${value}`);
	}

	if (primaryFocusValues.value.has(value)) {
		return t(`groups.index.create_modal.fields.primary_focuses.options.${value}`);
	}

	return value;
};
</script>

<template>
	<div class="w-full max-w-[27rem] h-full border border-white/10 bg-neutral-950/92 p-5 shadow-[0_22px_70px_rgba(0,0,0,0.5)] backdrop-blur-md">
		<div class="flex items-start gap-4">
			<div class="shrink-0 overflow-hidden border border-white/10 bg-neutral-900/90">
				<img
					v-if="group.profile_picture_url"
					:src="group.profile_picture_url"
					:alt="group.name"
					class="size-20 object-cover sm:size-24"
				>
				<div
					v-else
					class="flex size-20 items-center justify-center sm:size-24"
				>
					<UIcon name="i-lucide-users-round" class="size-8 text-white/55 sm:size-10" />
				</div>
			</div>

			<div class="min-w-0 flex-1">
				<div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-white/74">
					<span class="inline-flex items-center gap-1.5">
						<UIcon name="i-lucide-map-pinned" class="size-4" />
						<span class="break-words [overflow-wrap:anywhere]">{{ locationText }}</span>
					</span>
					<span
						v-if="languagesText"
						class="inline-flex items-center gap-1.5"
					>
						<UIcon name="i-lucide-languages" class="size-4" />
						<span class="break-words [overflow-wrap:anywhere]">{{ languagesText }}</span>
					</span>
				</div>
			</div>
		</div>

		<p class="mt-5 text-sm leading-7 text-white/84 break-words [overflow-wrap:anywhere]">
			{{ descriptionText }}
		</p>

		<div v-if="primaryBadges.length > 0" class="mt-5 flex flex-wrap gap-2">
			<GroupDiscoveryBadge
				v-for="badge in primaryBadges"
				:key="badge.value"
				:color="badge.color"
				:label="resolveBadgeLabel(badge.value)"
			/>
		</div>

		<div v-if="tagBadges.length > 0" class="mt-3 flex flex-wrap gap-2">
			<GroupDiscoveryBadge
				v-for="tag in tagBadges"
				:key="tag.value"
				:color="tag.color"
				:label="tag.label"
			/>
		</div>

		<div class="mt-5 grid gap-3 border-t border-white/10 pt-4 text-sm text-white/74">
			<div class="flex items-start gap-2">
				<UIcon name="i-lucide-user-round" class="mt-0.5 size-4 shrink-0" />
				<div class="min-w-0">
					<p class="text-[11px] uppercase tracking-[0.18em] text-white/50">
						{{ t("groups.dashboard.hero.owner") }}
					</p>
					<p class="mt-1 text-white break-words [overflow-wrap:anywhere]">
						{{ group.owner.name || t("groups.dashboard.labels.not_available") }}
					</p>
				</div>
			</div>

			<div
				v-if="group.discord_invite_url"
				class="flex items-start gap-2"
			>
				<UIcon name="i-lucide-link-2" class="mt-0.5 size-4 shrink-0" />
				<div class="min-w-0">
					<p class="text-[11px] uppercase tracking-[0.18em] text-white/50">
						{{ t("groups.dashboard.hero.discord") }}
					</p>
					<p class="mt-1 truncate text-white">
						{{ group.discord_invite_url }}
					</p>
				</div>
			</div>

			<div
				v-if="activityWindowText"
				class="flex items-start gap-2"
			>
				<UIcon name="i-lucide-clock-3" class="mt-0.5 size-4 shrink-0" />
				<div class="min-w-0">
					<p class="text-[11px] uppercase tracking-[0.18em] text-white/50">
						{{ t("groups.dashboard.hero.activity_window") }}
					</p>
					<p class="mt-1 break-words [overflow-wrap:anywhere] text-white">
						{{ activityWindowText }}
					</p>
				</div>
			</div>
		</div>
	</div>
</template>
