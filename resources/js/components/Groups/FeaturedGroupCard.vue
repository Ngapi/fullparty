<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { FeaturedGroupRecord } from "@/Types/Groups";
import GroupDiscoveryBadge from "@/components/Groups/GroupDiscoveryBadge.vue";

const props = defineProps<{
	group: FeaturedGroupRecord
}>();
const emit = defineEmits<{
	openGroup: [group: FeaturedGroupRecord]
}>();

const { t } = useI18n();
const languageLabelMap: Record<string, string> = {
	en: "English",
	de: "Deutsch",
	fr: "Français",
	ja: "日本語",
};

const visibleTags = computed(() => props.group.tag_badges.slice(0, 3));
const experienceLabel = computed(() => (
	props.group.experience_badge
		? t(`groups.index.create_modal.fields.experience_expectation.options.${props.group.experience_badge.value}`)
		: null
));
const languagesText = computed(() => props.group.preferred_languages
	.map((language) => languageLabelMap[language] ?? language.toUpperCase())
	.join(", "));
const bannerStyle = computed(() => {
	if (!props.group.banner_image_url) {
		return undefined;
	}

	return {
		backgroundImage: `url(${props.group.banner_image_url})`,
	};
});

function openGroup() {
	emit("openGroup", props.group);
}
</script>

<template>
	<article
		class="relative min-h-52 overflow-hidden border border-white/10 bg-neutral-950 shadow-[0_18px_40px_rgba(0,0,0,0.28)] transition-all cursor-pointer hover:border-neutral-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
		role="button"
		tabindex="0"
		@click="openGroup"
		@keydown.enter.prevent="openGroup"
		@keydown.space.prevent="openGroup"
	>
		<div
			class="absolute inset-0 bg-cover bg-center"
			:style="bannerStyle"
		/>
		<div class="absolute inset-0 bg-linear-to-t from-neutral-950 via-neutral-950/72 to-neutral-950/18" />
		<div
			v-if="!group.banner_image_url"
			class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(134,93,255,0.22),transparent_42%),radial-gradient(circle_at_80%_20%,rgba(80,145,255,0.18),transparent_36%),linear-gradient(180deg,rgba(255,255,255,0.04),rgba(255,255,255,0))]"
		/>

		<div class="relative flex h-full min-h-52 flex-col justify-between p-4">
			<div>
				<GroupDiscoveryBadge
					v-if="group.experience_badge && experienceLabel"
					:label="experienceLabel"
					:color="group.experience_badge.color"
				/>
			</div>

			<div class="space-y-3">
				<h3 class="text-lg font-semibold leading-tight text-white drop-shadow-[0_2px_8px_rgba(0,0,0,0.45)] break-words [overflow-wrap:anywhere]">
					{{ group.name }}
				</h3>

				<div v-if="visibleTags.length > 0" class="flex flex-wrap gap-2">
					<GroupDiscoveryBadge
						v-for="tag in visibleTags"
						:key="tag.value"
						:label="tag.label"
						:color="tag.color"
					/>
				</div>

				<div class="flex items-center gap-2 text-sm text-white/72">
					<div class="flex items-center gap-2">
						<UIcon name="i-lucide-users" class="size-4" />
						<span>{{ group.stats.member_count }}</span>
						<span>{{ t('general.members') }}</span>
					</div>
					<span v-if="languagesText" class="ml-auto text-right">
						{{ languagesText }}
					</span>
				</div>
			</div>
		</div>
	</article>
</template>
