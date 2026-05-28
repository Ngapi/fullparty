<script setup lang="ts">
import type {
	GroupMemberCharacter,
	GroupMemberNotesController,
	GroupMemberRecord,
	GroupMembersTableModerationController,
	GroupMemberTableRow,
} from "@/Types/Groups";
import { getPaginationRowModel } from "@tanstack/vue-table";
import { computed, ref, useTemplateRef, watch } from "vue";
import { useI18n } from "vue-i18n";
import MemberNotesButton from "@/components/Shared/Notes/MemberNotesButton.vue";
import type { GroupRole } from "@/Types/Groups";
import { createDateTimeFormatter } from "@/utils/dateTimeFormat";

const props = defineProps<{
	members: GroupMemberRecord[]
	notes: GroupMemberNotesController
	moderation: GroupMembersTableModerationController
}>();

const { t } = useI18n();
const memberTable = useTemplateRef('memberTable');
const responsiveMemberTable = useTemplateRef('responsiveMemberTable');

const memberPagination = ref({
	pageIndex: 0,
	pageSize: 8,
});
const memberGlobalFilter = ref('');
const memberExpanded = ref<Record<string, boolean>>({});

const memberCountLabel = computed(() => t('groups.members.roster.count', { count: props.members.length }));
const notAvailableLabel = computed(() => t('groups.members.roster.not_available'));

const roleBadge = (role: string) => ({
	owner: {
		label: t('groups.common.roles.owner'),
		color: 'warning',
		icon: 'i-lucide-crown',
	},
	moderator: {
		label: t('groups.common.roles.moderator'),
		color: 'primary',
		icon: 'i-lucide-shield',
	},
	admin: {
		label: t('groups.common.roles.admin'),
		color: 'secondary',
		icon: 'i-lucide-shield-check',
	},
	member: {
		label: t('groups.common.roles.member'),
		color: 'neutral',
		icon: 'i-lucide-user',
	},
}[role] ?? {
	label: role,
	color: 'neutral',
	icon: 'i-lucide-user',
});

const formatShortDate = (value: string | null) => {
	if (!value) {
		return notAvailableLabel.value;
	}

	return createDateTimeFormatter(undefined, {
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
	}).format(new Date(value));
};

const summarizeCharacters = (characters: GroupMemberCharacter[]) => {
	return characters
		.map((character) => `${character.name} ${character.world} ${character.is_primary ? 'primary' : ''}`.trim())
		.join(' ');
};

const summarizeMember = (member: GroupMemberRecord) => [
	member.name,
	roleBadge(member.role).label,
	formatShortDate(member.joined_at),
	String(member.participated_run_count),
].join(' ');

const memberTableData = computed<GroupMemberTableRow[]>(() => props.members.map((member) => ({
	...member,
	member_summary: summarizeMember(member),
	character_summary: summarizeCharacters(member.characters),
})));

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

const memberColumns = computed(() => [
	{ accessorKey: 'name', header: t('groups.members.table.columns.member') },
	{ accessorKey: 'role', header: t('general.role') },
	{ accessorKey: 'joined_at', header: t('groups.members.table.columns.joined_at') },
	{ accessorKey: 'participated_run_count', header: t('groups.members.table.columns.runs_participated') },
	{ accessorKey: 'character_summary', header: t('groups.members.table.columns.characters') },
	{ id: 'actions', header: t('general.actions') },
]);

const responsiveMemberColumns = computed(() => [
	{ accessorKey: 'member_summary', header: t('groups.members.table.columns.member') },
	{
		accessorKey: 'character_summary',
		header: t('groups.members.table.columns.characters'),
		meta: {
			class: {
				th: 'hidden md:table-cell',
				td: 'hidden md:table-cell',
			},
		},
	},
]);

const activeMemberTable = computed(() => memberTable.value ?? responsiveMemberTable.value);
const filteredRowCount = computed(() => activeMemberTable.value?.tableApi?.getFilteredRowModel().rows.length ?? 0);
const shouldFixTableHeight = computed(() => filteredRowCount.value > memberPagination.value.pageSize);

watch(memberGlobalFilter, () => {
	memberPagination.value.pageIndex = 0;
	memberExpanded.value = {};
});

watch(() => memberPagination.value.pageIndex, () => {
	memberExpanded.value = {};
});

const toggleResponsiveRow = (_event: Event, row: any) => {
	row.toggleExpanded();
};
</script>

