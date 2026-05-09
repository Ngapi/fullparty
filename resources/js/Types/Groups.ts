import type { LocalizedText } from "@/Types/Common"

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

export type GroupDashboardActivity = {
	id: number
	activity_type: {
		id: number | null
		slug: string | null
		draft_name: LocalizedText
	}
	title: string | null
	status: string
	starts_at: string | null
	duration_hours: number | null
	is_public: boolean
	needs_application: boolean
	allow_guest_applications: boolean
	organized_by: {
		id: number
		name: string
		avatar_url: string | null
	} | null
	organized_by_character: {
		id: number
		user_id: number
		name: string | null
		avatar_url: string | null
	} | null
	slot_count: number
	application_count: number
	created_at: string | null
	updated_at: string | null
}

export type GroupDashboardMemberPreview = {
	id: number
	name: string
	avatar_url: string | null
	role: string
	joined_at: string | null
}

export type GroupDashboardGroup = {
	id: number
	name: string
	description: string | null
	profile_picture_url: string | null
	discord_invite_url: string | null
	datacenter: string
	is_public: boolean
	is_visible: boolean
	slug: string
	owner: {
		id: number | null
		name: string | null
		avatar_url: string | null
	}
	current_user_role: string | null
	permissions: {
		can_manage_group: boolean
		can_manage_members: boolean
		can_manage_activities: boolean
	}
	stats: {
		member_count: number
		moderator_count: number
		activity_count: number
		draft_count: number
		planned_count: number
		scheduled_count: number
		assigned_count: number
		upcoming_count: number
		ongoing_count: number
		completed_count: number
		cancelled_count: number
		open_application_count: number
		guest_friendly_count: number
		public_activity_count: number
		last_activity_at: string | null
		latest_member_join_at: string | null
	}
	member_role_breakdown: {
		owner: number
		moderator: number
		member: number
	}
	members_preview: GroupDashboardMemberPreview[]
	activity_status_breakdown: Array<{
		status: string
		count: number
	}>
	recent_activities: GroupDashboardActivity[]
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
