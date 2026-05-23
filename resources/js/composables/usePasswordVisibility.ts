import { ref } from "vue";

export const usePasswordVisibility = <T extends string>(fields: readonly T[]) => {
	const visibility = ref(Object.fromEntries(
		fields.map((field) => [field, false]),
	) as Record<T, boolean>);

	const inputType = (field: T) => (visibility.value[field] ? "text" : "password");
	const icon = (field: T) => (visibility.value[field] ? "i-lucide-eye-off" : "i-lucide-eye");
	const toggle = (field: T) => {
		visibility.value[field] = !visibility.value[field];
	};

	return {
		inputType,
		icon,
		toggle,
	};
};
