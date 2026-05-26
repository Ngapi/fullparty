import { createRelativeTimeFormatter } from "@/utils/dateTimeFormat";

export function formatRelativeTime(
	value: string | null,
	locale: string,
	justNowLabel: string,
	fallbackLabel: string,
) {
	if (!value) {
		return fallbackLabel;
	}

	const target = new Date(value).getTime();
	const now = Date.now();
	const diffMs = target - now;
	const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
		["year", 1000 * 60 * 60 * 24 * 365],
		["month", 1000 * 60 * 60 * 24 * 30],
		["day", 1000 * 60 * 60 * 24],
		["hour", 1000 * 60 * 60],
		["minute", 1000 * 60],
	];

	for (const [unit, threshold] of units) {
		if (Math.abs(diffMs) >= threshold) {
			return createRelativeTimeFormatter(locale, { numeric: "auto" }).format(
				Math.round(diffMs / threshold),
				unit,
			);
		}
	}

	return justNowLabel;
}
