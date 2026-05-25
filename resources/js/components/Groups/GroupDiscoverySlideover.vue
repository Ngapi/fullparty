<script setup lang="ts">
import type { GroupDiscoveryDetailRecord } from "@/Types/Groups";
import GroupDiscoveryBadge from "@/components/Groups/GroupDiscoveryBadge.vue";
import { formatRelativeTime } from "@/utils/formatRelativeTime";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	open: boolean
	group: GroupDiscoveryDetailRecord | null
	loading?: boolean
}>();

const emit = defineEmits<{
	"update:open": [value: boolean]
}>();

const { locale, t } = useI18n();

const openModel = computed({
	get: () => props.open,
	set: (value: boolean) => emit("update:open", value),
});

const experienceBadge = computed(() => props.group?.badge_meta.experience_expectation ?? null);
const recruitingBadge = computed(() => props.group?.badge_meta.recruiting_status ?? null);
const voiceBadge = computed(() => props.group?.badge_meta.voice_expectation ?? null);
const focusBadges = computed(() => props.group?.badge_meta.primary_focuses ?? []);
const tagBadges = computed(() => props.group?.badge_meta.tags ?? []);

const languagesText = computed(() => (props.group?.preferred_languages ?? [])
	.map((language) => resolveLanguageLabel(language))
	.join(", "));

const activeDaysText = computed(() => (props.group?.active_days ?? [])
	.map((day) => t(`groups.index.create_modal.fields.active_days.options.${day}`))
	.join(", "));

const activeHoursText = computed(() => {
	if (!props.group?.active_start_time || !props.group?.active_end_time) {
		return t("groups.index.discovery.detail.not_shared");
	}

	return `${formatTime(props.group.active_start_time)} - ${formatTime(props.group.active_end_time)}`;
});

const lastActivityText = computed(() => formatRelativeTime(
	props.group?.stats.last_activity_at ?? null,
	locale.value,
	t("notifications.ui.just_now"),
	t("groups.index.table.no_activity"),
));

const latestMemberJoinText = computed(() => formatRelativeTime(
	props.group?.stats.latest_member_join_at ?? null,
	locale.value,
	t("notifications.ui.just_now"),
	t("groups.index.discovery.detail.not_shared"),
));

const groupTypeLabel = computed(() => {
	if (!props.group?.group_type) {
		return t("groups.index.discovery.detail.not_shared");
	}

	return t(`groups.index.create_modal.fields.group_type.options.${props.group.group_type}`);
});

const descriptionText = computed(() => props.group?.description || t("groups.index.discovery.detail.description_fallback"));
const bannerUrl = computed(() => props.group?.banner_image_url ?? "/prereqimages/forked.jpg");

function resolveLanguageLabel(value: string) {
	const languageLabels: Record<string, string> = {
		en: "English",
		de: "Deutsch",
		fr: "Français",
		ja: "日本語",
	};

	return languageLabels[value] ?? value.toUpperCase();
}

function formatTime(value: string) {
	return value.slice(0, 5);
}

function booleanLabel(value: boolean | null | undefined) {
	return value ? t("groups.index.discovery.detail.yes") : t("groups.index.discovery.detail.no");
}
</script>

