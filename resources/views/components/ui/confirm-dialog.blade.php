{{--
    One shared delete confirmation, included once in the app layout.
    Trigger: $dispatch('confirm-delete', { action: url, title: '...', message: '...', button: 'Delete' })
    or use <x-ui.icon-button :confirm="[...]" />.
--}}
<div
    x-data="{ action: '', title: '', message: '', button: '' }"
    x-on:confirm-delete.window="
        action = $event.detail.action;
        title = $event.detail.title ?? @js(__('Are you sure?'));
        message = $event.detail.message ?? '';
        button = $event.detail.button ?? @js(__('Delete'));
        $dispatch('open-modal', 'confirm-delete');
    "
>
    <x-ui.modal name="confirm-delete" max-width="md">
        <form method="POST" x-bind:action="action">
            @csrf
            @method('DELETE')

            <div class="flex gap-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                    <x-icon name="alert-triangle" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="title"></h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="message" x-show="message"></p>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="danger"><span x-text="button"></span></x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
