export type GroupLegacyLeaderboardGroup = {
	id: number
	name: string
	slug: string
	current_user_role?: string | null
	permissions: {
		can_manage_group?: boolean
		can_manage_members?: boolean
		can_manage_discovery?: boolean
		can_manage_activities?: boolean
		can_view_members?: boolean
		can_review_membership_applications?: boolean
		can_manage_membership_application_form?: boolean
	}
}

export type GroupLegacyLeaderboardCharacter = {
	id: number | null
	name: string
	world: string | null
	datacenter: string | null
	avatar_url: string | null
}

export type GroupLegacyLeaderboardBadge = {
	type: "participation" | "leader"
	key: string
	icon: string
}

export type GroupLegacyLeaderboardEntry = {
	rank: number
	character: GroupLegacyLeaderboardCharacter
	participation_count: number
	raid_leader_count: number
	rescue_count: number
	assignment_count: number
	badges: GroupLegacyLeaderboardBadge[]
}

export type GroupLegacyLeaderboardPayload = {
	source: {
		label: string
		is_static: boolean
	}
	summary: {
		total_players: number
		ranked_participants: number
		ranked_raid_leaders: number
		total_participations: number
		total_raid_leader_participations: number
	}
	rankings: {
		participations: GroupLegacyLeaderboardEntry[]
		raid_leaders: GroupLegacyLeaderboardEntry[]
	}
}
