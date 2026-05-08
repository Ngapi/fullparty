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
