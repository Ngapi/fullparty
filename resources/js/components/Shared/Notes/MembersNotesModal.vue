<script setup lang="ts">
import type { MemberNote } from "@/Types/Groups";
import { useI18n } from "vue-i18n";
import { useMemberNotes } from "@/composables/useMemberNotes";

const props = defineProps<{
	notes: ReturnType<typeof useMemberNotes>
}>();

const { t } = useI18n();
const {
	isNotesModalOpen,
	member,
	isLoading,
	hasLoadError,
	totalVisibleNoteCount,
	severityOptions,
	noteForm,
	noteUpdateForm,
	addendumForm,
	noteDeleteForm,
	editingNoteId,
	addendumNoteId,
	pendingDeleteNoteId,
	handleNotesModalOpenChange,
	openEditNote,
	cancelEditNote,
	submitNoteUpdate,
	openAddendum,
	cancelAddendum,
	submitAddendum,
	removeNote,
	submitNote,
} = props.notes;

const formatDate = (value: string | null) => {
	if (!value) {
		return t('groups.members.roster.not_available');
	}

	return new Intl.DateTimeFormat(undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	}).format(new Date(value));
};

const severityBadge = (severity: MemberNote['severity']) => ({
	info: {
		label: t('general.severity_levels.info'),
		color: 'info',
		icon: 'i-lucide-info',
	},
	warning: {
		label: t('general.severity_levels.warning'),
		color: 'warning',
		icon: 'i-lucide-triangle-alert',
	},
	critical: {
		label: t('general.severity_levels.critical'),
		color: 'error',
		icon: 'i-lucide-octagon-alert',
	},
}[severity]);

const severityBorderClass = (severity: MemberNote['severity']) => ({
	info: 'border-r-2 border-r-info',
	warning: 'border-r-2 border-r-warning',
	critical: 'border-r-2 border-r-error',
}[severity]);
</script>

