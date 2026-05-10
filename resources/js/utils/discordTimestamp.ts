export type DiscordTimestampStyle = "t" | "T" | "d" | "D" | "f" | "F" | "R"

const UTC_OFFSET_PATTERN = /(?:Z|[+-]\d{2}:?\d{2})$/i;

const normalizeUtcInput = (value: string | number | Date) => {
	if (typeof value !== "string") {
		return value;
	}

	const trimmedValue = value.trim();

	if (UTC_OFFSET_PATTERN.test(trimmedValue)) {
		return trimmedValue;
	}

	return `${trimmedValue}Z`;
};

export const utcToDiscordTimestamp = (
	utcDate: string | number | Date,
	style: DiscordTimestampStyle = "F",
): string | null => {
	const timestamp = new Date(normalizeUtcInput(utcDate)).getTime();

	if (!Number.isFinite(timestamp)) {
		return null;
	}

	return `<t:${Math.floor(timestamp / 1000)}:${style}>`;
};
