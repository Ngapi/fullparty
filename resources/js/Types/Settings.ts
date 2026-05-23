export type SettingsSocialAccount = {
	id: number
	provider: string
	provider_name: string | null
	provider_email: string | null
}

export type SettingsUser = {
	name: string
	email: string
	public_profile: boolean
	public_characters: boolean
	application_notifications: boolean
	run_and_reminder_notifications: boolean
	group_update_notifications: boolean
	assignment_notifications: boolean
	account_character_notifications: boolean
	system_notice_notifications: boolean
	email_notifications: boolean
	discord_notifications: boolean
	social_accounts: SettingsSocialAccount[]
}
