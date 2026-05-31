import type { ActivityCalendarDay, ActivityIndexItem } from "@/Types/ActivityCore"

export const createMonthStart = (date: Date) => new Date(date.getFullYear(), date.getMonth(), 1)

export const toLocalDateKey = (date: Date) => {
	const year = date.getFullYear()
	const month = `${date.getMonth() + 1}`.padStart(2, '0')
	const day = `${date.getDate()}`.padStart(2, '0')

	return `${year}-${month}-${day}`
}

export const toDisplayDateKey = (date: Date, timeZone?: string) => {
	if (!timeZone) {
		return toLocalDateKey(date)
	}

	const parts = new Intl.DateTimeFormat('en-CA', {
		timeZone,
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
	}).formatToParts(date)

	const year = parts.find((part) => part.type === 'year')?.value ?? '0000'
	const month = parts.find((part) => part.type === 'month')?.value ?? '01'
	const day = parts.find((part) => part.type === 'day')?.value ?? '01'

	return `${year}-${month}-${day}`
}

export const createDateFromLocalKey = (dateKey: string) => {
	const [year, month, day] = dateKey.split('-').map(Number)

	return new Date(year, month - 1, day)
}

export const sortActivitiesByStart = (activities: ActivityIndexItem[]) => (
	activities.slice().sort((left, right) => {
		return new Date(left.starts_at ?? 0).getTime() - new Date(right.starts_at ?? 0).getTime()
	})
)

export const groupActivitiesByDisplayDate = (activities: ActivityIndexItem[], timeZone?: string) => {
	return activities.reduce<Record<string, ActivityIndexItem[]>>((map, activity) => {
		if (!activity.starts_at) {
			return map
		}

		const key = toDisplayDateKey(new Date(activity.starts_at), timeZone)
		map[key] ??= []
		map[key].push(activity)

		return map
	}, {})
}

export const groupActivitiesByLocalDate = (activities: ActivityIndexItem[]) => groupActivitiesByDisplayDate(activities)

export const buildMonthCalendarDays = (
	activityMap: Record<string, ActivityIndexItem[]>,
	monthCursor: Date,
	todayKey = toLocalDateKey(new Date()),
): ActivityCalendarDay[] => {
	const monthStart = createMonthStart(monthCursor)
	const startOffset = (monthStart.getDay() + 6) % 7
	const rangeStart = new Date(monthStart.getFullYear(), monthStart.getMonth(), 1 - startOffset)

	return Array.from({ length: 42 }, (_, index) => {
		const date = new Date(rangeStart.getFullYear(), rangeStart.getMonth(), rangeStart.getDate() + index)
		const key = toLocalDateKey(date)

		return {
			key,
			date,
			isCurrentMonth: date.getMonth() === monthStart.getMonth(),
			isToday: key === todayKey,
			activities: sortActivitiesByStart(activityMap[key] ?? []),
		}
	})
}

export const buildWeekCalendarDays = (
	activityMap: Record<string, ActivityIndexItem[]>,
	anchorDate: Date,
	todayKey = toLocalDateKey(new Date()),
): ActivityCalendarDay[] => {
	const startOffset = (anchorDate.getDay() + 6) % 7
	const rangeStart = new Date(anchorDate.getFullYear(), anchorDate.getMonth(), anchorDate.getDate() - startOffset)

	return Array.from({ length: 7 }, (_, index) => {
		const date = new Date(rangeStart.getFullYear(), rangeStart.getMonth(), rangeStart.getDate() + index)
		const key = toLocalDateKey(date)

		return {
			key,
			date,
			isCurrentMonth: true,
			isToday: key === todayKey,
			activities: sortActivitiesByStart(activityMap[key] ?? []),
		}
	})
}
