import type { LocalizedStringRecord } from "@/Types/Common"

export type ActivityTypeProgPoint = {
	key: string
	label: LocalizedStringRecord
}

export type ActivityTypeLayoutGroup = {
	key: string
	label: LocalizedStringRecord
	size: number
}

export type ActivityTypeRosterSummaryRequirement = {
	source: string
	source_id: number | null
	comparison: string
	target_count: number
	scope_type: string
	scope_group_keys: string[]
}

export type ActivityTypeRosterSummaryPreset = {
	key: string
	label: LocalizedStringRecord
	description?: LocalizedStringRecord
	requirements: ActivityTypeRosterSummaryRequirement[]
}

export type ActivityTypeRosterSummarySourceOption = {
	value: number
	label: string
	meta?: {
		role?: string | null
		shorthand?: string | null
	}
}

export type ActivityTypeRosterSummaryReference = {
	supportedSources: string[]
	supportedComparisons: string[]
	supportedScopeTypes: string[]
	sourceOptions: Record<string, ActivityTypeRosterSummarySourceOption[]>
}

export type ActivityTypeSchemaOption = {
	value: string
	label: LocalizedStringRecord
}

export type ActivityTypeSchemaField = {
	key: string
	type: string
	source?: string | null
	required?: boolean
	label: LocalizedStringRecord
	help_text?: LocalizedStringRecord
	options?: ActivityTypeSchemaOption[]
}

export type ActivityTypeProgressMilestone = {
	key: string
	label: LocalizedStringRecord
	order: number
	fflogs_matcher: {
		type: "encounter" | "phase"
		encounter_id: number | null
		phase_id: number | null
	}
}

export type ActivityTypeProgressSchema = {
	milestones: ActivityTypeProgressMilestone[]
}
