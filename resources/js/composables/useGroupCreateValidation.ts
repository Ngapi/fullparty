import type { GroupCreateField, GroupCreateFormData } from "@/Types/Groups";
import type { InertiaForm } from "@inertiajs/vue3";
import { validateGroupProfilePictureFile } from "@/utils/groupProfilePictureValidation";
import { computed, type ComputedRef, type Ref } from "vue";
import { useI18n } from "vue-i18n";

const groupSlugMaxLength = 8;
const groupProfilePictureMaxBytes = 5 * 1024 * 1024;

const reservedGroupSlugs = new Set([
	"admin",
	"api",
	"auth",
	"groups",
	"group",
	"invite",
	"invites",
	"login",
	"register",
	"settings",
]);

const stepFields: Record<number, GroupCreateField[]> = {
	1: ["name", "slug", "group_type", "description", "datacenter"],
	2: ["profile_picture", "discord_invite_url"],
	3: ["is_public", "is_visible"],
};

type GroupCreateOption = {
	value: string
};

type UseGroupCreateValidationOptions = {
	form: InertiaForm<GroupCreateFormData>
	step: Ref<number>
	datacenterOptions: ComputedRef<GroupCreateOption[]>
	groupTypeOptions: ComputedRef<GroupCreateOption[]>
};

export const useGroupCreateValidation = ({
	form,
	step,
	datacenterOptions,
	groupTypeOptions,
}: UseGroupCreateValidationOptions) => {
	const { t } = useI18n();

	const normalizeGroupSlug = (value: string) => value
		.toLowerCase()
		.replace(/[^a-z]/g, "")
		.slice(0, groupSlugMaxLength);

	const slugFieldValue = computed({
		get: () => form.slug,
		set: (value: string | number | undefined) => {
			form.slug = normalizeGroupSlug(String(value ?? ""));
			form.clearErrors("slug");
		},
	});

	const normalizedSlugHint = computed(() => normalizeGroupSlug(form.slug));

	const canContinue = computed(() => {
		if (step.value === 1) {
			return !!form.name && !!form.slug && !!form.group_type && !!form.datacenter;
		}

		return true;
	});

	const clearFieldError = (field: GroupCreateField) => {
		form.clearErrors(field);
	};

	const setFieldError = (field: GroupCreateField, message: string) => {
		form.setError(field, message);
	};

	const validateStepOne = () => {
		form.clearErrors(...stepFields[1]);

		let isValid = true;

		if (!form.name.trim()) {
			setFieldError("name", t("groups.index.create_modal.validation.required", {
				field: t("general.name"),
			}));
			isValid = false;
		} else if (form.name.length > 255) {
			setFieldError("name", t("groups.index.create_modal.validation.max_characters", {
				field: t("general.name"),
				max: 255,
			}));
			isValid = false;
		}

		if (!form.slug) {
			setFieldError("slug", t("groups.index.create_modal.validation.required", {
				field: t("groups.index.create_modal.fields.slug.label"),
			}));
			isValid = false;
		} else if (!/^[a-z]{1,8}$/.test(form.slug)) {
			setFieldError("slug", t("groups.index.create_modal.validation.slug_letters_only"));
			isValid = false;
		} else if (reservedGroupSlugs.has(form.slug)) {
			setFieldError("slug", t("groups.index.create_modal.validation.slug_reserved"));
			isValid = false;
		}

		if (!form.group_type) {
			setFieldError("group_type", t("groups.index.create_modal.validation.required", {
				field: t("groups.index.create_modal.fields.group_type.label"),
			}));
			isValid = false;
		} else if (!groupTypeOptions.value.some((option) => option.value === form.group_type)) {
			setFieldError("group_type", t("groups.index.create_modal.validation.invalid_option", {
				field: t("groups.index.create_modal.fields.group_type.label"),
			}));
			isValid = false;
		}

		if (!form.datacenter) {
			setFieldError("datacenter", t("groups.index.create_modal.validation.required", {
				field: t("general.datacenter"),
			}));
			isValid = false;
		} else if (!datacenterOptions.value.some((option) => option.value === form.datacenter)) {
			setFieldError("datacenter", t("groups.index.create_modal.validation.invalid_option", {
				field: t("general.datacenter"),
			}));
			isValid = false;
		}

		return isValid;
	};

	const validateStepTwo = () => {
		form.clearErrors(...stepFields[2]);

		let isValid = true;
		const discordInviteUrl = form.discord_invite_url.trim();

		if (discordInviteUrl.length > 500) {
			setFieldError("discord_invite_url", t("groups.index.create_modal.validation.max_characters", {
				field: t("general.discord_invite_url"),
				max: 500,
			}));
			isValid = false;
		} else if (discordInviteUrl) {
			try {
				new URL(discordInviteUrl);
			} catch {
				setFieldError("discord_invite_url", t("groups.index.create_modal.validation.invalid_url"));
				isValid = false;
			}
		}

		if (form.profile_picture) {
			const profilePictureValidation = validateGroupProfilePictureFile(form.profile_picture);

			if (!profilePictureValidation.isValid) {
				setFieldError("profile_picture", t("groups.index.create_modal.validation.image_invalid_format"));
				isValid = false;
			} else if (form.profile_picture.size > groupProfilePictureMaxBytes) {
				setFieldError("profile_picture", t("groups.index.create_modal.validation.image_too_large"));
				isValid = false;
			}
		}

		return isValid;
	};

	const validateCurrentStep = () => {
		if (step.value === 1) {
			return validateStepOne();
		}

		if (step.value === 2) {
			return validateStepTwo();
		}

		return true;
	};

	const goToStepWithErrors = (errors: Partial<Record<GroupCreateField, string>>) => {
		for (const [stepNumber, fields] of Object.entries(stepFields)) {
			if (fields.some((field) => Boolean(errors[field]))) {
				step.value = Number(stepNumber);
				return;
			}
		}
	};

	return {
		canContinue,
		clearFieldError,
		goToStepWithErrors,
		groupSlugMaxLength,
		normalizeGroupSlug,
		normalizedSlugHint,
		slugFieldValue,
		validateCurrentStep,
	};
};
