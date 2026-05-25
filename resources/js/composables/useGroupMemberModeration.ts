// @ts-ignore
import type {GroupBannedMemberRecord, GroupMemberRecord} from "@/Types/Groups";
// @ts-ignore
import type { ConfirmationModalInput } from "@/Types/Shared";
import {useForm} from "@inertiajs/vue3";
import {useToast} from "@nuxt/ui/composables";
import {computed, type MaybeRefOrGetter, ref, toValue} from "vue";
import {useI18n} from "vue-i18n";
// @ts-ignore
import {route} from "ziggy-js";
// @ts-ignore
import {useConfirmationModal} from "@/composables/useConfirmationModal";
import type { GroupRole } from "@/Types/Groups";

type UseGroupMemberModerationOptions = {
	groupSlug: MaybeRefOrGetter<string>
	groupName: MaybeRefOrGetter<string>
};

export const useGroupMemberModeration = (options: UseGroupMemberModerationOptions) => {
	const { t } = useI18n();
	const toast = useToast();

	const updateRoleForm = useForm({
		role: '',
	});
	const removeForm = useForm({});
	const banForm = useForm({
		reason: '',
	});
	const unbanForm = useForm({});

	const memberPendingRoleUpdateId = ref<number | null>(null);
	const memberPendingRemovalId = ref<number | null>(null);
	const memberPendingBanId = ref<number | null>(null);
	const memberPendingUnbanId = ref<number | null>(null);
	const confirmationModal = useConfirmationModal();

	const currentGroupSlug = computed(() => toValue(options.groupSlug));
	const currentGroupName = computed(() => toValue(options.groupName));

	const showSuccessToast = (description: string) => {
		toast.add({
			title: t('general.success'),
			description,
			color: 'success',
			icon: 'i-lucide-check',
		});
	};

	const createBanReasonInput = (error?: string): ConfirmationModalInput => ({
		label: t('groups.members.ban_modal.reason.label'),
		help: t('groups.members.ban_modal.reason.help'),
		placeholder: t('groups.members.ban_modal.reason.placeholder'),
		error,
		rows: 4,
	});

	const updateMemberRole = (member: GroupMemberRecord, role: 'admin' | 'moderator' | 'member') => {
		memberPendingRoleUpdateId.value = member.id;
		updateRoleForm.role = role;

		updateRoleForm.put(route('groups.members.update', [currentGroupSlug.value, member.id]), {
			preserveScroll: true,
			onSuccess: () => {
				showSuccessToast(isRolePromotion(member.role, role)
					? t('groups.members.toasts.promoted')
					: t('groups.members.toasts.demoted'));
			},
			onFinish: () => {
				memberPendingRoleUpdateId.value = null;
				updateRoleForm.reset();
			},
		});
	};

	const openKickConfirmation = async (member: GroupMemberRecord) => {
		await confirmationModal.open({
			title: t('groups.members.kick_modal.title', { name: member.name }),
			description: t('groups.members.kick_modal.subtitle', { group: currentGroupName.value }),
			severity: 'error',
			warningText: t('groups.members.kick_modal.warning'),
			confirmLabel: t('groups.members.actions.kick'),
			confirmIcon: 'i-lucide-user-round-x',
			onConfirm: async ({ patch }) => {
				memberPendingRemovalId.value = member.id;

				patch({ confirmLoading: true });

                return await new Promise<boolean>((resolve) => {
                    removeForm.delete(route('groups.members.destroy', [currentGroupSlug.value, member.id]), {
                        preserveScroll: true,
                        onSuccess: () => {
                            showSuccessToast(t('groups.members.toasts.removed'));
                            resolve(true);
                        },
                        onError: () => {
                            resolve(false);
                        },
                        onFinish: () => {
                            memberPendingRemovalId.value = null;
                            patch({confirmLoading: false});
                        },
                    });
                });
			},
		});
	};

	const openBanConfirmation = async (member: GroupMemberRecord) => {
		banForm.reason = '';
		banForm.clearErrors();

		await confirmationModal.open({
			title: t('groups.members.ban_modal.title', { name: member.name }),
			description: t('groups.members.ban_modal.subtitle', { group: currentGroupName.value }),
			severity: 'error',
			warningText: t('groups.members.ban_modal.warning'),
			confirmLabel: t('groups.members.actions.ban'),
			confirmIcon: 'i-lucide-ban',
			input: createBanReasonInput(),
			onConfirm: async ({ inputValue, patch }) => {
				memberPendingBanId.value = member.id;
				banForm.reason = inputValue;
				banForm.clearErrors();

				patch({
					confirmLoading: true,
					input: createBanReasonInput(),
				});

                return await new Promise<boolean>((resolve) => {
                    banForm.post(route('groups.members.ban', [currentGroupSlug.value, member.id]), {
                        preserveScroll: true,
                        onSuccess: () => {
                            showSuccessToast(t('groups.members.toasts.banned'));
                            banForm.reset();
                            resolve(true);
                        },
                        onError: (errors) => {
							patch({
								input: createBanReasonInput(typeof errors.reason === 'string' ? errors.reason : undefined),
							});
                            resolve(false);
                        },
                        onFinish: () => {
                            memberPendingBanId.value = null;
                            patch({confirmLoading: false});
                        },
                    });
                });
			},
		});
	};

	const unbanMember = (member: GroupBannedMemberRecord) => {
		if (!member.user_id) {
			return;
		}

		memberPendingUnbanId.value = member.user_id;

		unbanForm.delete(route('groups.members.unban', [currentGroupSlug.value, member.user_id]), {
			preserveScroll: true,
			onSuccess: () => {
				showSuccessToast(t('groups.members.toasts.unbanned'));
			},
			onFinish: () => {
				memberPendingUnbanId.value = null;
			},
		});
	};

	return {
		updateRoleForm,
		removeForm,
		banForm,
		unbanForm,
		memberPendingRoleUpdateId,
		memberPendingRemovalId,
		memberPendingBanId,
		memberPendingUnbanId,
		openKickConfirmation,
		openBanConfirmation,
		updateMemberRole,
		unbanMember,
	};
};

const roleOrder: GroupRole[] = ["owner", "admin", "moderator", "member"];

const isRolePromotion = (currentRole: GroupRole, nextRole: GroupRole) => (
	roleOrder.indexOf(nextRole) < roleOrder.indexOf(currentRole)
);