<template>
	<USlideover
		v-model:open="openModel"
		:overlay="false"
		side="right"
		:title="group?.name ?? t('groups.index.discovery.detail.title')"
		:description="loading && !group ? t('groups.index.discovery.detail.loading') : undefined"
		:ui="{ body: 'p-0 sm:p-0' }"
	>
		<template #body>
			<div class="flex h-full flex-col">
				<div v-if="loading && !group" class="space-y-4 p-4">
					<USkeleton class="h-40 w-full" />
					<div class="grid gap-4">
						<USkeleton class="h-24 w-full" />
						<USkeleton class="h-32 w-full" />
						<USkeleton class="h-28 w-full" />
					</div>
				</div>

				<div v-else-if="group" class="flex h-full flex-col">
					<div class="relative h-44 overflow-hidden border-b border-default bg-neutral-950">
						<img
							:src="bannerUrl"
							:alt="group.name"
							class="h-full w-full object-cover"
						>
						<div class="absolute inset-0 bg-linear-to-t from-neutral-950 via-neutral-950/72 to-neutral-950/22" />
						<div class="absolute inset-x-0 bottom-0 p-4">
							<div class="flex items-end gap-4">
								<div class="flex size-16 shrink-0 items-center justify-center overflow-hidden border border-white/10 bg-neutral-900">
									<img
										v-if="group.profile_picture_url"
										:src="group.profile_picture_url"
										:alt="group.name"
										class="h-full w-full object-cover"
									>
									<UIcon v-else name="i-lucide-users" class="size-7 text-white/65" />
								</div>

								<div class="min-w-0 flex-1">
									<p class="text-xs font-medium uppercase tracking-[0.18em] text-white/70">
										{{ groupTypeLabel }}
									</p>
									<h2 class="text-2xl font-semibold text-white break-words [overflow-wrap:anywhere]">
										{{ group.name }}
									</h2>
									<div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/78">
										<span class="inline-flex items-center gap-1">
											<UIcon name="i-lucide-server" class="size-4" />
											{{ group.datacenter || t('groups.index.discovery.detail.not_shared') }}
										</span>
										<span v-if="group.region" class="inline-flex items-center gap-1">
											<UIcon name="i-lucide-globe" class="size-4" />
											{{ group.region }}
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="flex-1 space-y-4 overflow-y-auto p-4">
						<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
							<div class="border border-default bg-elevated p-4">
								<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.owner') }}
								</p>
								<div class="mt-3">
									<UUser
										:name="group.owner.name ?? '—'"
										size="lg"
										:avatar="group.owner.avatar_url ? { src: group.owner.avatar_url } : undefined"
									/>
								</div>
							</div>

							<div class="border border-default bg-elevated p-4">
								<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.members') }}
								</p>
								<p class="mt-3 text-2xl font-semibold text-highlighted">
									{{ group.stats.member_count }}
								</p>
							</div>

							<div class="border border-default bg-elevated p-4">
								<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.recent_activity') }}
								</p>
								<p class="mt-3 text-base font-medium text-highlighted">
									{{ lastActivityText }}
								</p>
							</div>

							<div class="border border-default bg-elevated p-4">
								<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.runs_total') }}
								</p>
								<p class="mt-3 text-2xl font-semibold text-highlighted">
									{{ group.stats.run_count ?? 0 }}
								</p>
							</div>

							<div class="border border-default bg-elevated p-4">
								<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.runs_upcoming') }}
								</p>
								<p class="mt-3 text-2xl font-semibold text-highlighted">
									{{ group.stats.upcoming_run_count }}
								</p>
							</div>

							<div class="border border-default bg-elevated p-4">
								<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.latest_member') }}
								</p>
								<p class="mt-3 text-base font-medium text-highlighted">
									{{ latestMemberJoinText }}
								</p>
							</div>
						</div>

						<div class="border border-default bg-elevated p-4">
							<h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-dimmed">
								{{ t('groups.index.discovery.detail.about') }}
							</h3>
							<p class="mt-3 text-sm leading-6 text-toned break-words [overflow-wrap:anywhere]">
								{{ descriptionText }}
							</p>

							<div class="mt-4 grid gap-3 sm:grid-cols-2">
								<div>
									<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
										{{ t('groups.index.discovery.detail.discord') }}
									</p>
									<a
										v-if="group.discord_invite_url"
										:href="group.discord_invite_url"
										target="_blank"
										rel="noreferrer"
										class="mt-2 inline-flex items-center gap-2 text-sm text-primary hover:text-primary/80"
									>
										<UIcon name="i-lucide-external-link" class="size-4" />
										<span class="truncate">{{ group.discord_invite_url }}</span>
									</a>
									<p v-else class="mt-2 text-sm text-muted">
										{{ t('groups.index.discovery.detail.not_shared') }}
									</p>
								</div>

								<div>
									<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
										{{ t('groups.index.discovery.detail.languages') }}
									</p>
									<p class="mt-2 text-sm text-toned">
										{{ languagesText || t('groups.index.discovery.detail.not_shared') }}
									</p>
								</div>
							</div>
						</div>

						<div class="border border-default bg-elevated p-4">
							<h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-dimmed">
								{{ t('groups.index.discovery.detail.discovery_fit') }}
							</h3>

							<div class="mt-4 grid gap-4">
								<div class="space-y-2">
									<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
										{{ t('groups.index.discovery.detail.core_metadata') }}
									</p>
									<div class="flex flex-wrap gap-2">
										<GroupDiscoveryBadge
											v-if="recruitingBadge"
											:color="recruitingBadge.color"
											:label="t(`groups.index.create_modal.fields.recruiting_status.options.${recruitingBadge.value}`)"
										/>
										<GroupDiscoveryBadge
											v-if="experienceBadge"
											:color="experienceBadge.color"
											:label="t(`groups.index.create_modal.fields.experience_expectation.options.${experienceBadge.value}`)"
										/>
										<GroupDiscoveryBadge
											v-if="voiceBadge"
											:color="voiceBadge.color"
											:label="t(`groups.index.create_modal.fields.voice_expectation.options.${voiceBadge.value}`)"
										/>
										<GroupDiscoveryBadge
											v-for="focus in focusBadges"
											:key="focus.value"
											:color="focus.color"
											:label="t(`groups.index.create_modal.fields.primary_focuses.options.${focus.value}`)"
										/>
									</div>
								</div>

								<div class="space-y-2">
									<p class="text-xs font-semibold uppercase tracking-[0.14em] text-dimmed">
										{{ t('groups.index.discovery.detail.tags') }}
									</p>
									<div v-if="tagBadges.length > 0" class="flex flex-wrap gap-2">
										<GroupDiscoveryBadge
											v-for="tag in tagBadges"
											:key="tag.value"
											:color="tag.color"
											:label="tag.label"
										/>
									</div>
									<p v-else class="text-sm text-muted">
										{{ t('groups.index.discovery.detail.not_shared') }}
									</p>
								</div>
							</div>
						</div>

						<div class="grid gap-4 xl:grid-cols-2">
							<div class="border border-default bg-elevated p-4">
								<h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.schedule') }}
								</h3>
								<dl class="mt-4 space-y-3">
									<div class="flex items-start justify-between gap-4">
										<dt class="text-sm text-muted">
											{{ t('groups.index.discovery.detail.timezone') }}
										</dt>
										<dd class="text-right text-sm text-toned">
											{{ group.active_timezone || t('groups.index.discovery.detail.not_shared') }}
										</dd>
									</div>
									<div class="flex items-start justify-between gap-4">
										<dt class="text-sm text-muted">
											{{ t('groups.index.discovery.detail.active_days') }}
										</dt>
										<dd class="text-right text-sm text-toned">
											{{ activeDaysText || t('groups.index.discovery.detail.not_shared') }}
										</dd>
									</div>
									<div class="flex items-start justify-between gap-4">
										<dt class="text-sm text-muted">
											{{ t('groups.index.discovery.detail.active_hours') }}
										</dt>
										<dd class="text-right text-sm text-toned">
											{{ activeHoursText }}
										</dd>
									</div>
								</dl>
							</div>

							<div class="border border-default bg-elevated p-4">
								<h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-dimmed">
									{{ t('groups.index.discovery.detail.access') }}
								</h3>
								<dl class="mt-4 space-y-3">
									<div class="flex items-start justify-between gap-4">
										<dt class="text-sm text-muted">
											{{ t('groups.index.discovery.detail.type') }}
										</dt>
										<dd class="text-right text-sm text-toned">
											{{ groupTypeLabel }}
										</dd>
									</div>
									<div class="flex items-start justify-between gap-4">
										<dt class="text-sm text-muted">
											{{ t('groups.index.discovery.detail.region') }}
										</dt>
										<dd class="text-right text-sm text-toned">
											{{ group.region || t('groups.index.discovery.detail.not_shared') }}
										</dd>
									</div>
									<div class="flex items-start justify-between gap-4">
										<dt class="text-sm text-muted">
											{{ t('groups.index.discovery.detail.public_group') }}
										</dt>
										<dd class="text-right text-sm text-toned">
											{{ booleanLabel(group.is_public) }}
										</dd>
									</div>
									<div class="flex items-start justify-between gap-4">
										<dt class="text-sm text-muted">
											{{ t('groups.index.discovery.detail.visible_in_discovery') }}
										</dt>
										<dd class="text-right text-sm text-toned">
											{{ booleanLabel(group.is_visible) }}
										</dd>
									</div>
								</dl>
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>
	</USlideover>
</template>
