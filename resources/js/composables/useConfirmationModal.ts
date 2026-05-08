// @ts-ignore
import ConfirmationModal from "@/components/Shared/Modals/ConfirmationModal.vue";
import type { ConfirmationModalRequest } from "@/Types/Shared";
import { useOverlay } from "@nuxt/ui/composables";

export const useConfirmationModal = () => {
	const overlay = useOverlay();

	const open = async (request: ConfirmationModalRequest) => {
		const modal = overlay.create(ConfirmationModal, {
			destroyOnClose: true,
		});

		const patch = (props: Partial<ConfirmationModalRequest>) => {
			modal.patch(props);
		};

		const close = (value = true) => {
			modal.close(value);
		};

		const instance = modal.open({
			...request,
			onConfirm: request.onConfirm
				? async ({ inputValue }: { inputValue: string }) => request.onConfirm?.({
					inputValue,
					patch,
					close,
				})
				: undefined,
		});

		return await instance.result;
	};

	return {
		open,
	};
};
