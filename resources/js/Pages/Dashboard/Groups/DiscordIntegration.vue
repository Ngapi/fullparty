<script setup lang="ts">
import AccessBadge from "@/components/Groups/AccessBadge.vue";
import PageHeader from "@/components/PageHeader.vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "@nuxt/ui/composables";
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

type GroupPayload = {
	id: number
	name: string
	slug: string
	current_user_role: string | null
	permissions: {
		can_manage_group: boolean
	}
	discord_link_token_expires_at: string | null
}

type DiscordGuildIntegration = {
	id: number
	discord_guild_id: string
	name: string | null
	icon_url: string | null
	permissions: string | null
	guild_installed_at: string | null
	updated_at: string | null
}

type LinkToken = {
	token: string
	expires_at: string
}

const props = defineProps<{
	group: GroupPayload
	integration: DiscordGuildIntegration | null
	inviteUrl: string
}>();

const { t } = useI18n();
const page = usePage();
const toast = useToast();
const linkToken = ref<LinkToken | null>(null);
const generatingToken = ref(false);

const hasActiveToken = computed(() => {
	if (linkToken.value) {
		return true;
	}

	if (!props.group.discord_link_token_expires_at) {
		return false;
	}

	return new Date(props.group.discord_link_token_expires_at).getTime() > Date.now();
});

const tokenExpiresAt = computed(() => linkToken.value?.expires_at ?? props.group.discord_link_token_expires_at);

const generateToken = () => {
	generatingToken.value = true;

	router.post(route("groups.dashboard.discord-integration.link-token", props.group.slug), {}, {
		preserveScroll: true,
		onFinish: () => {
			generatingToken.value = false;
		},
	});
};

const copyToken = async () => {
	if (!linkToken.value) {
		return;
	}

	await navigator.clipboard.writeText(linkToken.value.token);

	toast.add({
		title: t("groups.discord.toasts.token_copied"),
		color: "success",
		icon: "i-lucide-copy-check",
	});
};

watch(
	() => page.props.flash?.data?.discord_guild_link_token,
	(value) => {
		linkToken.value = (value as LinkToken | null) ?? null;
	},
	{ immediate: true },
);
</script>

<template>
	<div class="w-full">
		<PageHeader
			:title="t('groups.discord.title')"
			:subtitle="t('groups.discord.subtitle', { group: group.name })"
		>
			<AccessBadge
				:role="group.current_user_role"
				fallback-role="owner"
			/>
		</PageHeader>

		<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(340px,420px)]">
			<UCard>
				<template #header>
					<div class="flex items-center gap-3">
						<div class="flex size-10 items-center justify-center border border-brand-400/25 bg-brand-500/10 text-brand">
							<UIcon name="ic:baseline-discord" class="size-5" />
						</div>
						<div>
							<h2 class="text-base font-semibold text-highlighted">{{ t("groups.discord.install.title") }}</h2>
							<p class="text-sm text-muted">{{ t("groups.discord.install.subtitle") }}</p>
						</div>
					</div>
				</template>

				<div class="space-y-4 text-sm text-muted">
					<p>{{ t("groups.discord.install.description") }}</p>

					<div class="flex flex-wrap gap-3">
						<UButton
							:href="inviteUrl"
							icon="i-lucide-external-link"
							color="primary"
							variant="solid"
						>
							{{ t("groups.discord.actions.invite") }}
						</UButton>
						<UButton
							icon="i-lucide-key-round"
							color="neutral"
							variant="soft"
							:loading="generatingToken"
							@click="generateToken"
						>
							{{ t("groups.discord.actions.generate_token") }}
						</UButton>
					</div>
				</div>
			</UCard>

			<UCard>
				<template #header>
					<div class="flex items-center justify-between gap-3">
						<div>
							<h2 class="text-base font-semibold text-highlighted">{{ t("groups.discord.status.title") }}</h2>
							<p class="text-sm text-muted">{{ t("groups.discord.status.subtitle") }}</p>
						</div>
						<UBadge
							:color="integration ? 'success' : 'neutral'"
							variant="subtle"
						>
							{{ integration ? t("groups.discord.status.linked") : t("groups.discord.status.not_linked") }}
						</UBadge>
					</div>
				</template>

				<div v-if="integration" class="space-y-3 text-sm">
					<div class="flex items-center gap-3">
						<img
							v-if="integration.icon_url"
							:src="integration.icon_url"
							class="size-12 border border-white/10 object-cover"
							alt=""
						>
						<div v-else class="flex size-12 items-center justify-center border border-white/10 bg-muted text-muted">
							<UIcon name="i-lucide-server" class="size-5" />
						</div>
						<div class="min-w-0">
							<p class="truncate font-semibold text-highlighted">{{ integration.name ?? t("groups.discord.status.unknown_guild") }}</p>
							<p class="truncate text-muted">{{ integration.discord_guild_id }}</p>
						</div>
					</div>
				</div>

				<div v-else class="text-sm text-muted">
					{{ t("groups.discord.status.empty") }}
				</div>
			</UCard>

			<UCard class="lg:col-span-2">
				<template #header>
					<div class="flex items-center gap-3">
						<div class="flex size-10 items-center justify-center border border-white/10 bg-muted text-muted">
							<UIcon name="i-lucide-link" class="size-5" />
						</div>
						<div>
							<h2 class="text-base font-semibold text-highlighted">{{ t("groups.discord.link.title") }}</h2>
							<p class="text-sm text-muted">{{ t("groups.discord.link.subtitle") }}</p>
						</div>
					</div>
				</template>

				<div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
					<div class="space-y-3">
						<p class="text-sm text-muted">{{ t("groups.discord.link.description") }}</p>

						<div
							v-if="linkToken"
							class="flex flex-col gap-3 border border-brand-400/25 bg-brand-500/10 p-4 md:flex-row md:items-center md:justify-between"
						>
							<div class="min-w-0">
								<p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ t("groups.discord.link.generated_token") }}</p>
								<p class="mt-1 break-all font-mono text-lg font-semibold text-highlighted">{{ linkToken.token }}</p>
								<p class="mt-1 text-xs text-muted">{{ t("groups.discord.link.expires_at", { date: new Date(linkToken.expires_at).toLocaleString() }) }}</p>
							</div>
							<UButton
								icon="i-lucide-copy"
								color="neutral"
								variant="soft"
								@click="copyToken"
							>
								{{ t("groups.discord.actions.copy_token") }}
							</UButton>
						</div>

						<UAlert
							v-else-if="hasActiveToken"
							color="info"
							variant="soft"
							icon="i-lucide-clock"
							:title="t('groups.discord.link.active_token_title')"
							:description="t('groups.discord.link.active_token_description', { date: tokenExpiresAt ? new Date(tokenExpiresAt).toLocaleString() : '' })"
						/>

						<UAlert
							v-else
							color="neutral"
							variant="soft"
							icon="i-lucide-info"
							:title="t('groups.discord.link.no_token_title')"
							:description="t('groups.discord.link.no_token_description')"
						/>
					</div>

					<UButton
						icon="i-lucide-key-round"
						color="primary"
						variant="solid"
						:loading="generatingToken"
						@click="generateToken"
					>
						{{ t("groups.discord.actions.generate_token") }}
					</UButton>
				</div>
			</UCard>
		</div>
	</div>
</template>
