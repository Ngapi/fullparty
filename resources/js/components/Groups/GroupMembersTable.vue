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

const memberPagination = ref({
	pageIndex: 0,
	pageSize: 8,
});
const memberGlobalFilter = ref('');

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

const memberTableData = computed<GroupMemberTableRow[]>(() => props.members.map((member) => ({
	...member,
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

const filteredRowCount = computed(() => memberTable.value?.tableApi?.getFilteredRowModel().rows.length ?? 0);
const shouldFixTableHeight = computed(() => filteredRowCount.value > memberPagination.value.pageSize);

watch(memberGlobalFilter, () => {
	memberPagination.value.pageIndex = 0;
});
</script>

<template>
	<UCard class="w-full dark:bg-elevated/25">
		<template #header>
			<div class="flex flex-row items-center justify-between gap-4">
				<div class="flex flex-col gap-1">
					<p class="font-semibold text-md">{{ t('groups.members.roster.title') }}</p>
					<p class="text-sm text-muted">{{ t('groups.members.roster.subtitle') }}</p>
				</div>
				<div class="flex items-center gap-2">
					<UInput
						v-model="memberGlobalFilter"
						class="w-72"
						icon="i-lucide-search"
						:placeholder="t('groups.members.roster.search_placeholder')"
					/>
					<UBadge :label="memberCountLabel" color="neutral" variant="subtle" />
				</div>
			</div>
		</template>

		<div class="flex flex-col gap-4">
			<div :class="shouldFixTableHeight ? 'h-[34rem] overflow-auto' : 'overflow-auto'">
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
