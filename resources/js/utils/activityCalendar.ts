import type { ActivityCalendarDay, ActivityIndexItem } from "@/Types/ActivityCore"

export const createMonthStart = (date: Date) => new Date(date.getFullYear(), date.getMonth(), 1)

export const toLocalDateKey = (date: Date) => {
	const year = date.getFullYear()
	const month = `${date.getMonth() + 1}`.padStart(2, '0')
	const day = `${date.getDate()}`.padStart(2, '0')

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

export const groupActivitiesByLocalDate = (activities: ActivityIndexItem[]) => {
	return activities.reduce<Record<string, ActivityIndexItem[]>>((map, activity) => {
		if (!activity.starts_at) {
			return map
		}

		const key = toLocalDateKey(new Date(activity.starts_at))
		map[key] ??= []
		map[key].push(activity)

		return map
	}, {})
}

export const buildMonthCalendarDays = (
	activityMap: Record<string, ActivityIndexItem[]>,
	monthCursor: Date,
): ActivityCalendarDay[] => {
	const monthStart = createMonthStart(monthCursor)
	const startOffset = (monthStart.getDay() + 6) % 7
	const rangeStart = new Date(monthStart.getFullYear(), monthStart.getMonth(), 1 - startOffset)
	const todayKey = toLocalDateKey(new Date())

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
): ActivityCalendarDay[] => {
	const startOffset = (anchorDate.getDay() + 6) % 7
	const rangeStart = new Date(anchorDate.getFullYear(), anchorDate.getMonth(), anchorDate.getDate() - startOffset)
	const todayKey = toLocalDateKey(new Date())

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
