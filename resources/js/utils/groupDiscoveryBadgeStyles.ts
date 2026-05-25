const HEX_COLOR_PATTERN = /^#([0-9a-f]{6}|[0-9a-f]{3})$/i;

type Rgb = {
	r: number
	g: number
	b: number
}

function normalizeHex(color: string) {
	if (!HEX_COLOR_PATTERN.test(color)) {
		return null;
	}

	if (color.length === 4) {
		return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`.toUpperCase();
	}

	return color.toUpperCase();
}

function hexToRgb(color: string): Rgb | null {
	const normalized = normalizeHex(color);

	if (!normalized) {
		return null;
	}

	return {
		r: Number.parseInt(normalized.slice(1, 3), 16),
		g: Number.parseInt(normalized.slice(3, 5), 16),
		b: Number.parseInt(normalized.slice(5, 7), 16),
	};
}

function badgeTextColor() {
	return "#F8FAFC";
}

export function groupDiscoveryBadgeStyle(color: string | null | undefined) {
	const rgb = color ? hexToRgb(color) : null;

	if (!rgb || !color) {
		return undefined;
	}

	return {
		backgroundColor: `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.18)`,
		borderColor: `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.42)`,
		color: badgeTextColor(),
		boxShadow: `inset 0 0 0 1px rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.08)`,
	};
}
