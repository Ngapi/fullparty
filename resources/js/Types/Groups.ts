export type MemberNoteSeverity = "info" | "warning" | "critical"

export type NoteAuthor = {
	id: number
	name: string
	avatar_url: string | null
} | null

export type NoteSourceGroup = {
	id: number | null
	name: string | null
	slug: string | null
} | null

export type MemberNoteAddendum = {
	id: number
	body: string
	created_at: string | null
	author: NoteAuthor
}

export type MemberNote = {
	id: number
	severity: MemberNoteSeverity
	body: string
	is_shared_with_groups: boolean
	created_at: string | null
	permissions: {
		can_edit_body: boolean
		can_delete: boolean
		can_add_addendum: boolean
	}
	author: NoteAuthor
	addenda: MemberNoteAddendum[]
	source_group: NoteSourceGroup
}

export type MemberNotePayload = {
	can_view: boolean
	can_add: boolean
	current_group_count: number
	shared_count: number
	current_group: MemberNote[]
	shared: MemberNote[]
}

export type MemberNoteSummary = {
	can_view: boolean
	current_group_count: number
	shared_count: number
}

export type GroupIndexRecord = {
	id: number
	slug: string
	name: string
	description: string | null
	profile_picture_url: string | null
	is_public: boolean
	current_user_role: string | null
	stats: {
		member_count: number
		upcoming_run_count: number
		last_activity_at: string | null
	}
}

export type PaginatedGroups = {
	data: GroupIndexRecord[]
	meta: {
		current_page: number
		last_page: number
		per_page: number
		total: number
	}
}

export type GroupMemberManagementGroup = {
	slug: string
	name: string
	current_user_role: string
	permissions: {
		can_manage_members: boolean
		can_manage_roles: boolean
		can_view_bans: boolean
	}
}

export type GroupMemberCharacter = {
	id: number
	name: string
	world: string
	datacenter?: string | null
	avatar_url: string | null
	is_primary: boolean
}

export type GroupMemberRecord = {
	id: number
	name: string
	avatar_url: string | null
	role: string
	joined_at: string | null
	participated_run_count: number
	characters: GroupMemberCharacter[]
	permissions: {
		can_promote: boolean
		can_demote: boolean
		can_kick: boolean
		can_ban: boolean
	}
	note_summary: MemberNoteSummary
}

export type GroupBannedMemberRecord = {
	id: number
	user_id: number | null
	name: string | null
	avatar_url: string | null
	characters: GroupMemberCharacter[]
	reason: string | null
	banned_at: string | null
	banned_by: {
		id: number
		name: string
		avatar_url: string | null
	} | null
	permissions: {
		can_unban: boolean
	}
	note_summary: MemberNoteSummary
}

export type MemberNotesTarget = {
	id: number
	name: string
	notes: MemberNotePayload
}

export type GroupMemberTableRow = GroupMemberRecord & {
	character_summary: string
}

export type GroupBannedMemberTableRow = GroupBannedMemberRecord & {
	name_display: string
	reason_display: string
	banned_by_name: string
	character_summary: string
}

export type GroupMemberNotesController = {
	openMemberNotes: (userId: number) => void
}

export type GroupMembersTableModerationController = {
	updateRoleForm: {
		processing: boolean
	}
	removeForm: {
		processing: boolean
	}
	banForm: {
		processing: boolean
	}
	memberPendingRoleUpdateId: number | null
	memberPendingRemovalId: number | null
	memberPendingBanId: number | null
	updateMemberRole: (member: GroupMemberRecord, role: 'moderator' | 'member') => void
	openKickConfirmation: (member: GroupMemberRecord) => void | Promise<unknown>
	openBanConfirmation: (member: GroupMemberRecord) => void | Promise<unknown>
}

export type GroupBannedMembersTableModerationController = {
	unbanForm: {
		processing: boolean
	}
	memberPendingUnbanId: number | null
	unbanMember: (member: GroupBannedMemberRecord) => void
}
