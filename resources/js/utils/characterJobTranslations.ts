type Translator = (key: string) => string;

const normalizeTranslationKey = (value: unknown): string => String(value ?? "")
	.trim()
	.toLowerCase()
	.replace(/^phantom\s+/, "")
	.replace(/&/g, "and")
	.replace(/[^a-z0-9]+/g, "_")
	.replace(/^_+|_+$/g, "");

export const characterClassTranslationKey = (characterClass: { shorthand?: string | null } | null | undefined): string | null => {
	const key = normalizeTranslationKey(characterClass?.shorthand);

	return key === "" ? null : `characters.jobs.classes.${key}`;
};

export const phantomJobTranslationKey = (phantomJob: { name?: string | null } | null | undefined): string | null => {
	const key = normalizeTranslationKey(phantomJob?.name);

	return key === "" ? null : `characters.jobs.phantom.${key}`;
};

export const translateJobName = (t: Translator, key: string | null, fallback: string | null | undefined): string => {
	if (!key) {
		return fallback ?? "";
	}

	const translated = t(key);

	return translated === key ? (fallback ?? "") : translated;
};
