import type { AccountApplication } from "@/Types/ActivityCore"

export type DashboardProfile = {
	name: string
	email: string
	avatar_url: string | null
	email_verified_at: string | null
	primary_character: {
		id: number
		name: string | null
		world: string | null
		datacenter: string | null
		avatar_url: string | null
	} | null
}

export type DashboardSummary = {
	unread_notification_count: number
	verified_character_count: number
	connected_account_count: number
	group_count: number
	owned_group_count: number
	moderated_group_count: number
	member_group_count: number
	active_application_count: number
	pending_application_count: number
	confirmed_participation_count: number
	completed_participation_count: number
}

export type DashboardSetup = {
	has_primary_character: boolean
	has_verified_characters: boolean
	public_profile: boolean
	public_characters: boolean
	connected_providers: string[]
}

export type DashboardGroupLink = {
	id: number
	name: string
	slug: string
	href: string
}

export type DashboardGroupBucket = {
	count: number
	items: DashboardGroupLink[]
}

export type DashboardGroups = {
	owned: DashboardGroupBucket
	moderated: DashboardGroupBucket
	member: DashboardGroupBucket
}

export type DashboardPageProps = {
	profile: DashboardProfile
	summary: DashboardSummary
	setup: DashboardSetup
	groups: DashboardGroups
	upcomingParticipations: AccountApplication[]
	recentApplications: AccountApplication[]
}
