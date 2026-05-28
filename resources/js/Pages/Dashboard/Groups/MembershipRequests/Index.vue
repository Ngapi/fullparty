<script setup lang="ts">
import type { GroupMembershipRequestRecord } from "@/Types/Groups";
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import MembershipApplicationAnswerList from "@/components/Groups/MembershipApplicationAnswerList.vue";
import PageHeader from "@/components/PageHeader.vue";
import { createDateTimeFormatter } from "@/utils/dateTimeFormat";

const props = defineProps<{
	activeRequests: GroupMembershipRequestRecord[]
	historicalRequests: GroupMembershipRequestRecord[]
}>();

const { locale, t } = useI18n();

const hasRequests = computed(() => props.activeRequests.length > 0 || props.historicalRequests.length > 0);

const statusColor = (status: GroupMembershipRequestRecord["status"]): "warning" | "success" | "error" | "neutral" => ({
	pending: "warning",
	approved: "success",
	declined: "error",
}[status] ?? "neutral");

const formatDateTime = (value: string | null) => {
	if (!value) {
		return t("groups.membership_applications.requests.not_available");
	}

	return createDateTimeFormatter(locale.value, {
		year: "numeric",
		month: "2-digit",
		day: "2-digit",
		hour: "2-digit",
		minute: "2-digit",
	}).format(new Date(value));
};

const openRequest = (request: GroupMembershipRequestRecord) => {
	if (request.urls.edit) {
		router.get(request.urls.edit);
	}
};

const openDashboard = (request: GroupMembershipRequestRecord) => {
	if (request.urls.dashboard) {
		router.get(request.urls.dashboard);
	}
};
</script>

<template>
	<div class="w-full">
		<PageHeader
			:title="t('groups.membership_applications.requests.title')"
			:subtitle="t('groups.membership_applications.requests.subtitle')"
		/>

		<div v-if="!hasRequests" class="mt-2">
			<UCard class="dark:bg-elevated/25">
				<UAlert
					color="neutral"
					variant="soft"
					icon="i-lucide-inbox"
					:title="t('groups.membership_applications.requests.empty_title')"
					:description="t('groups.membership_applications.requests.empty_description')"
				/>
			</UCard>
		</div>

		<div v-else class="mt-2 flex flex-col gap-8">
			<section class="space-y-4">
				<div class="space-y-1">
					<h2 class="text-xl font-semibold text-toned">
						{{ t("groups.membership_applications.requests.sections.active_title") }}
					</h2>
					<p class="text-sm text-muted">
						{{ t("groups.membership_applications.requests.sections.active_description") }}
					</p>
				</div>

				<UAlert
					v-if="activeRequests.length === 0"
					color="neutral"
					variant="soft"
					icon="i-lucide-inbox"
					:title="t('groups.membership_applications.requests.sections.active_empty_title')"
					:description="t('groups.membership_applications.requests.sections.active_empty_description')"
				/>

				<UCard
					v-for="request in activeRequests"
					:key="request.id"
					class="dark:bg-elevated/25"
				>
					<div class="space-y-5">
						<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
							<div class="flex min-w-0 items-center gap-3">
								<UAvatar
									:src="request.group.profile_picture_url ?? undefined"
									:alt="request.group.name ?? undefined"
									size="lg"
									icon="i-lucide-users"
								/>
								<div class="min-w-0">
									<h3 class="break-words [overflow-wrap:anywhere] text-xl font-semibold text-toned">
										{{ request.group.name || t("groups.membership_applications.requests.unknown_group") }}
									</h3>
									<p class="text-sm text-muted">
										{{ t("groups.membership_applications.requests.submitted", { date: formatDateTime(request.submitted_at) }) }}
									</p>
									<p v-if="request.group.datacenter" class="text-sm text-muted">
										{{ request.group.datacenter }}
									</p>
								</div>
							</div>

							<div class="flex flex-wrap items-center gap-2">
								<UBadge
									:color="statusColor(request.status)"
									variant="soft"
									:label="t(`groups.membership_applications.statuses.${request.status}`)"
								/>
								<UButton
									v-if="request.can_edit && request.urls.edit"
									color="neutral"
									variant="outline"
									icon="i-lucide-pencil-line"
									:label="t('groups.membership_applications.requests.actions.edit')"
									@click="openRequest(request)"
								/>
							</div>
						</div>

						<MembershipApplicationAnswerList
							:fields="request.form_snapshot"
							:answers="request.answers"
						/>
					</div>
				</UCard>
			</section>

			<section class="space-y-4">
				<div class="space-y-1">
					<h2 class="text-xl font-semibold text-toned">
						{{ t("groups.membership_applications.requests.sections.history_title") }}
					</h2>
					<p class="text-sm text-muted">
						{{ t("groups.membership_applications.requests.sections.history_description") }}
					</p>
				</div>

				<UAlert
					v-if="historicalRequests.length === 0"
					color="neutral"
					variant="soft"
					icon="i-lucide-history"
					:title="t('groups.membership_applications.requests.sections.history_empty_title')"
					:description="t('groups.membership_applications.requests.sections.history_empty_description')"
				/>

				<UCard
					v-for="request in historicalRequests"
					:key="request.id"
					class="dark:bg-elevated/25"
				>
					<div class="space-y-5">
						<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
							<div class="flex min-w-0 items-center gap-3">
								<UAvatar
									:src="request.group.profile_picture_url ?? undefined"
									:alt="request.group.name ?? undefined"
									size="lg"
									icon="i-lucide-users"
								/>
								<div class="min-w-0">
									<h3 class="break-words [overflow-wrap:anywhere] text-xl font-semibold text-toned">
										{{ request.group.name || t("groups.membership_applications.requests.unknown_group") }}
									</h3>
									<p class="text-sm text-muted">
										{{ t("groups.membership_applications.requests.submitted", { date: formatDateTime(request.submitted_at) }) }}
									</p>
									<p v-if="request.reviewed_at" class="text-sm text-muted">
										{{ t("groups.membership_applications.requests.reviewed", { date: formatDateTime(request.reviewed_at) }) }}
									</p>
								</div>
							</div>

							<div class="flex flex-wrap items-center gap-2">
								<UBadge
									:color="statusColor(request.status)"
									variant="soft"
									:label="t(`groups.membership_applications.statuses.${request.status}`)"
								/>
								<UButton
									v-if="request.urls.dashboard"
									color="neutral"
									variant="ghost"
									icon="i-lucide-layout-dashboard"
									:label="t('groups.membership_applications.requests.actions.open_group')"
									@click="openDashboard(request)"
								/>
								<UButton
									v-else-if="request.status === 'declined' && request.urls.edit"
									color="neutral"
									variant="outline"
									icon="i-lucide-send"
									:label="t('groups.membership_applications.requests.actions.request_again')"
									@click="openRequest(request)"
								/>
							</div>
						</div>

						<p
							v-if="request.review_reason"
							class="break-words [overflow-wrap:anywhere] whitespace-pre-wrap rounded-sm border border-default bg-default px-4 py-3 text-sm text-toned"
						>
							{{ request.review_reason }}
						</p>

						<MembershipApplicationAnswerList
							:fields="request.form_snapshot"
							:answers="request.answers"
						/>
					</div>
				</UCard>
			</section>
		</div>
	</div>
</template>
