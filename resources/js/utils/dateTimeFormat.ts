export type DateTimeLocale = string | null | undefined;

export const resolveDateTimeLocale = (locale?: DateTimeLocale): string => {
	const normalizedLocale = typeof locale === "string" ? locale.trim() : "";

	if (normalizedLocale === "") {
		return "en-GB";
	}

	const lowerLocale = normalizedLocale.toLowerCase();

	if (lowerLocale === "en" || lowerLocale.startsWith("en-")) {
		return "en-GB";
	}

	return normalizedLocale;
};

export const createDateTimeFormatter = (
	locale: DateTimeLocale,
	options?: Intl.DateTimeFormatOptions,
): Intl.DateTimeFormat => {
	return new Intl.DateTimeFormat(resolveDateTimeLocale(locale), options);
};

export const createRelativeTimeFormatter = (
	locale: DateTimeLocale,
	options?: Intl.RelativeTimeFormatOptions,
): Intl.RelativeTimeFormat => {
	return new Intl.RelativeTimeFormat(resolveDateTimeLocale(locale), options);
};
