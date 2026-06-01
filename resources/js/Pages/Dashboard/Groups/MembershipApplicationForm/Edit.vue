<script setup lang="ts">
import type { MembershipApplicationFormField } from "@/Types/Groups";
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { route } from "ziggy-js";
import AccessBadge from "@/components/Groups/AccessBadge.vue";
import MembershipApplicationFormBuilder from "@/components/Groups/MembershipApplicationFormBuilder.vue";
import PageHeader from "@/components/PageHeader.vue";

const props = defineProps<{
	group: {
		id: number
		name: string
		slug: string
		join_mode: string
		current_user_role: string | null
		permissions: {
			can_manage_membership_application_form: boolean
		}
	}
	formSchema: MembershipApplicationFormField[]
	locales: string[]
	maxQuestions: number
}>();

const { t } = useI18n();

const cloneSchema = (schema: MembershipApplicationFormField[]) => JSON.parse(JSON.stringify(schema)) as MembershipApplicationFormField[];

const form = useForm({
	fields: cloneSchema(props.formSchema),
});

const hasChanges = computed(() => JSON.stringify(form.fields) !== JSON.stringify(props.formSchema));

const save = () => {
	form.put(route("groups.dashboard.membership-application-form.update", props.group.slug), {
	});
};
</script>

<template>
	<div class="w-full">
		<PageHeader
			:title="t('groups.membership_applications.form.title')"
			:subtitle="t('groups.membership_applications.form.subtitle')"
		>
			<AccessBadge :role="group.current_user_role" fallback-role="admin" />
		</PageHeader>

		<div class="mt-4 space-y-4">
			<UAlert
				v-if="group.join_mode !== 'application'"
				color="neutral"
				variant="soft"
				icon="i-lucide-info"
				:title="t('groups.membership_applications.form.inactive_title')"
				:description="t('groups.membership_applications.form.inactive_description')"
			/>

			<div class="flex flex-wrap items-center justify-end gap-2">
				<UButton
					color="primary"
					icon="i-lucide-save"
					:label="t('groups.membership_applications.form.actions.save')"
					:loading="form.processing"
					:disabled="!hasChanges && !form.isDirty"
					@click="save"
				/>
			</div>

			<MembershipApplicationFormBuilder
				v-model="form.fields"
				:locales="locales"
				:max-questions="maxQuestions"
				:errors="form.errors"
			/>
		</div>
	</div>
</template>
