export type AuditLogRowRecord = {
	id: number
	action: string
	severity: string
	scope?: {
		type: string | null
		id: number | null
		label: string | null
	}
	actor: {
		id: number | null
		name: string
		avatar_url: string | null
		is_system: boolean
	}
	subject: {
		type: string | null
		id: number | null
		name: string
		avatar_url: string | null
		is_system: boolean
	}
	title?: string
	summary?: string
	changes: Array<{
		label: string
		old: string
		new: string
	}>
	details: string[]
	search_text?: string
	created_at: string
}
