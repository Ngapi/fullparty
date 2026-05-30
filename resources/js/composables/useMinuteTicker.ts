import { onMounted, onUnmounted, ref } from "vue";

export function useMinuteTicker() {
	const now = ref(Date.now());
	let timer: number | undefined;

	onMounted(() => {
		timer = window.setInterval(() => {
			now.value = Date.now();
		}, 60_000);
	});

	onUnmounted(() => {
		if (timer !== undefined) {
			window.clearInterval(timer);
		}
	});

	return now;
}
