<script setup lang="ts">
import axios from "axios";
import PageHeader from "@/components/PageHeader.vue";
import CreateGroupModal from "@/components/Groups/CreateGroupModal.vue";
import FeaturedGroupsCarousel from "@/components/Groups/FeaturedGroupsCarousel.vue";
import GroupDiscoverySearchSection from "@/components/Groups/GroupDiscoverySearchSection.vue";
import GroupDiscoverySlideover from "@/components/Groups/GroupDiscoverySlideover.vue";
import type { FeaturedGroupRecord, GroupDiscoveryDetailRecord, GroupIndexRecord } from "@/Types/Groups";
import { onMounted, ref } from "vue";
import { route } from "ziggy-js";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
const featuredGroups = ref<FeaturedGroupRecord[]>([]);
const isFeaturedGroupsLoading = ref(true);
const isGroupDetailsOpen = ref(false);
const isGroupDetailsLoading = ref(false);
const selectedGroup = ref<GroupDiscoveryDetailRecord | null>(null);

const groupDetailsCache = new Map<string, GroupDiscoveryDetailRecord>();
let groupDetailsRequestCounter = 0;

const loadFeaturedGroups = async () => {
	isFeaturedGroupsLoading.value = true;

	try {
		const response = await axios.get<{ data: FeaturedGroupRecord[] }>(route("groups.featured"));

		featuredGroups.value = response.data.data;
	} finally {
		isFeaturedGroupsLoading.value = false;
	}
};

const isSearchResultGroup = (group: FeaturedGroupRecord | GroupIndexRecord): group is GroupIndexRecord => "badge_meta" in group;

const loadGroupDetails = async (groupSlug: string) => {
	const requestId = ++groupDetailsRequestCounter;

	isGroupDetailsLoading.value = true;

	try {
		const response = await axios.get<{ data: GroupDiscoveryDetailRecord }>(route("groups.details", {
			group: groupSlug,
		}));

		if (requestId !== groupDetailsRequestCounter) {
			return;
		}

		selectedGroup.value = response.data.data;
		groupDetailsCache.set(groupSlug, response.data.data);
	} finally {
		if (requestId === groupDetailsRequestCounter) {
			isGroupDetailsLoading.value = false;
		}
	}
};

const openGroupDetails = (group: FeaturedGroupRecord | GroupIndexRecord) => {
	isGroupDetailsOpen.value = true;

	if (isSearchResultGroup(group)) {
		selectedGroup.value = group;
		groupDetailsCache.set(group.slug, group);
		isGroupDetailsLoading.value = false;

		return;
	}

	const cachedGroup = groupDetailsCache.get(group.slug);

	if (cachedGroup) {
		selectedGroup.value = cachedGroup;
		isGroupDetailsLoading.value = false;

		return;
	}

	selectedGroup.value = null;
	void loadGroupDetails(group.slug);
};

onMounted(() => {
	void loadFeaturedGroups();
});
</script>

<template>
	<div class="w-full">
		<PageHeader :title="t('groups.index.featured.title')" :subtitle="t('groups.index.featured.subtitle')">
			<CreateGroupModal />
		</PageHeader>

		<FeaturedGroupsCarousel
			:groups="featuredGroups"
			:is-loading="isFeaturedGroupsLoading"
			@open-group="openGroupDetails"
		/>

		<GroupDiscoverySearchSection @open-group="openGroupDetails" />

		<GroupDiscoverySlideover
			v-model:open="isGroupDetailsOpen"
			:group="selectedGroup"
			:loading="isGroupDetailsLoading"
		/>
	</div>
</template>

<style scoped>

</style>