<template>
	<UModal
		:open="isNotesModalOpen"
		:title="t('groups.members.notes.modal.title', { name: member?.name ?? '' })"
		:description="t('groups.members.notes.modal.subtitle')"
		:ui="{ content: 'rounded-sm max-w-6xl', header: 'border-0 sm:px-6 sm:pt-6', body: 'sm:px-6 sm:pb-6' }"
		@update:open="handleNotesModalOpenChange"
	>
		<template #body>
			<div v-if="isLoading" class="flex flex-col gap-6">
				<div class="flex flex-wrap items-center gap-2">
					<USkeleton class="h-6 w-32" />
					<USkeleton class="h-6 w-32" />
					<USkeleton class="h-6 w-32" />
				</div>

				<div class="flex w-full flex-row items-start justify-start gap-6">
					<section class="flex w-2/5 flex-col gap-4">
						<UCard class="dark:bg-elevated/25">
							<div class="flex flex-col gap-4">
								<USkeleton class="h-10 w-full" />
								<USkeleton class="h-10 w-full" />
								<USkeleton class="h-40 w-full" />
								<USkeleton class="h-10 w-full" />
							</div>
						</UCard>
					</section>

					<section class="flex w-3/5 min-w-0 flex-col gap-4">
						<USkeleton class="h-32 w-full" />
						<USkeleton class="h-32 w-full" />
						<USkeleton class="h-24 w-full" />
					</section>
				</div>
			</div>

			<UAlert
				v-else-if="hasLoadError"
				color="error"
				variant="subtle"
				icon="i-lucide-triangle-alert"
				:title="t('groups.members.notes.load_error')"
			/>

			<div v-else-if="member?.notes.can_view" class="flex flex-col gap-6">
				<div class="flex flex-wrap items-center gap-2">
					<UBadge
						color="neutral"
						variant="subtle"
						:label="t('groups.members.notes.count.total', { count: totalVisibleNoteCount })"
					/>
					<UBadge
						color="primary"
						variant="subtle"
						:label="t('groups.members.notes.count.current_group', { count: member.notes.current_group_count })"
					/>
					<UBadge
						color="secondary"
						variant="subtle"
						:label="t('groups.members.notes.count.shared', { count: member.notes.shared_count })"
					/>
				</div>

				<div class="flex max-h-[75vh] w-full flex-row items-start justify-start gap-6">
					<section class="w-2/5 flex flex-col gap-4">
						<UCard v-if="member.notes.can_add" class="dark:bg-elevated/25">
							<div class="flex flex-col gap-4">
								<UFormField
									:label="t('general.severity')"
									:error="noteForm.errors.severity"
								>
									<USelect
										v-model="noteForm.severity"
										value-key="value"
										:items="severityOptions"
										class="w-full"
									/>
								</UFormField>

								<UFormField
									orientation="horizontal"
									:label="t('groups.members.notes.fields.shared.label')"
									:description="t('groups.members.notes.fields.shared.help')"
									class="items-center"
								>
									<USwitch v-model="noteForm.is_shared_with_groups" />
								</UFormField>

								<UFormField
									:label="t('groups.members.notes.fields.body.label')"
									:help="t('groups.members.notes.fields.body.help')"
									:error="noteForm.errors.body"
								>
									<UTextarea
										v-model="noteForm.body"
										class="w-full"
										:rows="10"
										:placeholder="t('groups.members.notes.fields.body.placeholder')"
									/>
								</UFormField>

								<div class="flex justify-stretch">
									<UButton
										color="primary"
										icon="i-lucide-notebook-pen"
										class="w-full justify-center"
										:label="t('groups.members.notes.create.submit')"
										:loading="noteForm.processing"
										@click="submitNote"
									/>
								</div>
							</div>
						</UCard>
					</section>

					<section class="w-3/5 min-w-0 max-h-[75vh] overflow-y-auto pr-2 flex flex-col gap-6">
						<div class="flex flex-col gap-3">
							<div>
								<p class="font-semibold text-md">{{ t('groups.members.notes.sections.current_group.title') }}</p>
								<p class="text-sm text-muted">{{ t('groups.members.notes.sections.current_group.subtitle') }}</p>
							</div>

							<div v-if="member.notes.current_group.length > 0" class="flex flex-col gap-3">
								<UCard
									v-for="note in member.notes.current_group"
									:key="`group-note-${note.id}`"
									:class="['dark:bg-elevated/20', severityBorderClass(note.severity)]"
								>
									<div class="flex flex-col gap-3">
										<div class="flex flex-wrap items-start justify-between gap-3">
											<div class="flex min-w-0 items-center gap-3">
												<UAvatar
													:src="note.author?.avatar_url ?? undefined"
													:alt="note.author?.name ?? t('audit_log.defaults.system')"
													icon="i-lucide-user"
												/>
												<div class="min-w-0">
													<p class="truncate font-medium">{{ note.author?.name ?? t('audit_log.defaults.system') }}</p>
													<p class="text-xs text-muted">{{ formatDate(note.created_at) }}</p>
												</div>
											</div>
											<div class="flex flex-wrap items-center gap-2">
												<UBadge
													:label="severityBadge(note.severity).label"
													:color="severityBadge(note.severity).color"
													:icon="severityBadge(note.severity).icon"
													variant="subtle"
												/>
												<UBadge
													v-if="note.is_shared_with_groups"
													color="secondary"
													variant="soft"
													icon="i-lucide-globe"
													:label="t('general.shared')"
												/>
											</div>
										</div>

										<div v-if="editingNoteId === note.id" class="flex flex-col gap-4">
											<UFormField
												:label="t('general.severity')"
												:error="noteUpdateForm.errors.severity"
											>
												<USelect
													v-model="noteUpdateForm.severity"
													value-key="value"
													:items="severityOptions"
													class="w-full"
												/>
											</UFormField>

											<UFormField
												orientation="horizontal"
												:label="t('groups.members.notes.fields.shared.label')"
												:description="t('groups.members.notes.fields.shared.help')"
												class="items-center"
											>
												<USwitch v-model="noteUpdateForm.is_shared_with_groups" />
											</UFormField>

											<UFormField
												:label="t('groups.members.notes.fields.body.label')"
												:error="noteUpdateForm.errors.body"
											>
												<UTextarea
													v-model="noteUpdateForm.body"
													class="w-full"
													:rows="6"
												/>
											</UFormField>

											<div class="flex flex-wrap justify-end gap-2">
												<UButton
													color="neutral"
													variant="ghost"
													:label="t('general.cancel')"
													@click="cancelEditNote"
												/>
												<UButton
													color="primary"
													icon="i-lucide-save"
													:label="t('general.save')"
													:loading="noteUpdateForm.processing"
													@click="submitNoteUpdate(note)"
												/>
											</div>
										</div>

										<p v-else class="whitespace-pre-wrap text-sm text-toned">{{ note.body }}</p>

										<div class="flex flex-wrap items-center gap-2">
											<UButton
												v-if="note.permissions.can_edit_body"
												color="neutral"
												variant="soft"
												size="sm"
												icon="i-lucide-pencil"
												:label="t('general.edit')"
												@click="openEditNote(note)"
											/>
											<UButton
												v-if="note.permissions.can_add_addendum"
												color="secondary"
												variant="soft"
												size="sm"
												icon="i-lucide-message-square-plus"
												:label="t('groups.members.notes.actions.add_context')"
												@click="openAddendum(note)"
											/>
											<UButton
												v-if="note.permissions.can_delete"
												color="error"
												variant="ghost"
												size="sm"
												icon="i-lucide-trash-2"
												:label="t('general.delete')"
												:loading="noteDeleteForm.processing && pendingDeleteNoteId === note.id"
												@click="removeNote(note)"
											/>
										</div>

										<div v-if="note.addenda.length > 0" class="flex flex-col gap-2 rounded-sm border border-default/70 bg-muted/10 p-3">
											<p class="text-xs font-semibold uppercase tracking-wide text-muted">
												{{ t('groups.members.notes.addenda.title') }}
											</p>
											<div
												v-for="addendum in note.addenda"
												:key="`note-${note.id}-addendum-${addendum.id}`"
												class="rounded-sm border border-default/60 bg-background/60 px-3 py-2"
											>
												<p class="text-xs text-muted">
													{{ t('groups.members.notes.addenda.byline', {
														author: addendum.author?.name ?? t('audit_log.defaults.system'),
														date: formatDate(addendum.created_at),
													}) }}
												</p>
												<p class="mt-1 whitespace-pre-wrap text-sm text-toned">{{ addendum.body }}</p>
											</div>
										</div>

										<div v-if="addendumNoteId === note.id" class="flex flex-col gap-3 rounded-sm border border-default bg-muted/10 p-3">
											<UFormField
												:label="t('groups.members.notes.addenda.form.label')"
												:error="addendumForm.errors.body"
											>
												<UTextarea
													v-model="addendumForm.body"
													class="w-full"
													:rows="4"
													:placeholder="t('groups.members.notes.addenda.form.placeholder')"
												/>
											</UFormField>

											<div class="flex flex-wrap justify-end gap-2">
												<UButton
													color="neutral"
													variant="ghost"
													:label="t('general.cancel')"
													@click="cancelAddendum"
												/>
												<UButton
													color="secondary"
													icon="i-lucide-message-square-plus"
													:label="t('groups.members.notes.actions.save_context')"
													:loading="addendumForm.processing"
													@click="submitAddendum(note)"
												/>
											</div>
										</div>
									</div>
								</UCard>
							</div>

							<UAlert
								v-else
								color="neutral"
								variant="subtle"
								icon="i-lucide-notebook"
								:title="t('groups.members.notes.sections.current_group.empty')"
							/>
						</div>

						<div class="flex flex-col gap-3">
							<div>
								<p class="font-semibold text-md">{{ t('groups.members.notes.sections.shared.title') }}</p>
								<p class="text-sm text-muted">{{ t('groups.members.notes.sections.shared.subtitle') }}</p>
							</div>

							<div v-if="member.notes.shared.length > 0" class="flex flex-col gap-3">
								<UCard
									v-for="note in member.notes.shared"
									:key="`shared-note-${note.id}`"
									:class="['dark:bg-elevated/20', severityBorderClass(note.severity)]"
								>
									<div class="flex flex-col gap-3">
										<div class="flex flex-wrap items-start justify-between gap-3">
											<div class="min-w-0">
												<p class="truncate font-medium">{{ note.source_group?.name ?? t('groups.members.notes.unknown_group') }}</p>
												<p class="text-xs text-muted">
													{{ t('groups.members.notes.shared_from', { author: note.author?.name ?? t('audit_log.defaults.system'), date: formatDate(note.created_at) }) }}
												</p>
											</div>
											<UBadge
												:label="severityBadge(note.severity).label"
												:color="severityBadge(note.severity).color"
												:icon="severityBadge(note.severity).icon"
												variant="subtle"
											/>
										</div>

										<p class="whitespace-pre-wrap text-sm text-toned">{{ note.body }}</p>

										<div v-if="note.addenda.length > 0" class="flex flex-col gap-2 rounded-sm border border-default/70 bg-muted/10 p-3">
											<p class="text-xs font-semibold uppercase tracking-wide text-muted">
												{{ t('groups.members.notes.addenda.title') }}
											</p>
											<div
												v-for="addendum in note.addenda"
												:key="`shared-note-${note.id}-addendum-${addendum.id}`"
												class="rounded-sm border border-default/60 bg-background/60 px-3 py-2"
											>
												<p class="text-xs text-muted">
													{{ t('groups.members.notes.addenda.byline', {
														author: addendum.author?.name ?? t('audit_log.defaults.system'),
														date: formatDate(addendum.created_at),
													}) }}
												</p>
												<p class="mt-1 whitespace-pre-wrap text-sm text-toned">{{ addendum.body }}</p>
											</div>
										</div>
									</div>
								</UCard>
							</div>

							<UAlert
								v-else
								color="neutral"
								variant="subtle"
								icon="i-lucide-globe"
								:title="t('groups.members.notes.sections.shared.empty')"
							/>
						</div>
					</section>
				</div>
			</div>

			<UAlert
				v-else
				color="neutral"
				variant="subtle"
				icon="i-lucide-eye-off"
				:title="t('groups.members.notes.hidden')"
			/>
		</template>
	</UModal>
</template>
