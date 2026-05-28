import type { LocalizedText } from "./Common"

export type LandingAssignedMember = {
	id: number
	name: string
	initials: string
	avatar_url: string | null
}

export type LandingThisWeekRun = {
	id: number
	title: string | null
	activity_type_name: LocalizedText
	difficulty: string | null
	datacenter: string | null
	starts_at: string | null
	application_status_key: "open" | "closed"
	allow_guest_applications: boolean
	filled_slots: number
	total_slots: number
	overflow_count: number
	assigned_members: LandingAssignedMember[]
	href: string | null
}

export type LandingThisWeekDay = {
	key: "mon" | "tue" | "wed" | "thu" | "fri" | "sat" | "sun"
	date: string
	is_today: boolean
	hidden_run_count: number
	runs: LandingThisWeekRun[]
}

export type LandingThisWeek = {
	start: string
	end: string
	days: LandingThisWeekDay[]
}

export type LandingPageData = {
	this_week: LandingThisWeek
}

export type LandingPageProps = {
	landing: LandingPageData
}
