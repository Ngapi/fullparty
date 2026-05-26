<script setup lang="ts">
import type { RunDiscoveryResultItemData } from "../../Types/RunDiscovery";
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const props = defineProps<{
	item: RunDiscoveryResultItemData
}>();

const { t, locale } = useI18n();

const roleSlotIconUrls: Record<string, string> = {
	tank: "/role-icons/tank.png",
	healer: "/role-icons/healer.png",
	dps: "/role-icons/dps.png",
};

const contentName = computed(() => props.item.activity_type_name);
const showContentName = computed(() => contentName.value !== "" && contentName.value !== props.item.title);

const descriptionLabel = computed(() => props.item.description || t("groups.activities.overview.details.no_description"));

const tagLabels = computed(() => {
	const tags: string[] = [];

	if (props.item.run_style) {
		tags.push(t(`runs.discovery.filters.options.run_styles.${props.item.run_style}`));
	}

	if (props.item.intensity) {
		tags.push(t(`runs.discovery.filters.options.intensity.${props.item.intensity}`));
	}

	if (props.item.beginner_friendly) {
		tags.push(t("runs.discovery.filters.labels.beginner_friendly"));
	}

	if (props.item.min_item_level) {
		tags.push(`ILVL ${props.item.min_item_level}+`);
	}

	return tags;
});

const startsAtDate = computed(() => props.item.starts_at ? new Date(props.item.starts_at) : null);
const nowDate = computed(() => new Date());

const scheduleLabel = computed(() => {
	if (!startsAtDate.value) {
		return t("groups.activities.cards.no_time");
	}

	const start = startsAtDate.value;
	const now = nowDate.value;
	const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
	const startDay = new Date(start.getFullYear(), start.getMonth(), start.getDate());
	const diffDays = Math.round((startDay.getTime() - todayStart.getTime()) / 86400000);

	if (diffDays === 0) {
		return t("runs.discovery.results.placeholder_item.schedule.today");
	}

	if (diffDays === 1) {
		return t("runs.discovery.results.placeholder_item.schedule.tomorrow");
	}

	return new Intl.DateTimeFormat(locale.value, {
		weekday: "short",
		day: "numeric",
		month: "short",
	}).format(start);
});

const timeLabel = computed(() => {
	if (!startsAtDate.value) {
		return "—";
	}

	return new Intl.DateTimeFormat(locale.value, {
		hour: "numeric",
		minute: "2-digit",
	}).format(startsAtDate.value);
});

const timezoneLabel = computed(() => {
	if (!startsAtDate.value) {
		return Intl.DateTimeFormat().resolvedOptions().timeZone;
	}

	const parts = new Intl.DateTimeFormat(locale.value, {
		timeZoneName: "short",
	}).formatToParts(startsAtDate.value);

	return parts.find((part) => part.type === "timeZoneName")?.value
		|| Intl.DateTimeFormat().resolvedOptions().timeZone;
});

const memberCountLabel = computed(() => `${props.item.filled_slots} / ${props.item.total_slots}`);

const goToViewDetails = () => {
	router.get(props.item.links.view);
};

const goToApply = () => {
	if (!props.item.links.apply) {
		return;
	}

	router.get(props.item.links.apply);
};

const goToGroup = () => {
	if (!props.item.group_slug) {
		return;
	}

	router.get(route("groups.index", {
		locale: locale.value,
		group: props.item.group_slug,
	}));
};
</script>

