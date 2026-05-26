const discordInviteHosts = new Set([
	"discord.gg",
	"www.discord.gg",
	"discord.com",
	"www.discord.com",
	"discordapp.com",
	"www.discordapp.com",
	"ptb.discord.com",
	"canary.discord.com",
]);

const inviteCodePattern = /^[A-Za-z0-9-]+$/;

export function isValidDiscordInviteUrl(value: string): boolean {
	let url: URL;

	try {
		url = new URL(value);
	} catch {
		return false;
	}

	const host = url.hostname.toLowerCase();
	const path = url.pathname.replace(/^\/+|\/+$/g, "");

	if (!discordInviteHosts.has(host) || path === "") {
		return false;
	}

	if (host === "discord.gg" || host === "www.discord.gg") {
		return inviteCodePattern.test(path);
	}

	return /^invite\/[A-Za-z0-9-]+$/.test(path);
}
