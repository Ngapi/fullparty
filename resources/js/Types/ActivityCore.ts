import type { LocalizedText } from "@/Types/Common"

export type ActivityStatus = "draft" | "planned" | "scheduled" | "assigned" | "upcoming" | "ongoing" | "complete" | "cancelled"

export type ActivityStatusMeta = {
	color: string
	icon: string
	borderClass: string
	dotClass: string
}

export type ActivityProgressPoint = {
	key: string
	label: LocalizedText
}

export interface ActivityIndexItem {
	id: number
	activity_type: {
		id: number | null
		slug: string | null
		draft_name: LocalizedText
	}
	activity_type_version_id: number
	title: string | null
	status: string
	starts_at: string | null
	organized_by: {
		id: number
		name: string
		avatar_url: string | null
	} | null
	slot_count: number
	application_count: number
	progress_milestone_count: number
	created_at: string | null
	updated_at: string | null
}

export type ActivityTypeOption = {
	id: number
	slug: string
	draft_name: LocalizedText
	current_published_version_id: number | null
	slot_count: number
	prog_points: ActivityProgressPoint[]
}

export type OrganizerCharacterOption = {
	id: number
	user_id: number
	name: string | null
	user_name: string | null
	avatar_url: string | null
	world: string | null
}

export type ActivityFormShape = {
	activity_type_id: number | null
	organized_by_user_id: number | null
	organized_by_character_id: number | null
	status: string
	title: string
	notes: string
	starts_at: string | null
	duration_hours: number
	target_prog_point_key: string | null
}

export type ActivityFormOptions = {
	mode: "create" | "edit"
}

export type AccountApplication = {
	id: number
	status: string
	submitted_at: string | null
	reviewed_at: string | null
	review_reason: string | null
	notes: string | null
	can_edit: boolean
	can_cancel: boolean
	group: {
		name: string | null
		slug: string | null
	}
	activity: {
		id: number | null
		title: string | null
		description: string | null
		status: string | null
		starts_at: string | null
		duration_hours: number | null
		is_public: boolean
		secret_key: string | null
		type_name: LocalizedText
	}
	character: {
		name: string | null
		world: string | null
		datacenter: string | null
		avatar_url: string | null
	}
}

export type ActivityCalendarDay = {
	key: string
	date: Date
	isCurrentMonth: boolean
	isToday: boolean
	activities: ActivityIndexItem[]
}
