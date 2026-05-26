const preferredTimeZoneRepresentatives = [
	'Etc/GMT+12',
	'Pacific/Pago_Pago',
	'Pacific/Honolulu',
	'America/Anchorage',
	'America/Los_Angeles',
	'America/Denver',
	'America/Chicago',
	'America/New_York',
	'America/Halifax',
	'America/Sao_Paulo',
	'Atlantic/South_Georgia',
	'Europe/London',
	'UTC',
	'Europe/Paris',
	'Europe/Athens',
	'Asia/Dubai',
	'Asia/Karachi',
	'Asia/Dhaka',
	'Asia/Bangkok',
	'Asia/Shanghai',
	'Asia/Tokyo',
	'Australia/Brisbane',
	'Pacific/Noumea',
	'Pacific/Auckland',
	'Pacific/Tongatapu',
]

const timeZoneLabelOverrides: Record<string, string> = {
	'Etc/GMT+12': 'International Date Line West',
	UTC: 'UTC',
}

const offsetMinutesForZone = (timeZone: string) => {
	try {
		const parts = new Intl.DateTimeFormat('en-GB', {
			timeZone,
			timeZoneName: 'shortOffset',
		}).formatToParts(new Date())
		const rawOffset = parts.find((part) => part.type === 'timeZoneName')?.value ?? 'GMT'
		const normalizedOffset = rawOffset.replace('UTC', 'GMT')

		if (normalizedOffset === 'GMT') {
			return 0
		}

		const match = normalizedOffset.match(/^GMT([+-])(\d{1,2})(?::(\d{2}))?$/)

		if (!match) {
			return null
		}

		const sign = match[1] === '-' ? -1 : 1
		const hours = Number(match[2] ?? '0')
		const minutes = Number(match[3] ?? '0')

		return sign * ((hours * 60) + minutes)
	} catch {
		return null
	}
}

const formatUtcOffsetLabel = (offsetMinutes: number) => {
	if (offsetMinutes === 0) {
		return 'UTC+0'
	}

	const sign = offsetMinutes > 0 ? '+' : '-'
	const hours = Math.abs(offsetMinutes) / 60

	return `UTC${sign}${hours}`
}

const compareRepresentativeZones = (left: string, right: string) => {
	const leftPreferredIndex = preferredTimeZoneRepresentatives.indexOf(left)
	const rightPreferredIndex = preferredTimeZoneRepresentatives.indexOf(right)

	if (leftPreferredIndex !== -1 || rightPreferredIndex !== -1) {
		if (leftPreferredIndex === -1) {
			return 1
		}

		if (rightPreferredIndex === -1) {
			return -1
		}

		return leftPreferredIndex - rightPreferredIndex
	}

	return left.localeCompare(right)
}

export const buildGroupTimeZoneOptions = () => {
	const supportedValuesOf = (Intl as typeof Intl & {
		supportedValuesOf?: (key: 'timeZone') => string[]
	}).supportedValuesOf
	const values = supportedValuesOf
		? supportedValuesOf('timeZone')
		: preferredTimeZoneRepresentatives

	const zonesByOffset = new Map<number, string[]>()

	for (const value of values) {
		const offsetMinutes = offsetMinutesForZone(value)

		if (offsetMinutes === null || offsetMinutes < -720 || offsetMinutes > 720 || offsetMinutes % 60 !== 0) {
			continue
		}

		const existing = zonesByOffset.get(offsetMinutes) ?? []
		existing.push(value)
		zonesByOffset.set(offsetMinutes, existing)
	}

	return Array.from(zonesByOffset.entries())
		.sort(([leftOffset], [rightOffset]) => leftOffset - rightOffset)
		.map(([offsetMinutes, zones]) => {
			const representative = [...zones].sort(compareRepresentativeZones)[0] ?? zones[0]
			const displayName = timeZoneLabelOverrides[representative] ?? representative

			return {
				value: representative,
				label: `${displayName} - ${formatUtcOffsetLabel(offsetMinutes)}`,
			}
		})
}
