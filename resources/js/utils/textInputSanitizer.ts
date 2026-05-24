const disallowedFormatCharacters = /\p{Cf}+/gu;
const disallowedSingleLineControlCharacters = /[\u0000-\u001F\u007F-\u009F]+/gu;
const disallowedMultilineControlCharacters = /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F-\u009F]+/gu;
const unicodeSeparators = /\p{Z}+/gu;

const normalizeUnicode = (value: string) => value.normalize("NFC");

export const sanitizeSingleLineTextForInput = (value: string) => normalizeUnicode(value)
	.replace(/\r\n?/gu, "\n")
	.replace(disallowedFormatCharacters, "")
	.replace(disallowedSingleLineControlCharacters, " ")
	.replace(unicodeSeparators, " ")
	.replace(/ {2,}/gu, " ");

export const sanitizeSingleLineText = (value: string) => normalizeUnicode(value)
	.replace(/\r\n?/gu, "\n")
	.replace(disallowedFormatCharacters, "")
	.replace(disallowedSingleLineControlCharacters, " ")
	.replace(unicodeSeparators, " ")
	.replace(/\s+/gu, " ")
	.trim();

export const sanitizeMultilineTextForInput = (value: string) => normalizeUnicode(value)
	.replace(/\r\n?/gu, "\n")
	.replace(disallowedFormatCharacters, "")
	.replace(disallowedMultilineControlCharacters, "")
	.replace(unicodeSeparators, " ")
	.split("\n")
	.map((line) => line.replace(/[^\S\n]{2,}/gu, " "))
	.join("\n");

export const sanitizeMultilineText = (value: string) => normalizeUnicode(value)
	.replace(/\r\n?/gu, "\n")
	.replace(disallowedFormatCharacters, "")
	.replace(disallowedMultilineControlCharacters, "")
	.replace(unicodeSeparators, " ")
	.split("\n")
	.map((line) => line.replace(/[^\S\n]+/gu, " ").trim())
	.join("\n")
	.trim();
