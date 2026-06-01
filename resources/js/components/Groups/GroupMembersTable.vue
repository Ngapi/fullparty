<script setup lang="ts">
import type {
	GroupBannedMemberRecord,
	GroupBannedMembersTableModerationController,
	GroupMemberCharacter,
	GroupMemberNotesController,
	GroupMemberRecord,
	GroupMembersTableModerationController,
	GroupRole,
} from "@/Types/Groups";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import MemberNotesButton from "@/components/Shared/Notes/MemberNotesButton.vue";

const pageSize = 15;
const fallbackProfileBackground = "/default-homepage-bg.jpg";

type ViewMode = "list" | "grid";

type ActiveMemberCard = {
	kind: "active"
	id: string
	member: GroupMemberRecord
	displayName: string
	avatarUrl: string | null
	backgroundUrl: string | null
	characters: GroupMemberCharacter[]
	searchText: string
};

type BannedMemberCard = {
	kind: "banned"
	id: string
	member: GroupBannedMemberRecord
	displayName: string
	avatarUrl: string | null
	backgroundUrl: string | null
	characters: GroupMemberCharacter[]
	searchText: string
};

type MemberCard = ActiveMemberCard | BannedMemberCard;

const props = withDefaults(defineProps<{
	members: GroupMemberRecord[]
	bannedMembers?: GroupBannedMemberRecord[]
	canViewBans?: boolean
	notes: GroupMemberNotesController
	moderation: GroupMembersTableModerationController & GroupBannedMembersTableModerationController
}>(), {
	bannedMembers: () => [],
	canViewBans: false,
});

const { locale, t } = useI18n();

const search = ref("");
const viewMode = ref<ViewMode>("list");
const showBannedMembers = ref(false);
const visibleCount = ref(pageSize);
const loadMoreSentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const relativeTimeFormatter = computed(() => new Intl.RelativeTimeFormat(locale.value, {
	numeric: "auto",
	style: "long",
}));

const roleBadge = (role: string) => ({
	owner: {
		label: t("groups.common.roles.owner"),
		color: "warning",
		icon: "i-lucide-crown",
	},
	moderator: {
		label: t("groups.common.roles.moderator"),
		color: "primary",
		icon: "i-lucide-shield",
	},
	admin: {
		label: t("groups.common.roles.admin"),
		color: "secondary",
		icon: "i-lucide-shield-check",
	},
	member: {
		label: t("groups.common.roles.member"),
		color: "neutral",
		icon: "i-lucide-user",
	},
}[role] ?? {
	label: role,
	color: "neutral",
	icon: "i-lucide-user",
});

const bannedBadge = computed(() => ({
	label: t("groups.members.bans.badge"),
	color: "error",
	icon: "i-lucide-ban",
}));

const currentTitle = computed(() => showBannedMembers.value
	? t("groups.members.bans.title")
	: t("groups.members.roster.title"));
const currentSubtitle = computed(() => showBannedMembers.value
	? t("groups.members.bans.subtitle")
	: t("groups.members.roster.subtitle"));
const currentCountLabel = computed(() => showBannedMembers.value
	? t("groups.members.bans.count", { count: props.bannedMembers.length })
	: t("groups.members.roster.count", { count: props.members.length }));
const currentSearchPlaceholder = computed(() => showBannedMembers.value
	? t("groups.members.bans.search_placeholder")
	: t("groups.members.roster.search_placeholder"));
const emptyLabel = computed(() => showBannedMembers.value
	? t("groups.members.bans.empty")
	: t("groups.members.roster.empty"));

const formatRelativeTime = (value: string | null) => {
	if (!value) {
		return t("groups.members.roster.not_available");
	}

	const date = new Date(value);
	if (Number.isNaN(date.getTime())) {
		return t("groups.members.roster.not_available");
	}

	const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
	const units = [
		{ unit: "year", seconds: 60 * 60 * 24 * 365 },
		{ unit: "month", seconds: 60 * 60 * 24 * 30 },
		{ unit: "week", seconds: 60 * 60 * 24 * 7 },
		{ unit: "day", seconds: 60 * 60 * 24 },
		{ unit: "hour", seconds: 60 * 60 },
	] as const;

	for (const item of units) {
		if (Math.abs(diffSeconds) >= item.seconds) {
			return relativeTimeFormatter.value.format(Math.round(diffSeconds / item.seconds), item.unit);
		}
	}

	return relativeTimeFormatter.value.format(Math.round(diffSeconds / 60), "minute");
};

