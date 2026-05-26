<script setup lang="ts">
import type {
	GroupDiscoveryContentItem,
	GroupDiscoveryContentStatusCount,
	GroupDiscoveryContentSummary,
} from "@/Types/Groups";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { formatRelativeTime } from "@/utils/formatRelativeTime";

const props = defineProps<{
	summary: GroupDiscoveryContentSummary
	items: GroupDiscoveryContentItem[]
}>();

const { locale, t } = useI18n();

const totalRuns = computed(() => props.summary.total_runs);

const statusItems = computed(() => props.summary.status_breakdown.map((item) => ({
	...item,
	label: t(`groups.dashboard.content.statuses.${item.status}`),
	width: resolveStatusWidth(item),
})));

function resolveStatusWidth(item: GroupDiscoveryContentStatusCount) {
	if (totalRuns.value <= 0) {
		return "0%";
	}

	return `${Math.max((item.count / totalRuns.value) * 100, item.count > 0 ? 6 : 0)}%`;
}

function resolveStatusClasses(status: GroupDiscoveryContentStatusCount["status"]) {
	switch (status) {
		case "draft":
			return "bg-sky-300/80";
		case "scheduled":
			return "bg-violet-300/80";
		case "active":
			return "bg-amber-300/85";
		case "complete":
			return "bg-emerald-300/85";
		case "cancelled":
			return "bg-rose-300/80";
	}
}

function formatCountLabel(count: number) {
	return count === 1
		? t("groups.dashboard.content.run_singular")
		: t("groups.dashboard.content.run_plural");
}

function formatRunTime(value: string | null) {
	return formatRelativeTime(
		value,
		locale.value,
		t("groups.dashboard.labels.just_now"),
		t("groups.dashboard.labels.not_available"),
	);
}
</script>

<template>
	<section class="">
		<div class="overflow-hidden ">
			<div class="flex flex-col gap-4 px-5 sm:flex-row sm:items-start sm:justify-between">
				<div class="flex flex-col gap-1">
					<h2 class="text-lg font-semibold text-white">
						{{ t("groups.dashboard.content.title") }}
					</h2>
					<p class="max-w-2xl text-sm leading-6 text-white/62">
						{{ t("groups.dashboard.content.subtitle") }}
					</p>
				</div>

				<UBadge
					color="neutral"
					variant="subtle"
					:label="t('groups.dashboard.content.total_runs', { count: summary.total_runs })"
				/>
			</div>

			<div class="flex items-stretch justify-evenly gap-4 py-5 lg:grid-cols-[17rem,1fr]">
				<div class="w-full border border-white/10 bg-white/[0.03] p-5">
					<p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/44">
						{{ t("groups.dashboard.content.visible_runs") }}
					</p>
					<p class="mt-3 text-4xl font-semibold text-white">
						{{ summary.total_runs }}
					</p>
				</div>

				<div class="w-full flex flex-col items-center justify-center px-5 overflow-hidden border border-white/10 bg-white/[0.03]">
					<div class="flex h-3 w-full overflow-hidden bg-neutral-900/80">
						<div
							v-for="item in statusItems"
							:key="item.status"
							class="h-full transition-[width]"
							:class="resolveStatusClasses(item.status)"
							:style="{ width: item.width }"
						/>
					</div>

					<div class="grid gap-3 px-4 py-4 sm:grid-cols-2 xl:grid-cols-5">
						<div
							v-for="item in statusItems"
							:key="item.status"
							class="flex items-center gap-3"
						>
							<span
								class="size-2.5 shrink-0 rounded-full"
								:class="resolveStatusClasses(item.status)"
							/>
							<div class="min-w-0">
								<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/44">
									{{ item.label }}
								</p>
								<p class="mt-1 text-sm text-white/76">
									{{ item.count }}
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="">
				<div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
					<p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/44">
						{{ t("groups.dashboard.content.library") }}
					</p>
					<p class="text-xs text-white/52">
						{{ t("groups.dashboard.content.library_hint") }}
					</p>
				</div>

				<div v-if="items.length > 0" class="grid gap-4 xl:grid-cols-2">
					<article
						v-for="item in items"
						:key="item.key"
						class="overflow-hidden border border-white/10 bg-white/[0.03]"
					>
						<div class="relative h-32 overflow-hidden border-b border-white/10 bg-neutral-900">
							<img
								v-if="item.activity_image_url"
								:src="item.activity_image_url"
								:alt="item.activity_name"
								class="absolute inset-0 size-full object-cover"
							>
							<div
								v-else
								class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(123,97,153,0.34),transparent_46%),radial-gradient(circle_at_center_right,rgba(84,136,184,0.28),transparent_38%),linear-gradient(180deg,#201c24_0%,#151217_100%)]"
							/>
							<div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/45 to-transparent" />

							<div class="absolute inset-x-0 bottom-0 p-4">
								<div class="flex items-end justify-between gap-3">
									<div class="min-w-0">
										<p class="text-sm font-semibold text-white break-words [overflow-wrap:anywhere]">
											{{ item.activity_name }}
										</p>
										<p class="mt-1 text-xs uppercase tracking-[0.12em] text-white/62">
											{{ item.total_runs }} {{ formatCountLabel(item.total_runs) }}
										</p>
									</div>
								</div>
							</div>
						</div>

						<div class="space-y-4 p-4">
							<div class="flex flex-wrap gap-2">
								<UBadge
									color="neutral"
									variant="soft"
									:label="t('groups.dashboard.content.completed', { count: item.completed_runs })"
								/>
								<UBadge
									color="primary"
									variant="soft"
									:label="t('groups.dashboard.content.active', { count: item.active_runs })"
								/>
							</div>

							<div class="grid gap-3 sm:grid-cols-2">
								<div class="border border-white/8 bg-neutral-950/45 px-3 py-3">
									<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/42">
										{{ t("groups.dashboard.content.last_run") }}
									</p>
									<p class="mt-2 text-sm text-white/78">
										{{ item.last_run_at ? formatRunTime(item.last_run_at) : t("groups.dashboard.labels.not_available") }}
									</p>
								</div>

								<div class="border border-white/8 bg-neutral-950/45 px-3 py-3">
									<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/42">
										{{ t("groups.dashboard.content.next_run") }}
									</p>
									<p class="mt-2 text-sm text-white/78">
										{{ item.next_run_at ? formatRunTime(item.next_run_at) : t("groups.dashboard.labels.not_available") }}
									</p>
								</div>
							</div>
						</div>
					</article>
				</div>

				<p v-else class="text-sm text-white/58">
					{{ t("groups.dashboard.content.empty") }}
				</p>
			</div>
		</div>
	</section>
</template>