<template>
	<article
		class="h-38 overflow-hidden border border-white/10 bg-neutral-950/72 shadow-[0_20px_40px_rgba(0,0,0,0.2)]"
		:class="props.item.has_existing_application ? 'border-l-4 border-l-brand-400' : ''"
	>
		<div class="grid gap-4 xl:grid-cols-[7rem_minmax(0,1.6fr)_11rem_10rem_11rem] xl:items-center">
			<div class="border border-white/8 bg-neutral-900/70">
				<img
					v-if="item.image_url"
					:src="item.image_url"
					:alt="item.title"
					class="h-38 w-34 object-cover"
				>
				<div
					v-else
					class="h-38 w-34 bg-[radial-gradient(circle_at_top_left,rgba(123,97,153,0.34),transparent_46%),radial-gradient(circle_at_center_right,rgba(84,136,184,0.28),transparent_38%),linear-gradient(180deg,#201c24_0%,#151217_100%)]"
				/>
			</div>

			<div class="min-w-0 space-y-3 xl:pr-2 py-4">
				<div class="flex items-start justify-between gap-3">
					<div class="min-w-0 space-y-2">
						<div class="flex flex-wrap items-center gap-2">
							<h3 class="text-xl font-semibold leading-tight text-white">
								{{ item.title }}
							</h3>
						</div>

						<div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-white/70">
							<div v-if="showContentName" class="flex items-center gap-2">
								<UIcon name="i-lucide-shield" class="size-4 text-brand-300" />
								<span>{{ contentName }}</span>
							</div>

							<button
								v-if="item.group_name && item.group_slug"
								type="button"
								class="flex cursor-pointer items-center gap-2 text-left transition-colors hover:text-white"
								@click="goToGroup"
							>
								<UIcon name="i-lucide-users" class="size-4 text-white/50" />
								<span>{{ item.group_name }}</span>
							</button>
							<div v-else-if="item.group_name" class="flex items-center gap-2">
								<UIcon name="i-lucide-users" class="size-4 text-white/50" />
								<span>{{ item.group_name }}</span>
							</div>
						</div>
					</div>

					<UButton
						color="neutral"
						variant="ghost"
						icon="i-lucide-bookmark"
						class="shrink-0 rounded-none text-white/50 hover:text-white"
					/>
				</div>

				<p class="max-w-2xl text-sm leading-6 text-white/68">
					{{ descriptionLabel }}
				</p>

				<div v-if="tagLabels.length > 0" class="flex flex-wrap gap-2">
					<UBadge
						v-for="tag in tagLabels"
						:key="tag"
						color="neutral"
						variant="outline"
						class="rounded-none border-white/12 bg-neutral-950/65 px-2.5 py-1 text-[11px] uppercase tracking-[0.12em] text-white/74"
						:label="tag"
					/>
				</div>
			</div>

			<div class="space-y-3 border-t border-white/8 pt-3 xl:border-l xl:border-t-0 xl:pt-0 xl:pl-5">
				<div class="flex items-start gap-3">
					<UIcon name="i-lucide-calendar-days" class="mt-0.5 size-4 text-white/46" />
					<div class="space-y-1">
						<p class="text-sm font-medium text-white">
							{{ scheduleLabel }}
						</p>
						<p class="text-2xl font-semibold leading-none text-white">
							{{ timeLabel }}
						</p>
						<p class="text-sm uppercase tracking-[0.18em] text-white/46">
							{{ timezoneLabel }}
						</p>
					</div>
				</div>

				<div class="flex items-start gap-3 text-white/70">
					<UIcon name="i-lucide-globe" class="mt-0.5 size-4 text-white/46" />
					<div class="space-y-1">
						<p class="text-base font-medium text-white">
							{{ item.datacenter || "—" }}
						</p>
						<p v-if="item.world" class="text-sm">
							{{ item.world }}
						</p>
					</div>
				</div>
			</div>

			<div class="border-t border-white/8 pt-3 xl:border-l xl:border-t-0 xl:pt-0 xl:pl-5">
				<div class=" p-3">
					<p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-white/48">
						{{ t("runs.discovery.results.placeholder_item.open_slots") }}
					</p>

					<div class="grid gap-3">
						<div
							v-for="roleSlot in item.role_slots"
							:key="roleSlot.key"
							class="grid grid-cols-[1.25rem_1fr_auto] items-center gap-2"
						>
							<img
								:src="roleSlotIconUrls[roleSlot.key]"
								:alt="roleSlot.key"
								class="size-5 object-contain"
							>
							<div class="h-px bg-white/10" />
							<span class="text-sm font-medium text-white/78">{{ roleSlot.count > 0 ? roleSlot.count : "—" }}</span>
						</div>
					</div>
				</div>
			</div>

			<div class="border-t border-white/8 pt-3 xl:border-l xl:border-t-0 xl:pt-0 xl:pl-5">
				<div class="flex h-full flex-col justify-between gap-4">
					<div class="flex items-center justify-center">
						<p class="text-md font-semibold text-white">
							{{ memberCountLabel }} {{ t("general.members") }}
						</p>
					</div>

					<div class="space-y-2 pr-4">
						<UButton
							color="primary"
							class="w-full justify-center rounded-none"
							:label="t('runs.discovery.results.placeholder_item.actions.view_details')"
							@click="goToViewDetails"
						/>
						<UButton
							v-if="item.links.apply && (item.can_apply || item.has_existing_application)"
							color="neutral"
							variant="outline"
							class="w-full justify-center rounded-none border-brand-400/45 text-white hover:bg-brand-500/10"
							:label="item.has_existing_application
								? t('runs.discovery.results.placeholder_item.actions.view_application')
								: t('runs.discovery.results.placeholder_item.actions.apply_now')"
							@click="goToApply"
						/>
					</div>
				</div>
			</div>
		</div>
	</article>
</template>