<template>
	<UCard class="w-full dark:bg-elevated/25">
		<template #header>
			<div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
				<div class="flex flex-col gap-1">
					<p class="font-semibold text-md">{{ t('groups.members.roster.title') }}</p>
					<p class="text-sm text-muted">{{ t('groups.members.roster.subtitle') }}</p>
				</div>
				<div class="flex w-full items-center gap-2 sm:w-auto">
					<UInput
						v-model="memberGlobalFilter"
						class="min-w-0 flex-1 sm:w-72 sm:flex-none"
						icon="i-lucide-search"
						:placeholder="t('groups.members.roster.search_placeholder')"
					/>
					<UBadge :label="memberCountLabel" color="neutral" variant="subtle" />
				</div>
			</div>
		</template>

		<div class="flex flex-col gap-4">
			<div
				class="xl:hidden"
				:class="shouldFixTableHeight ? 'h-[34rem] overflow-auto' : 'overflow-auto'"
			>
				<UTable
					ref="responsiveMemberTable"
					v-model:pagination="memberPagination"
					v-model:global-filter="memberGlobalFilter"
					v-model:expanded="memberExpanded"
					:data="memberTableData"
					:columns="responsiveMemberColumns"
					:pagination-options="{ getPaginationRowModel: getPaginationRowModel() }"
					:on-select="toggleResponsiveRow"
					class="w-full [&_td]:py-3.5 [&_th]:py-2 md:[&_td]:py-3"
				>
					<template #member_summary-cell="{ row }">
						<div class="flex min-w-56 items-center gap-2.5 md:min-w-72">
							<div v-if="row.original.avatar_url" class="h-9 w-9 shrink-0 overflow-hidden rounded-sm border border-default bg-muted/30">
								<img
									:src="row.original.avatar_url"
									:alt="`${row.original.name} avatar`"
									class="h-full w-full object-cover"
								>
							</div>
							<div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-sm border border-default bg-muted/20">
								<UIcon name="i-lucide-user" size="16" class="text-muted" />
							</div>

							<div class="min-w-0 space-y-1">
								<div class="flex flex-wrap items-center gap-2">
									<p class="break-words font-semibold leading-tight text-toned [overflow-wrap:anywhere]">{{ row.original.name }}</p>
									<UBadge
										:label="roleBadge(row.original.role).label"
										:color="roleBadge(row.original.role).color"
										:icon="roleBadge(row.original.role).icon"
										variant="subtle"
										size="xs"
									/>
								</div>

								<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
									<span class="inline-flex items-center gap-1">
										<UIcon name="i-lucide-calendar-days" class="size-3" />
										{{ t('groups.members.table.columns.joined_at') }}: {{ formatShortDate(row.original.joined_at) }}
									</span>
									<span class="inline-flex items-center gap-1">
										<UIcon name="i-lucide-swords" class="size-3" />
										{{ t('groups.members.table.columns.runs_participated') }}: {{ row.original.participated_run_count }}
									</span>
								</div>
							</div>

							<UIcon
								:name="row.getIsExpanded() ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
								class="ml-auto size-4 shrink-0 text-muted"
							/>
						</div>
					</template>

					<template #character_summary-cell="{ row }">
						<div v-if="row.original.characters.length > 0" class="flex min-w-56 flex-wrap gap-1.5">
							<div
								v-for="character in row.original.characters"
								:key="character.id"
								class="inline-flex max-w-48 items-center gap-2 rounded-sm border border-default bg-muted/20 px-2 py-1.5"
								:title="`${character.name} - ${character.world}`"
							>
								<div v-if="character.avatar_url" class="h-6 w-6 shrink-0 overflow-hidden rounded-sm border border-default bg-muted/30">
									<img
										:src="character.avatar_url"
										:alt="`${character.name} avatar`"
										class="h-full w-full object-cover"
										loading="lazy"
									>
								</div>
								<div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-sm border border-default bg-muted/20">
									<UIcon name="i-lucide-user-round" size="12" class="text-muted" />
								</div>

								<div class="min-w-0">
									<p class="truncate text-xs font-medium text-toned">{{ character.name }}</p>
									<p class="truncate text-[11px] leading-tight text-muted">{{ character.world }}</p>
								</div>

								<UIcon
									v-if="character.is_primary"
									name="i-lucide-star"
									class="size-3 shrink-0 text-warning"
								/>
							</div>
						</div>

						<p v-else class="text-sm text-muted">
							{{ t('groups.members.roster.no_characters') }}
						</p>
					</template>

					<template #expanded="{ row }">
						<div class="space-y-4 bg-muted/10 px-1 py-2">
							<div class="space-y-2 md:hidden">
								<p class="text-xs font-semibold uppercase tracking-wide text-muted">
									{{ t('groups.members.table.columns.characters') }}
								</p>
								<div v-if="row.original.characters.length > 0" class="flex flex-wrap gap-1.5">
									<div
										v-for="character in row.original.characters"
										:key="character.id"
										class="inline-flex max-w-full items-center gap-2 rounded-sm border border-default bg-muted/20 px-2 py-1.5"
										:title="`${character.name} - ${character.world}`"
									>
										<div v-if="character.avatar_url" class="h-6 w-6 shrink-0 overflow-hidden rounded-sm border border-default bg-muted/30">
											<img
												:src="character.avatar_url"
												:alt="`${character.name} avatar`"
												class="h-full w-full object-cover"
												loading="lazy"
											>
										</div>
										<div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-sm border border-default bg-muted/20">
											<UIcon name="i-lucide-user-round" size="12" class="text-muted" />
										</div>

										<div class="min-w-0">
											<p class="truncate text-xs font-medium text-toned">{{ character.name }}</p>
											<p class="truncate text-[11px] leading-tight text-muted">{{ character.world }}</p>
										</div>

										<UIcon
											v-if="character.is_primary"
											name="i-lucide-star"
											class="size-3 shrink-0 text-warning"
										/>
									</div>
								</div>

								<p v-else class="text-sm text-muted">
									{{ t('groups.members.roster.no_characters') }}
								</p>
							</div>

							<div class="space-y-2">
								<p class="text-xs font-semibold uppercase tracking-wide text-muted">
									{{ t('general.actions') }}
								</p>
								<div v-if="row.original.note_summary.can_view || row.original.permissions.can_promote || row.original.permissions.can_demote || row.original.permissions.can_kick || row.original.permissions.can_ban" class="flex flex-wrap items-center gap-2">
									<MemberNotesButton
										:user-id="row.original.id"
										:note-summary="row.original.note_summary"
										size="sm"
										@open="props.notes.openMemberNotes"
									/>
									<UButton
										v-if="row.original.permissions.can_promote"
										color="primary"
										variant="subtle"
										icon="i-lucide-arrow-up"
										size="sm"
										:label="t('groups.members.actions.promote')"
										:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === row.original.id"
										@click="props.moderation.updateMemberRole(row.original, nextPromotedRole(row.original.role))"
									/>
									<UButton
										v-if="row.original.permissions.can_demote"
										color="neutral"
										variant="subtle"
										icon="i-lucide-arrow-down"
										size="sm"
										:label="t('groups.members.actions.demote')"
										:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === row.original.id"
										@click="props.moderation.updateMemberRole(row.original, nextDemotedRole(row.original.role))"
									/>
									<UButton
										v-if="row.original.permissions.can_kick"
										color="error"
										variant="ghost"
										icon="i-lucide-user-round-x"
										size="sm"
										:label="t('groups.members.actions.kick')"
										:loading="props.moderation.removeForm.processing && props.moderation.memberPendingRemovalId === row.original.id"
										@click="props.moderation.openKickConfirmation(row.original)"
									/>
									<UButton
										v-if="row.original.permissions.can_ban"
										color="error"
										variant="subtle"
										icon="i-lucide-ban"
										size="sm"
										:label="t('groups.members.actions.ban')"
										:loading="props.moderation.banForm.processing && props.moderation.memberPendingBanId === row.original.id"
										@click="props.moderation.openBanConfirmation(row.original)"
									/>
								</div>

								<span v-else class="text-sm text-muted">-</span>
							</div>
						</div>
					</template>
				</UTable>
			</div>

			<div
				class="hidden xl:block"
				:class="shouldFixTableHeight ? 'h-[34rem] overflow-auto' : 'overflow-auto'"
			>
				<UTable
					ref="memberTable"
					v-model:pagination="memberPagination"
					v-model:global-filter="memberGlobalFilter"
					:data="memberTableData"
					:columns="memberColumns"
					:pagination-options="{ getPaginationRowModel: getPaginationRowModel() }"
					class="w-full"
				>
					<template #name-cell="{ row }">
						<div class="flex items-center gap-3">
							<div v-if="row.original.avatar_url" class="h-10 w-10 shrink-0 overflow-hidden rounded-sm border border-default bg-muted/30">
								<img
									:src="row.original.avatar_url"
									:alt="`${row.original.name} avatar`"
									class="h-full w-full object-cover"
								>
							</div>
							<div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm border border-default bg-muted/20">
								<UIcon name="i-lucide-user" size="16" class="text-muted" />
							</div>

							<div class="min-w-0">
								<p class="font-semibold">{{ row.original.name }}</p>
							</div>
						</div>
					</template>

					<template #role-cell="{ row }">
						<UBadge
							:label="roleBadge(row.original.role).label"
							:color="roleBadge(row.original.role).color"
							:icon="roleBadge(row.original.role).icon"
							variant="subtle"
							size="sm"
						/>
					</template>

					<template #joined_at-cell="{ row }">
						<span class="text-sm">{{ formatShortDate(row.original.joined_at) }}</span>
					</template>

					<template #participated_run_count-cell="{ row }">
						<UBadge :label="`${row.original.participated_run_count}`" color="neutral" variant="subtle" />
					</template>

					<template #character_summary-cell="{ row }">
						<div v-if="row.original.characters.length > 0" class="flex flex-wrap gap-2">
							<div
								v-for="character in row.original.characters"
								:key="character.id"
								class="min-w-36 rounded-sm border border-default bg-muted/20 px-3 py-2"
							>
								<UUser
									:name="character.name"
									:description="character.datacenter + ' - ' + character.world"
									:avatar="{
									  src: character.avatar_url,
									  loading: 'lazy',
									  icon: 'i-lucide-image'
									}"
								/>
							</div>
						</div>

						<p v-else class="text-sm text-muted">
							{{ t('groups.members.roster.no_characters') }}
						</p>
					</template>

					<template #actions-cell="{ row }">
						<div v-if="row.original.note_summary.can_view || row.original.permissions.can_promote || row.original.permissions.can_demote || row.original.permissions.can_kick || row.original.permissions.can_ban" class="flex flex-wrap items-center gap-2">
							<MemberNotesButton
								:user-id="row.original.id"
								:note-summary="row.original.note_summary"
								@open="props.notes.openMemberNotes"
							/>
							<UButton
								v-if="row.original.permissions.can_promote"
								color="primary"
								variant="subtle"
								icon="i-lucide-arrow-up"
								:label="t('groups.members.actions.promote')"
								:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === row.original.id"
								@click="props.moderation.updateMemberRole(row.original, nextPromotedRole(row.original.role))"
							/>
							<UButton
								v-if="row.original.permissions.can_demote"
								color="neutral"
								variant="subtle"
								icon="i-lucide-arrow-down"
								:label="t('groups.members.actions.demote')"
								:loading="props.moderation.updateRoleForm.processing && props.moderation.memberPendingRoleUpdateId === row.original.id"
								@click="props.moderation.updateMemberRole(row.original, nextDemotedRole(row.original.role))"
							/>
							<UButton
								v-if="row.original.permissions.can_kick"
								color="error"
								variant="ghost"
								icon="i-lucide-user-round-x"
								:label="t('groups.members.actions.kick')"
								:loading="props.moderation.removeForm.processing && props.moderation.memberPendingRemovalId === row.original.id"
								@click="props.moderation.openKickConfirmation(row.original)"
							/>
							<UButton
								v-if="row.original.permissions.can_ban"
								color="error"
								variant="subtle"
								icon="i-lucide-ban"
								:label="t('groups.members.actions.ban')"
								:loading="props.moderation.banForm.processing && props.moderation.memberPendingBanId === row.original.id"
								@click="props.moderation.openBanConfirmation(row.original)"
							/>
						</div>

						<span v-else class="text-sm text-muted">-</span>
					</template>
				</UTable>
			</div>

			<div class="flex justify-end border-t border-default px-4 pt-4">
				<UPagination
					:page="(memberTable?.tableApi?.getState().pagination.pageIndex || 0) + 1"
					:items-per-page="memberTable?.tableApi?.getState().pagination.pageSize"
					:total="filteredRowCount"
					@update:page="(page) => memberTable?.tableApi?.setPageIndex(page - 1)"
				/>
			</div>
		</div>
	</UCard>
</template>
