import type { LocalizedText } from "@/Types/Common"
import type { ActivityProgressPoint } from "@/Types/ActivityCore"
import type { QueueFilterField } from "@/Types/ActivityQueue"
import type { ActivityMissingAssignment, ActivityRosterSummaryPreset, ActivitySlot } from "@/Types/ActivityRoster"

export type ActivityProgressMilestone = {
	id: number
	milestone_key: string
	milestone_label: LocalizedText
	kills: number
	best_progress_percent: number | null
}

export type ActivityManagementProgressMilestone = ActivityProgressMilestone & {
	sort_order: number
	source: string | null
	notes: string | null
}

export type ActivityCompletionPreviewMilestone = {
	milestone_key: string
	kills: number
	best_progress_percent: number | null
}

export type FflogsEncounterProgress = {
	name: string
	kills: number
	progress: number
}

export type FflogsProgressResponse = {
	title: string
	zone_id: number
	encounters: FflogsEncounterProgress[]
	encounter_count: number
	total_kills: number
} | null

export type ActivityData = {
	id: number
	activity_type: {
		id: number | null
		slug: string | null
		draft_name: LocalizedText
	}
	title: string | null
	description: string | null
	notes: string | null
	status: string
	starts_at: string | null
	duration_hours: number | null
	furthest_progress_key: string | null
	furthest_progress_percent: number | null
	is_public: boolean
	needs_application: boolean
	secret_key: string | null
	completed_at: string | null
	organized_by_character: {
		id: number
		user_id: number
		name: string
		avatar_url: string | null
	} | null
	slot_count: number
	assigned_count: number
	pending_application_count: number
}

export type ActivityManagementPageProps = {
	group: {
		id: number
		name: string
		slug: string
		current_user_role: string | null
		permissions: {
			can_manage_activities: boolean
		}
	}
	activity: ActivityData
}

export type ActivityDetails = {
	id: number
	activity_type: {
		id: number | null
		slug: string | null
		draft_name: LocalizedText
	}
	activity_type_version_id: number
	fflogs_zone_id: number | null
	title: string | null
	description: string | null
	notes: string | null
	status: string
	starts_at: string | null
	duration_hours: number | null
	target_prog_point_key: string | null
	furthest_progress_key: string | null
	furthest_progress_percent: number | null
	is_public: boolean
	needs_application: boolean
	secret_key: string | null
	progress_entry_mode: string | null
	progress_link_url: string | null
	progress_notes: string | null
	completed_at: string | null
	organized_by: {
		id: number
		name: string
		avatar_url: string | null
	} | null
	organized_by_character: {
		id: number
		user_id: number
		name: string
		avatar_url: string | null
	} | null
	slot_count: number
	bench_slot_count: number
	application_count: number
	pending_application_count: number
	progress_milestone_count: number
	can_use_fflogs_completion: boolean
	prog_points: ActivityProgressPoint[]
	roster_summary_presets: ActivityRosterSummaryPreset[]
	slot_field_definitions: QueueFilterField[]
	slots: ActivitySlot[]
	missing_assignments: ActivityMissingAssignment[]
	progress_milestones: ActivityManagementProgressMilestone[]
}

export type ActivityManagementPatch = {
	updated_slots?: ActivitySlot[]
	pending_application_count?: number
	queue_application_sync_ids?: number[]
	queue_application_remove_ids?: number[]
	upsert_missing_assignments?: ActivityDetails["missing_assignments"]
	remove_missing_assignment_ids?: number[]
}

export type SlotDesignation = "host" | "raid_leader"
