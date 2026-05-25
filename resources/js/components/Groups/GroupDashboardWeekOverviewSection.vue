<script setup lang="ts">
import type { GroupDashboardActivity } from "@/Types/Groups";
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { localizedValue } from "@/utils/localizedValue";
import { getActivityStatusBorderClass, getActivityStatusMeta } from "@/utils/activityStatusMeta";

const props = defineProps<{
	activities: GroupDashboardActivity[]
	weekStartDate: string
	weekEndDate: string
}>();

const { t, locale } = useI18n();
const page = usePage();
const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? "en"));

const parseDateKey = (value: string) => {
	const [year, month, day] = value.split("-").map((segment) => Number.parseInt(segment, 10));

	return new Date(year, (month ?? 1) - 1, day ?? 1);
};

const toDateKey = (date: Date) => {
	const year = date.getFullYear();
	const month = `${date.getMonth() + 1}`.padStart(2, "0");
	const day = `${date.getDate()}`.padStart(2, "0");

	return `${year}-${month}-${day}`;
};

const weekStart = computed(() => parseDateKey(props.weekStartDate));
const weekEnd = computed(() => parseDateKey(props.weekEndDate));

const weekRangeLabel = computed(() => {
	const start = weekStart.value;
	const end = weekEnd.value;

	if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) {
		const monthLabel = new Intl.DateTimeFormat(locale.value, {
			month: "long",
		}).format(start);

		return `${monthLabel} ${start.getDate()} - ${end.getDate()}`;
	}

	return `${new Intl.DateTimeFormat(locale.value, {
		month: "short",
		day: "numeric",
	}).format(start)} - ${new Intl.DateTimeFormat(locale.value, {
		month: "short",
		day: "numeric",
	}).format(end)}`;
});

const activityMap = computed(() => {
	return props.activities.reduce<Record<string, GroupDashboardActivity[]>>((map, activity) => {
		if (! activity.starts_at) {
			return map;
		}

		const key = toDateKey(new Date(activity.starts_at));
		map[key] ??= [];
		map[key].push(activity);

		return map;
	}, {});
});

const weekDays = computed(() => {
	return Array.from({ length: 7 }, (_, index) => {
		const date = new Date(
			weekStart.value.getFullYear(),
			weekStart.value.getMonth(),
			weekStart.value.getDate() + index,
		);
		const key = toDateKey(date);

		return {
			key,
			date,
			activities: (activityMap.value[key] ?? []).slice().sort((left, right) => {
				return new Date(left.starts_at ?? 0).getTime() - new Date(right.starts_at ?? 0).getTime();
			}),
		};
	});
});

const activityTypeName = (activity: GroupDashboardActivity) => localizedValue(
	activity.activity_type?.draft_name,
	locale.value,
	fallbackLocale.value,
) || activity.activity_type?.slug || t("groups.activities.cards.unknown_type");

const activityLabel = (activity: GroupDashboardActivity) => activity.title || activityTypeName(activity);

const activitySubtitle = (activity: GroupDashboardActivity) => (
	activity.title && activity.title !== activityTypeName(activity)
		? activityTypeName(activity)
		: null
);

const activityStartsAtLabel = (activity: GroupDashboardActivity) => {
	if (! activity.starts_at) {
		return t("groups.activities.cards.no_time");
	}

	return new Intl.DateTimeFormat(locale.value, {
		hour: "2-digit",
		minute: "2-digit",
	}).format(new Date(activity.starts_at));
};
</script>

<template>
	<section class="">
		<div class="overflow-hidden">
			<div class="flex flex-col gap-4 border-b border-white/8 px-5 py-5 sm:flex-row sm:items-start sm:justify-between">
				<div class="flex flex-col gap-1">
					<h2 class="text-lg font-semibold text-white">
						{{ t("groups.dashboard.week_overview.title") }}
					</h2>
					<p class="max-w-2xl text-sm leading-6 text-white/62">
						{{ t("groups.dashboard.week_overview.subtitle") }}
					</p>
				</div>

				<div class="flex flex-wrap items-center gap-2">
					<UBadge
						color="neutral"
						variant="subtle"
						:label="weekRangeLabel"
					/>
					<UBadge
						color="primary"
						variant="soft"
						:label="t('groups.dashboard.week_overview.count', { count: activities.length })"
					/>
				</div>
			</div>

			<div class="overflow-x-auto">
				<div class="grid min-w-[56rem] grid-cols-7 gap-px bg-white/8">
					<div
						v-for="day in weekDays"
						:key="day.key"
						class="flex min-h-[17rem] flex-col bg-neutral-950/72"
					>
						<div class="flex items-center justify-between border-b border-white/8 px-3 py-3">
							<div class="flex flex-col">
								<p class="text-[11px] font-medium uppercase tracking-[0.16em] text-white/44">
									{{ new Intl.DateTimeFormat(locale, { weekday: "short" }).format(day.date) }}
								</p>
								<p class="mt-1 text-sm font-semibold text-white">
									{{ new Intl.DateTimeFormat(locale, { day: "numeric", month: "short" }).format(day.date) }}
								</p>
							</div>

							<span
								class="inline-flex min-w-8 items-center justify-center border border-white/10 px-2 py-1 text-[11px] font-medium text-white/58"
							>
								{{ day.activities.length }}
							</span>
						</div>

						<div class="flex flex-1 flex-col gap-2 p-3">
							<Link
								v-for="activity in day.activities"
								:key="activity.id"
								:href="activity.links.view"
								class="group overflow-hidden border border-white/10 border-t-2 bg-white/[0.03] transition hover:border-primary/30 hover:bg-primary/6"
								:class="getActivityStatusBorderClass(activity.status)"
							>
								<div class="min-w-0 p-3">
									<div class="flex items-start justify-between gap-2">
										<p class="text-xs font-medium text-white/66">
											{{ activityStartsAtLabel(activity) }}
										</p>
										<UBadge
											:label="t(`groups.activities.statuses.${activity.status}`)"
											:color="getActivityStatusMeta(activity.status).color"
											variant="soft"
											size="xs"
										/>
									</div>

									<p class="mt-1 text-sm font-semibold leading-5 text-white break-words [overflow-wrap:anywhere]">
										{{ activityLabel(activity) }}
									</p>
									<p
										v-if="activitySubtitle(activity)"
										class="mt-1 text-xs leading-5 text-white/54 break-words [overflow-wrap:anywhere]"
									>
										{{ activitySubtitle(activity) }}
									</p>
								</div>
							</Link>
						</div>
					</div>
				</div>
			</div>

			<div
				v-if="activities.length === 0"
				class="border-t border-white/8 px-5 py-4 text-sm text-white/52"
			>
				{{ t("groups.dashboard.week_overview.empty") }}
			</div>
		</div>
	</section>
</template>
