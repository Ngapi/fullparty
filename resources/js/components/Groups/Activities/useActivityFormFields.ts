import { computed, ref, watch, type Ref } from "vue";
import type { ActivityFormOptions, ActivityFormShape, ActivityTypeOption, OrganizerCharacterOption } from "@/Types/ActivityCore";
import { useI18n } from "vue-i18n";
import { usePage } from "@inertiajs/vue3";
import { localizedValue } from "@/utils/localizedValue";

export const useActivityFormFields = (
	activityTypes: Ref<ActivityTypeOption[]>,
	organizerCharacters: Ref<OrganizerCharacterOption[]>,
	form: ActivityFormShape,
	options: ActivityFormOptions,
) => {
	const { t, locale } = useI18n();
	const page = usePage();
	const fallbackLocale = computed(() => String(page.props.locale?.fallback ?? 'en'));
	const normalizeDurationHours = (value: string | number | null | undefined) => {
		const parsed = Number(value);

		if (!Number.isFinite(parsed)) {
			return 2;
		}

		const roundedToHalfHour = Math.round(parsed * 2) / 2;

		return Math.min(24, Math.max(1, roundedToHalfHour));
	};

	const activityTypeItems = computed(() => activityTypes.value.map((activityType) => ({
		label: localizedValue(activityType.draft_name, locale.value, fallbackLocale.value) || activityType.slug,
		value: activityType.id,
		slot_count: activityType.slot_count,
	})));

	const organizerCharacterItems = computed(() => organizerCharacters.value.map((character) => ({
		id: character.id,
		user_id: character.user_id,
		label: character.name || `#${character.id}`,
		user_name: character.user_name || t('groups.activities.create.fields.organizer.no_user'),
		world: character.world,
		avatar_url: character.avatar_url,
		avatar: character.avatar_url ? {
			src: character.avatar_url,
			alt: character.name || `#${character.id}`,
		} : undefined,
		description: character.user_name || t('groups.activities.create.fields.organizer.no_user'),
	})));

	const selectedOrganizerCharacter = computed(() => (
		organizerCharacterItems.value.find((character) => character.id === form.organized_by_character_id) ?? null
	));

	const selectedActivityType = computed(() => (
		activityTypes.value.find((activityType) => activityType.id === form.activity_type_id) ?? null
	));

	const progPointItems = computed(() => (selectedActivityType.value?.prog_points ?? []).map((progPoint) => ({
		label: localizedValue(progPoint.label, locale.value, fallbackLocale.value) || progPoint.key,
		value: progPoint.key,
	})));

	const statusItems = computed(() => options.mode === 'create'
		? [
			{ label: t('groups.activities.statuses.planned'), value: 'planned' },
			{ label: t('groups.activities.statuses.scheduled'), value: 'scheduled' },
		]
		: []);

	watch(selectedActivityType, (activityType) => {
		const validProgPointKeys = (activityType?.prog_points ?? []).map((progPoint) => progPoint.key);

		if (!validProgPointKeys.length) {
			form.target_prog_point_key = null;
			return;
		}

		if (!form.target_prog_point_key) {
			if (options.mode === 'create') {
				form.target_prog_point_key = validProgPointKeys[0];
			}

			return;
		}

		if (!validProgPointKeys.includes(form.target_prog_point_key)) {
			form.target_prog_point_key = validProgPointKeys[0];
		}
	}, { immediate: true });

	const updateOrganizerCharacter = (character: (typeof organizerCharacterItems.value)[number] | null) => {
		form.organized_by_character_id = character?.id ?? null;
		form.organized_by_user_id = character?.user_id ?? null;
	};

	const buildDefaultStartsAt = () => {
		const now = new Date();
		const target = new Date(now.getTime() + (60 * 60 * 1000));
		target.setSeconds(0, 0);

		if (target.getMinutes() !== 0) {
			target.setHours(target.getHours() + 1, 0, 0, 0);
		}

		const year = target.getUTCFullYear();
		const month = String(target.getUTCMonth() + 1).padStart(2, '0');
		const day = String(target.getUTCDate()).padStart(2, '0');
		const hour = String(target.getUTCHours()).padStart(2, '0');

		return `${year}-${month}-${day}T${hour}:00`;
	};

	if (!form.starts_at && options.mode === 'create') {
		form.starts_at = buildDefaultStartsAt();
	}

	const startDate = ref(form.starts_at ? form.starts_at.slice(0, 10) : '');
	const startHour = ref(form.starts_at ? form.starts_at.slice(11, 13) : '');
	const startMinute = ref(form.starts_at ? form.starts_at.slice(14, 16) : '00');

	watch([startDate, startHour, startMinute], ([date, hour, minute]) => {
		form.starts_at = date && hour && minute
			? `${date}T${hour}:${minute}`
			: null;
	});

	const hourItems = Array.from({ length: 24 }, (_, hour) => ({
		label: hour.toString().padStart(2, '0'),
		value: hour.toString().padStart(2, '0'),
	}));

	const minuteItems = Array.from({ length: 12 }, (_, index) => {
		const minute = (index * 5).toString().padStart(2, '0');

		return {
			label: minute,
			value: minute,
		};
	});

	const durationPresets = [2, 3, 6] as const;

	const durationItems = computed(() => [
		{ label: '2h', value: 2 },
		{ label: '3h', value: 3 },
		{ label: '6h', value: 6 },
		{ label: t('groups.activities.create.fields.duration.custom'), value: 'custom' },
	]);

	const selectedDurationOption = computed({
		get: () => durationPresets.includes(form.duration_hours as 2 | 3 | 6)
			? options.mode === 'create'
				? String(form.duration_hours)
				: form.duration_hours
			: 'custom',
		set: (value: string | number | 'custom') => {
			if (value === 'custom') {
				if (durationPresets.includes(form.duration_hours as 2 | 3 | 6)) {
					form.duration_hours = 4;
				}

				return;
			}

			form.duration_hours = normalizeDurationHours(value);
		},
	});

	const isCustomDuration = computed(() => selectedDurationOption.value === 'custom');

	return {
		activityTypeItems,
		organizerCharacterItems,
		selectedOrganizerCharacter,
		selectedActivityType,
		progPointItems,
		statusItems,
		updateOrganizerCharacter,
		startDate,
		startHour,
		startMinute,
		hourItems,
		minuteItems,
		durationPresets,
		durationItems,
		selectedDurationOption,
		isCustomDuration,
		normalizeDurationHours,
	};
};
