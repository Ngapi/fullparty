import { localizedValue } from "@/utils/localizedValue"
import type { ActivityCompletionSummary, ActivityCompletionSummarySource } from "@/Types/ActivityProgression"

type TranslateFunction = (key: string, params?: Record<string, unknown>) => string

type BuildActivityCompletionSummaryOptions = {
	activity: ActivityCompletionSummarySource | null | undefined
	locale: string
	fallbackLocale: string
	t: TranslateFunction
}

export const buildActivityCompletionSummary = ({
	activity,
	locale,
	fallbackLocale,
	t,
}: BuildActivityCompletionSummaryOptions): ActivityCompletionSummary | null => {
	if (!activity || activity.status !== "complete") {
		return null
	}

	const hasProgressionSchema = activity.prog_points.length > 0 || activity.progress_milestones.length > 0

	if (!hasProgressionSchema) {
		return null
	}

	const furthestProgPoint = activity.prog_points.find((progPoint) => progPoint.key === activity.furthest_progress_key)
	const milestones = [...activity.progress_milestones]
		.sort((left, right) => left.sort_order - right.sort_order)
		.filter((milestone) => milestone.kills > 0 || milestone.best_progress_percent !== null)
		.map((milestone) => ({
			key: milestone.milestone_key,
			label: localizedValue(milestone.milestone_label, locale, fallbackLocale) || milestone.milestone_key,
			kills: milestone.kills,
			bestProgressPercent: milestone.best_progress_percent,
		}))

	return {
		completedAt: activity.completed_at,
		sourceLabel: activity.progress_entry_mode
			? t(`groups.activities.management.complete_activity_modal.methods.${activity.progress_entry_mode}`)
			: t("groups.activities.management.overview.progression.not_recorded"),
		furthestPointLabel: furthestProgPoint
			? (localizedValue(furthestProgPoint.label, locale, fallbackLocale) || furthestProgPoint.key)
			: null,
		bestProgressPercent: activity.furthest_progress_percent,
		progressLinkUrl: activity.progress_link_url,
		notes: activity.progress_notes,
		milestones,
	}
}