const joinedLabel = (member: GroupMemberRecord) => t("groups.members.roster.joined_at", {
	date: formatRelativeTime(member.joined_at),
});

const bannedAtLabel = (member: GroupBannedMemberRecord) => t("groups.members.bans.banned_at_relative", {
	date: formatRelativeTime(member.banned_at),
});

const characterSubtitle = (character: GroupMemberCharacter) => [character.datacenter, character.world]
	.filter(Boolean)
	.join(" - ") || character.world;

const summarizeCharacters = (characters: GroupMemberCharacter[]) => characters
	.map((character) => `${character.name} ${character.world} ${character.datacenter ?? ""} ${character.is_primary ? "primary" : ""}`.trim())
	.join(" ");

const activeCards = computed<ActiveMemberCard[]>(() => props.members.map((member) => ({
	kind: "active",
	id: `active-${member.id}`,
	member,
	displayName: member.name,
	avatarUrl: member.avatar_url,
	backgroundUrl: member.home_background_image_url ?? null,
	characters: member.characters,
	searchText: [
		member.name,
		roleBadge(member.role).label,
		String(member.participated_run_count),
		joinedLabel(member),
		summarizeCharacters(member.characters),
	].join(" ").toLowerCase(),
})));

const bannedCards = computed<BannedMemberCard[]>(() => props.bannedMembers.map((member) => ({
	kind: "banned",
	id: `banned-${member.id}`,
	member,
	displayName: member.name ?? t("groups.members.bans.unknown_member"),
	avatarUrl: member.avatar_url,
	backgroundUrl: member.home_background_image_url ?? null,
	characters: member.characters,
	searchText: [
		member.name ?? t("groups.members.bans.unknown_member"),
		member.reason ?? t("groups.members.bans.no_reason"),
		member.banned_by?.name ?? t("groups.members.bans.system"),
		bannedAtLabel(member),
		summarizeCharacters(member.characters),
	].join(" ").toLowerCase(),
})));

const currentCards = computed<MemberCard[]>(() => showBannedMembers.value ? bannedCards.value : activeCards.value);
const filteredCards = computed<MemberCard[]>(() => {
	const query = search.value.trim().toLowerCase();

	if (!query) {
		return currentCards.value;
	}

	return currentCards.value.filter((card) => card.searchText.includes(query));
});
const visibleCards = computed(() => filteredCards.value.slice(0, visibleCount.value));
const hasMore = computed(() => visibleCount.value < filteredCards.value.length);
const shouldShowNoResults = computed(() => filteredCards.value.length === 0);

const listViewActive = computed(() => viewMode.value === "list");
const gridViewActive = computed(() => viewMode.value === "grid");

const resetVisibleCards = () => {
	visibleCount.value = pageSize;
};

const loadMore = () => {
	if (!hasMore.value) {
		return;
	}

	visibleCount.value = Math.min(visibleCount.value + pageSize, filteredCards.value.length);
};

const observeLoadMoreSentinel = () => {
	observer?.disconnect();

	if (!observer || !loadMoreSentinel.value || !hasMore.value) {
		return;
	}

	observer.observe(loadMoreSentinel.value);
};

onMounted(() => {
	if (typeof window === "undefined" || !("IntersectionObserver" in window)) {
		return;
	}

	observer = new IntersectionObserver((entries) => {
		if (entries.some((entry) => entry.isIntersecting)) {
			loadMore();
		}
	}, {
		rootMargin: "240px",
	});

	nextTick(observeLoadMoreSentinel);
});

onBeforeUnmount(() => {
	observer?.disconnect();
});

watch([search, showBannedMembers], resetVisibleCards);
watch([hasMore, visibleCards], async () => {
	await nextTick();
	observeLoadMoreSentinel();
});

