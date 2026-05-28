<template>
	<div class="border border-neutral-900 bg-neutral-900/50">
		<div class="flex flex-col xl:flex-row">
			<div class="hidden min-h-56 w-56 shrink-0 border-r border-neutral-900 xl:block">
				<USkeleton class="h-full min-h-32 w-full rounded-none" />
			</div>

			<div class="grid min-w-0 flex-1 grid-cols-1 items-stretch md:flex">
				<div class="min-w-0 px-4 py-4 md:w-5/12 xl:w-4/12">
					<div class="flex h-full flex-col justify-evenly gap-3">
						<div class="space-y-2">
							<USkeleton class="h-6 w-3/4 rounded-none" />
							<USkeleton class="h-4 w-1/2 rounded-none" />
						</div>

						<div class="flex flex-wrap gap-2">
							<USkeleton class="h-6 w-24 rounded-none" />
							<USkeleton class="h-6 w-20 rounded-none" />
							<USkeleton class="h-6 w-24 rounded-none" />
						</div>
					</div>
				</div>

				<div class="hidden w-px bg-neutral-900 xl:block" />

				<div class="p-4 md:w-4/12 xl:w-3/12">
					<div class="flex h-full flex-col justify-between gap-3">
						<div class="space-y-2">
							<USkeleton class="h-5 w-24 rounded-none" />
							<USkeleton class="h-4 w-full rounded-none" />
							<USkeleton class="h-4 w-5/6 rounded-none" />
						</div>

						<div class="flex flex-wrap gap-2">
							<USkeleton class="h-6 w-16 rounded-none" />
							<USkeleton class="h-6 w-20 rounded-none" />
							<USkeleton class="h-6 w-14 rounded-none" />
						</div>
					</div>
				</div>

				<div class="hidden w-px bg-neutral-900 xl:block" />

				<div class="hidden p-4 md:block md:w-2/12">
					<div class="flex h-full flex-col justify-between gap-3">
						<div class="space-y-2">
							<USkeleton class="h-4 w-20 rounded-none" />
							<USkeleton class="h-6 w-28 rounded-none" />
						</div>

						<div class="space-y-2">
							<USkeleton class="h-4 w-24 rounded-none" />
						</div>
					</div>
				</div>

				<div class="hidden w-px bg-neutral-900 xl:block" />

				<div class="hidden p-4 xl:block xl:w-2/12">
					<div class="flex h-full flex-col justify-between gap-3">
						<div class="space-y-2">
							<USkeleton class="h-4 w-24 rounded-none" />
							<USkeleton class="h-4 w-20 rounded-none" />
						</div>

						<div class="space-y-2">
							<USkeleton class="h-4 w-24 rounded-none" />
						</div>
					</div>
				</div>

				<div class="hidden w-1/12 shrink-0 items-center justify-center px-4 md:flex">
					<USkeleton class="h-5 w-5 rounded-none" />
				</div>
			</div>
		</div>
	</div>
</template>
