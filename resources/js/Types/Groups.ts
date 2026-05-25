import type { LocalizedText } from "@/Types/Common"

export type MemberNoteSeverity = "info" | "warning" | "critical"
export type GroupType = "community" | "static"
export type GroupRole = "owner" | "admin" | "moderator" | "member"
export type GroupCreateField =
	| "name"
	| "description"
	| "profile_picture"
	| "banner_image"
	| "discord_invite_url"
	| "datacenter"
	| "is_public"
	| "is_visible"
	| "slug"
	| "group_type"
	| "recruiting_status"
	| "primary_focuses"
	| "experience_expectation"
	| "voice_expectation"
	| "preferred_languages"
	| "tags"
	| "active_timezone"
	| "active_days"
	| "active_start_time"
	| "active_end_time"

export type GroupCreateFormData = {
	name: string
	description: string
	profile_picture: File | null
	banner_image: File | null
	discord_invite_url: string
	datacenter: string
	is_public: boolean
	is_visible: boolean
	slug: string
	group_type: GroupType
	recruiting_status: string
	primary_focuses: string[]
	experience_expectation: string
	voice_expectation: string
	preferred_languages: string[]
	tags: string[]
	active_timezone: string
	active_days: string[]
	active_start_time: string
	active_end_time: string
}

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
	banner_image_url: string | null
	discord_invite_url: string | null
	datacenter: string | null
	region: string | null
	group_type: GroupType
	is_public: boolean
	is_visible: boolean
	recruiting_status: string | null
	primary_focuses: string[]
	experience_expectation: string | null
	voice_expectation: string | null
	preferred_languages: string[]
	tags: string[]
	active_timezone: string | null
	active_days: string[]
	active_start_time: string | null
	active_end_time: string | null
	badge_meta: GroupDiscoveryBadgeMeta
	owner: {
		id: number | null
		name: string | null
		avatar_url: string | null
	}
	links: {
		dashboard: string | null
	}
	current_user_role: string | null
	stats: {
		member_count: number
		upcoming_run_count: number
		run_count?: number
		completed_run_count?: number
		latest_member_join_at?: string | null
		last_activity_at: string | null
	}
}

export type FeaturedGroupRecord = {
	id: number
	slug: string
	name: string
	banner_image_url: string | null
	experience_expectation: string | null
	experience_badge: GroupDiscoveryBadgeEntry | null
	preferred_languages: string[]
	tags: string[]
	tag_badges: Array<{
		value: string
		label: string
		color: string
	}>
	stats: {
		member_count: number
	}
}

export type DatacenterLookup = {
	label: string
	value: string
	region: string | null
}

export type GroupDiscoveryLookups = {
	recruiting_statuses?: string[]
	primary_focuses?: string[]
	experience_expectations?: string[]
	voice_expectations?: string[]
	active_days?: string[]
	preferred_languages?: string[]
	max_tags?: number
	badge_colors?: Record<string, unknown>
}

export type GroupDiscoveryBadgeEntry = {
	value: string
	color: string | null
}

export type GroupDiscoveryTagBadge = {
	value: string
	label: string
	color: string
}

export type GroupDiscoveryBadgeMeta = {
	recruiting_status: GroupDiscoveryBadgeEntry | null
	primary_focuses: GroupDiscoveryBadgeEntry[]
	experience_expectation: GroupDiscoveryBadgeEntry | null
	voice_expectation: GroupDiscoveryBadgeEntry | null
	preferred_languages: GroupDiscoveryBadgeEntry[]
	active_days: GroupDiscoveryBadgeEntry[]
	tags: GroupDiscoveryTagBadge[]
	region: GroupDiscoveryBadgeEntry | null
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
	secret_key: string | null
	can_view_overview: boolean
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
	role: GroupRole
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
	group_type: GroupType
	owner: {
		id: number | null
		name: string | null
		avatar_url: string | null
	}
	current_user_role: string | null
	follow: {
		is_following: boolean
		notifications_enabled: boolean
	}
	permissions: {
		can_manage_group: boolean
		can_manage_members: boolean
		can_manage_activities: boolean
		can_leave: boolean
		can_toggle_notifications: boolean
	}
	stats: {
		member_count: number
		moderator_count: number
		activity_count: number
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
		admin: number
		moderator: number
		member: number
	}
	members_preview: GroupDashboardMemberPreview[]
	activity_status_breakdown: Array<{
		status: string
		count: number
	}>
	upcoming_activities: GroupDashboardActivity[]
	history_activities: GroupDashboardActivity[]
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

export type GroupDiscoveryActivitySummary = {
	completed_runs: number
	total_runs: number
	runs_per_week: number
	average_turnout: number
}

export type GroupDiscoveryRecentRun = {
	id: number
	status: string
	starts_at: string | null
	activity_name: string
	activity_image_url: string | null
	run_title: string | null
	turnout_count: number
	progress_summary: string | null
}

export type GroupDiscoveryContentStatusCount = {
	status: "planned" | "scheduled" | "active" | "complete" | "cancelled"
	count: number
}

export type GroupDiscoveryContentSummary = {
	total_runs: number
	status_breakdown: GroupDiscoveryContentStatusCount[]
}

export type GroupDiscoveryContentItem = {
	key: string
	activity_name: string
	activity_image_url: string | null
	total_runs: number
	completed_runs: number
	active_runs: number
	last_run_at: string | null
	next_run_at: string | null
}

export type GroupDiscoveryTeamMember = {
	id: number
	name: string | null
	avatar_url: string | null
	role: "owner" | "admin" | "moderator"
	joined_at: string | null
}

export type GroupDiscoveryDetailRecord = GroupIndexRecord & {
	activity_summary: GroupDiscoveryActivitySummary
	recent_runs: GroupDiscoveryRecentRun[]
	content_summary: GroupDiscoveryContentSummary
	content_items: GroupDiscoveryContentItem[]
	team_members: GroupDiscoveryTeamMember[]
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
	role: GroupRole
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
	characters: GroupMemberCharacter[]
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
	updateMemberRole: (member: GroupMemberRecord, role: 'admin' | 'moderator' | 'member') => void
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