const nextPromotedRole = (role: GroupRole) => {
	if (role === "member") {
		return "moderator";
	}

	if (role === "moderator") {
		return "admin";
	}

	return "admin";
};

const nextDemotedRole = (role: GroupRole) => {
	if (role === "admin") {
		return "moderator";
	}

	return "member";
};

const canShowActiveActions = (member: GroupMemberRecord) => member.note_summary.can_view
	|| member.permissions.can_promote
	|| member.permissions.can_demote
	|| member.permissions.can_kick
	|| member.permissions.can_ban;

const canShowBannedActions = (member: GroupBannedMemberRecord) => Boolean(member.user_id)
	&& (member.note_summary.can_view || member.permissions.can_unban);
</script>

<template>
	<UCard class="w-full dark:bg-elevated/25">
		<template #header>
			<div class="flex flex-col gap-4">
				<div class="flex flex-col items-start gap-3 lg:flex-row lg:items-center lg:justify-between">
					<div class="flex flex-col gap-1">
						<p class="font-semibold text-md">{{ currentTitle }}</p>
						<p class="text-sm text-muted">{{ currentSubtitle }}</p>
					</div>

					<UBadge :label="currentCountLabel" color="neutral" variant="subtle" />
				</div>

				<div class="flex flex-col gap-2 xl:flex-row xl:items-center xl:justify-between">
					<UInput
						v-model="search"
						class="w-full xl:max-w-md"
						icon="i-lucide-search"
						:placeholder="currentSearchPlaceholder"
					/>

					<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
						<div class="flex w-full gap-1 sm:w-auto">
							<UButton
								class="flex-1 sm:flex-none"
								:color="listViewActive ? 'primary' : 'neutral'"
								:variant="listViewActive ? 'solid' : 'subtle'"
								icon="i-lucide-list"
								:label="t('groups.members.view.list')"
								@click="viewMode = 'list'"
							/>
							<UButton
								class="flex-1 sm:flex-none"
								:color="gridViewActive ? 'primary' : 'neutral'"
								:variant="gridViewActive ? 'solid' : 'subtle'"
								icon="i-lucide-layout-grid"
								:label="t('groups.members.view.blocks')"
								@click="viewMode = 'grid'"
							/>
						</div>

						<UButton
							v-if="props.canViewBans"
							color="neutral"
							variant="subtle"
							:icon="showBannedMembers ? 'i-lucide-users' : 'i-lucide-ban'"
							:label="showBannedMembers ? t('groups.members.view.members') : t('groups.members.view.banned')"
							@click="showBannedMembers = !showBannedMembers"
						/>
					</div>
				</div>
			</div>
		</template>

		<div class="space-y-4">
			<div v-if="shouldShowNoResults" class="flex min-h-52 flex-col items-center justify-center gap-3 border border-dashed border-default bg-muted/10 px-6 py-12 text-center">
				<UIcon :name="showBannedMembers ? 'i-lucide-ban' : 'i-lucide-users'" class="size-8 text-muted" />
				<p class="font-medium text-toned">{{ emptyLabel }}</p>
				<p v-if="search" class="max-w-md text-sm text-muted">
					{{ t('groups.members.view.no_search_results') }}
				</p>
			</div>

			<div v-else-if="listViewActive" class="space-y-3">
				<div
					v-for="card in visibleCards"
					:key="card.id"
					class="flex flex-col gap-4 border border-default bg-default/60 p-4 shadow-sm transition hover:border-primary/35 hover:bg-elevated/60 lg:flex-row lg:items-center"
				>
					<div class="flex items-start gap-4 lg:w-[24rem] lg:shrink-0">
						<div v-if="card.avatarUrl" class="h-16 w-16 shrink-0 overflow-hidden rounded-full border border-default bg-muted/30">
							<img
								:src="card.avatarUrl"
								:alt="`${card.displayName} avatar`"
								class="h-full w-full object-cover"
								loading="lazy"
							>
						</div>
						<div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-default bg-muted/20">
							<UIcon name="i-lucide-user" size="22" class="text-muted" />
						</div>

						<div class="min-w-0 space-y-2">
							<div class="flex flex-wrap items-center gap-2">
								<p class="break-words text-base font-semibold leading-tight text-toned [overflow-wrap:anywhere]">{{ card.displayName }}</p>

								<template v-if="card.kind === 'active'">
									<UBadge
										:label="roleBadge(card.member.role).label"
										:color="roleBadge(card.member.role).color"
										:icon="roleBadge(card.member.role).icon"
										variant="subtle"
										size="sm"
									/>
									<UBadge
										:label="t('groups.members.roster.runs_badge', { count: card.member.participated_run_count })"
										color="neutral"
										variant="subtle"
										icon="i-lucide-swords"
										size="sm"
									/>
								</template>
								<UBadge
									v-else
									:label="bannedBadge.label"
									:color="bannedBadge.color"
									:icon="bannedBadge.icon"
									variant="subtle"
									size="sm"
								/>
							</div>

							<p v-if="card.kind === 'active'" class="text-sm text-muted">
								{{ joinedLabel(card.member) }}
							</p>
							<div v-else class="space-y-1 text-sm text-muted">
								<p>{{ bannedAtLabel(card.member) }}</p>
								<p>
									{{ t('groups.members.bans.reason_inline', { reason: card.member.reason || t('groups.members.bans.no_reason') }) }}
								</p>
							</div>
						</div>
					</div>

					<div class="min-w-0 flex-1">
						<div v-if="card.characters.length > 0" class="flex flex-wrap gap-2">
							<div
								v-for="character in card.characters"
								:key="character.id"
								class="inline-flex max-w-full items-center gap-2 rounded-sm border border-default bg-muted/20 px-2.5 py-2"
								:title="`${character.name} - ${character.world}`"
							>
								<div v-if="character.avatar_url" class="h-8 w-8 shrink-0 overflow-hidden rounded-sm border border-default bg-muted/30">
									<img
										:src="character.avatar_url"
										:alt="`${character.name} avatar`"
										class="h-full w-full object-cover"
										loading="lazy"
									>
								</div>
								<div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-sm border border-default bg-muted/20">
									<UIcon name="i-lucide-user-round" size="13" class="text-muted" />
								</div>

								<div class="min-w-0">
									<p class="truncate text-sm font-medium text-toned">{{ character.name }}</p>
									<p class="truncate text-xs leading-tight text-muted">{{ characterSubtitle(character) }}</p>
								</div>

								<UIcon
									v-if="character.is_primary"
									name="i-lucide-star"
									class="size-3.5 shrink-0 text-warning"
								/>
							</div>
						</div>

						<p v-else class="text-sm text-muted">
							{{ t('groups.members.roster.no_characters') }}
						</p>
					</div>

					<div class="flex flex-wrap gap-2 lg:w-72 lg:shrink-0 lg:justify-end">
						<template v-if="card.kind === 'active'">
							<div v-if="canShowActiveActions(card.member)" class="flex w-full flex-col gap-2 sm:w-auto sm:items-end">
								<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
									<MemberNotesButton
										:user-id="card.member.id"
										:note-summary="card.member.note_summary"
										size="sm"
										@open="props.notes.openMemberNotes"
									/>
									<UButton
										v-if="card.member.permissions.can_promote"
										color="primary"
										variant="subtle"
										icon="i-lucide-arrow-up"
										size="sm"
										:label="t('groups.members.actions.promote')"
										:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === card.member.id"
										@click="props.moderation.updateMemberRole(card.member, nextPromotedRole(card.member.role))"
									/>
									<UButton
										v-if="card.member.permissions.can_demote"
										color="neutral"
										variant="subtle"
										icon="i-lucide-arrow-down"
										size="sm"
										:label="t('groups.members.actions.demote')"
										:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === card.member.id"
										@click="props.moderation.updateMemberRole(card.member, nextDemotedRole(card.member.role))"
									/>
								</div>

								<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
									<UButton
										v-if="card.member.permissions.can_kick"
										color="error"
										variant="ghost"
										icon="i-lucide-user-round-x"
										size="sm"
										:label="t('groups.members.actions.kick')"
										:loading="props.moderation.removeForm.processing && props.moderation.memberPendingRemovalId === card.member.id"
										@click="props.moderation.openKickConfirmation(card.member)"
									/>
									<UButton
										v-if="card.member.permissions.can_ban"
										color="error"
										variant="subtle"
										icon="i-lucide-ban"
										size="sm"
										:label="t('groups.members.actions.ban')"
										:loading="props.moderation.banForm.processing && props.moderation.memberPendingBanId === card.member.id"
										@click="props.moderation.openBanConfirmation(card.member)"
									/>
								</div>
							</div>

							<span v-else class="text-sm text-muted">-</span>
						</template>

						<template v-else>
							<div v-if="canShowBannedActions(card.member)" class="flex flex-wrap gap-2 lg:justify-end">
								<MemberNotesButton
									v-if="card.member.user_id"
									:user-id="card.member.user_id"
									:note-summary="card.member.note_summary"
									size="sm"
									@open="props.notes.openMemberNotes"
								/>
								<UButton
									v-if="card.member.permissions.can_unban && card.member.user_id"
									color="success"
									variant="subtle"
									icon="i-lucide-undo-2"
									size="sm"
									:label="t('groups.members.actions.unban')"
									:loading="props.moderation.unbanForm.processing && props.moderation.memberPendingUnbanId === card.member.user_id"
									@click="props.moderation.unbanMember(card.member)"
								/>
							</div>

							<span v-else class="text-sm text-muted">-</span>
						</template>
					</div>
				</div>
			</div>

			<div v-else class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
				<div
					v-for="card in visibleCards"
					:key="card.id"
					class="overflow-hidden border border-default bg-default/60 shadow-sm transition hover:border-primary/35 hover:bg-elevated/60"
				>
					<div
						class="h-24 bg-cover bg-center bg-muted/30"
						:style="{ backgroundImage: `url('${card.backgroundUrl || fallbackProfileBackground}')` }"
					/>

					<div class="px-4 pb-4">
						<div class="-mt-10 flex justify-center">
							<div v-if="card.avatarUrl" class="h-20 w-20 overflow-hidden rounded-full border-4 border-default bg-muted/30 shadow-sm">
								<img
									:src="card.avatarUrl"
									:alt="`${card.displayName} avatar`"
									class="h-full w-full object-cover"
									loading="lazy"
								>
							</div>
							<div v-else class="flex h-20 w-20 items-center justify-center rounded-full border-4 border-default bg-muted/20 shadow-sm">
								<UIcon name="i-lucide-user" size="26" class="text-muted" />
							</div>
						</div>

						<div class="mt-3 space-y-3 text-center">
							<p class="break-words text-lg font-semibold leading-tight text-toned [overflow-wrap:anywhere]">{{ card.displayName }}</p>

							<div class="flex flex-wrap items-center justify-center gap-2">
								<template v-if="card.kind === 'active'">
									<UBadge
										:label="roleBadge(card.member.role).label"
										:color="roleBadge(card.member.role).color"
										:icon="roleBadge(card.member.role).icon"
										variant="subtle"
										size="sm"
									/>
									<UBadge
										:label="t('groups.members.roster.runs_badge', { count: card.member.participated_run_count })"
										color="neutral"
										variant="subtle"
										icon="i-lucide-swords"
										size="sm"
									/>
									<UBadge
										:label="joinedLabel(card.member)"
										color="neutral"
										variant="outline"
										icon="i-lucide-calendar-days"
										size="sm"
									/>
								</template>
								<template v-else>
									<UBadge
										:label="bannedBadge.label"
										:color="bannedBadge.color"
										:icon="bannedBadge.icon"
										variant="subtle"
										size="sm"
									/>
									<UBadge
										:label="bannedAtLabel(card.member)"
										color="neutral"
										variant="outline"
										icon="i-lucide-calendar-x"
										size="sm"
									/>
								</template>
							</div>
						</div>

						<div class="mt-4">
							<div v-if="card.characters.length > 0" class="flex flex-wrap justify-center gap-2">
								<div
									v-for="character in card.characters"
									:key="character.id"
									class="inline-flex max-w-full items-center gap-2 rounded-sm border border-default bg-muted/20 px-2.5 py-2 text-left"
									:title="`${character.name} - ${character.world}`"
								>
									<div v-if="character.avatar_url" class="h-8 w-8 shrink-0 overflow-hidden rounded-sm border border-default bg-muted/30">
										<img
											:src="character.avatar_url"
											:alt="`${character.name} avatar`"
											class="h-full w-full object-cover"
											loading="lazy"
										>
									</div>
									<div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-sm border border-default bg-muted/20">
										<UIcon name="i-lucide-user-round" size="13" class="text-muted" />
									</div>

									<div class="min-w-0">
										<p class="truncate text-sm font-medium text-toned">{{ character.name }}</p>
										<p class="truncate text-xs leading-tight text-muted">{{ characterSubtitle(character) }}</p>
									</div>

									<UIcon
										v-if="character.is_primary"
										name="i-lucide-star"
										class="size-3.5 shrink-0 text-warning"
									/>
								</div>
							</div>

							<p v-else class="text-sm text-muted">
								{{ t('groups.members.roster.no_characters') }}
							</p>
						</div>

						<div class="mt-4 border-t border-default pt-4">
							<template v-if="card.kind === 'active'">
								<div v-if="canShowActiveActions(card.member)" class="flex flex-col items-center gap-2">
									<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-center">
										<MemberNotesButton
											:user-id="card.member.id"
											:note-summary="card.member.note_summary"
											size="sm"
											@open="props.notes.openMemberNotes"
										/>
										<UButton
											v-if="card.member.permissions.can_promote"
											color="primary"
											variant="subtle"
											icon="i-lucide-arrow-up"
											size="sm"
											:label="t('groups.members.actions.promote')"
											:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === card.member.id"
											@click="props.moderation.updateMemberRole(card.member, nextPromotedRole(card.member.role))"
										/>
										<UButton
											v-if="card.member.permissions.can_demote"
											color="neutral"
											variant="subtle"
											icon="i-lucide-arrow-down"
											size="sm"
											:label="t('groups.members.actions.demote')"
											:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === card.member.id"
											@click="props.moderation.updateMemberRole(card.member, nextDemotedRole(card.member.role))"
										/>
									</div>

									<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-center">
										<UButton
											v-if="card.member.permissions.can_kick"
											color="error"
											variant="ghost"
											icon="i-lucide-user-round-x"
											size="sm"
											:label="t('groups.members.actions.kick')"
											:loading="props.moderation.removeForm.processing && props.moderation.memberPendingRemovalId === card.member.id"
											@click="props.moderation.openKickConfirmation(card.member)"
										/>
										<UButton
											v-if="card.member.permissions.can_ban"
											color="error"
											variant="subtle"
											icon="i-lucide-ban"
											size="sm"
											:label="t('groups.members.actions.ban')"
											:loading="props.moderation.banForm.processing && props.moderation.memberPendingBanId === card.member.id"
											@click="props.moderation.openBanConfirmation(card.member)"
										/>
									</div>
								</div>

								<p v-else class="text-center text-sm text-muted">-</p>
							</template>

							<template v-else>
								<div v-if="canShowBannedActions(card.member)" class="flex flex-wrap justify-center gap-2">
									<MemberNotesButton
										v-if="card.member.user_id"
										:user-id="card.member.user_id"
										:note-summary="card.member.note_summary"
										size="sm"
										@open="props.notes.openMemberNotes"
									/>
									<UButton
										v-if="card.member.permissions.can_unban && card.member.user_id"
										color="success"
										variant="subtle"
										icon="i-lucide-undo-2"
										size="sm"
										:label="t('groups.members.actions.unban')"
										:loading="props.moderation.unbanForm.processing && props.moderation.memberPendingUnbanId === card.member.user_id"
										@click="props.moderation.unbanMember(card.member)"
									/>
								</div>

								<p v-else class="text-center text-sm text-muted">-</p>
							</template>
						</div>
					</div>
				</div>
			</div>

			<div v-if="hasMore" ref="loadMoreSentinel" class="flex justify-center pt-2">
				<UButton
					color="neutral"
					variant="ghost"
					icon="i-lucide-chevrons-down"
					:label="t('groups.members.view.load_more')"
					@click="loadMore"
				/>
			</div>
		</div>
	</UCard>
</template>
