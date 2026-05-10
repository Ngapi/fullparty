import type { ActivityRosterSummaryPreset, ActivitySlot } from "@/Types/ActivityRoster"
import type { LocalizedText } from "@/Types/Common"

export type PublicGroupSummary = {
	id: number
	name: string
	slug: string
	is_public: boolean
}

export type AttendeeActivityType = {
	id: number | null
	slug: string | null
	draft_name: LocalizedText
}

export type AttendeeOrganizer = {
	id: number
	name: string
	avatar_url: string | null
}

export type AttendeeOrganizerCharacter = {
	id: number
	user_id: number
	name: string
	avatar_url: string | null
}

export type AttendeeActivity = {
	id: number
	activity_type: AttendeeActivityType
	activity_type_version_id: number
	title: string | null
	description: string | null
	small_image_url: string | null
	banner_image_url: string | null
	notes: string | null
	status: string
	starts_at: string | null
	duration_hours: number | null
	datacenter: string | null
	intensity: string | null
	min_item_level: number | null
	beginner_friendly: boolean
	run_style: string | null
	difficulty: string | null
	target_prog_point_key: string | null
	target_prog_point_label: LocalizedText | null
	needs_application: boolean
	allow_guest_applications: boolean
	slot_count: number
	assigned_slot_count: number
	pending_application_count: number
	organized_by: AttendeeOrganizer | null
	organized_by_character: AttendeeOrganizerCharacter | null
	roster_summary_presets: ActivityRosterSummaryPreset[]
	slots: ActivitySlot[]
}

export type ActivityOverviewPermissions = {
	can_apply: boolean
	can_manage: boolean
}
