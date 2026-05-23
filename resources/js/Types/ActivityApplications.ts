import type { LocalizedText } from "@/Types/Common"

export type ApplicationQuestionOption = {
	key: string
	label: LocalizedText
	meta?: {
		icon_url?: string | null
		role?: string | null
		shorthand?: string | null
	} | null
}

export type ApplicationQuestion = {
	key: string
	label: LocalizedText
	type: string
	source: string | null
	required?: boolean
	help_text?: LocalizedText
	options: ApplicationQuestionOption[]
}

export type GuestWorldOption = {
	label: string
	value: string
}

export type GuestCharacterSearchResult = {
	lodestone_id: string
	name: string
	world: string
	datacenter: string | null
	avatar_url: string | null
	profile_url: string | null
}

export type ActivityApplicantCharacter = {
	lodestone_id: string
	name: string
	world: string
	datacenter: string | null
	avatar_url: string | null
}

export type ActivityApplicationRecord = {
	id: number
	selected_character_id: number | null
	status: string
	is_rostered: boolean
	notes: string | null
	submitted_at: string | null
	review_reason?: string | null
	applicant_character?: ActivityApplicantCharacter | null
	answers: Record<string, unknown>
}
