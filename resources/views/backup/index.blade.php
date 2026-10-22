<x-app-layout>
    <x-slot name="title">{{ __('Backup & Data') }}</x-slot>

    <x-ui.page-header :title="__('Backup & Data')" :description="__('Create and download backups. Restore a backup to move your data to another computer.')" />

    @if (session('success'))
        <x-ui.alert type="success" class="mb-6">{{ session('success') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert type="danger" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="max-w-3xl space-y-6">
        {{-- Create backup --}}
        <x-ui.card :title="__('Create Backup')" :description="__('Saves a copy of all your data (database + uploaded images) as a .zip file.')">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    @if (! empty($lastBackup['backed_up_at']))
                        {{ __('Last backup:') }}
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ \Carbon\Carbon::parse($lastBackup['backed_up_at'])->diffForHumans() }}
                        </span>
                    @else
                        {{ __('No backup has been created yet.') }}
                    @endif
                </div>
                <form method="POST" action="{{ route('backup.store') }}">
                    @csrf
                    <x-ui.button type="submit" icon="download">{{ __('Create backup now') }}</x-ui.button>
                </form>
            </div>

            <div class="mt-4 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                <strong>{{ __('Transfer to another computer:') }}</strong>
                {{ __('Download a backup, copy the .zip file to the other computer, then use "Restore from Backup" on that machine.') }}
            </div>
        </x-ui.card>

        {{-- Backup list --}}
        <x-ui.card :title="__('Saved Backups')" :description="trans_choice(':count backup saved|:count backups saved', $backups->count())" :padding="false">
            @if ($backups->isEmpty())
                <x-ui.empty-state icon="download" :title="__('No backups yet')" :description="__('Create your first backup using the button above.')" />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-3">{{ __('File') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Size') }}</th>
                        <th class="hidden px-4 py-3 sm:table-cell">{{ __('Created') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </x-slot:head>

                    @foreach ($backups as $backup)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $backup['name'] }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-sm text-gray-600 dark:text-gray-400">
                                {{ number_format($backup['size'] / 1024 / 1024, 2) }} MB
                            </td>
                            <td class="hidden px-4 py-3 text-sm text-gray-500 dark:text-gray-400 sm:table-cell">
                                {{ $backup['date']->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.button
                                        :href="route('backup.download', $backup['name'])"
                                        variant="secondary"
                                        size="sm"
                                        icon="download"
                                    >{{ __('Download') }}</x-ui.button>
                                    <x-ui.icon-button
                                        icon="trash"
                                        variant="danger"
                                        :label="__('Delete')"
                                        :confirm="[
                                            'action' => route('backup.destroy', $backup['name']),
                                            'method' => 'DELETE',
                                            'title' => __('Delete this backup?'),
                                            'message' => __('This cannot be undone.'),
                                        ]"
                                    />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>

        {{-- Restore from backup --}}
        <x-ui.card
            :title="__('Restore from Backup')"
            :description="__('Upload a backup .zip file to replace all current data. Use this to migrate to a new computer.')"
        >
            <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                <strong>{{ __('Warning:') }}</strong>
                {{ __('Restoring a backup will overwrite ALL current data including sales, products, and settings. This cannot be undone.') }}
            </div>

            <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data"
                x-data="{ file: null, confirming: false }"
                x-on:submit.prevent="confirming = true">

                @csrf

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Backup file (.zip)') }}
                        </label>
                        <input
                            type="file"
                            name="backup_file"
                            accept=".zip"
                            x-on:change="file = $event.target.files[0]?.name ?? null"
                            class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:text-gray-400 dark:file:bg-primary-900/30 dark:file:text-primary-300"
                            required
                        />
                        @error('backup_file')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="file" x-cloak>
                        <x-ui.button type="button" variant="danger" icon="arrow-up-tray"
                            x-on:click="confirming = true">
                            {{ __('Restore this backup') }}
                        </x-ui.button>
                    </div>
                </div>

                {{-- Confirmation modal --}}
                <div x-show="confirming" x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Confirm restore') }}</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('This will permanently replace all current data with the selected backup. Continue?') }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100" x-text="file"></p>
                        <div class="mt-5 flex gap-3">
                            <x-ui.button type="submit" variant="danger">{{ __('Yes, restore') }}</x-ui.button>
                            <x-ui.button type="button" variant="secondary" x-on:click="confirming = false">{{ __('Cancel') }}</x-ui.button>
                        </div>
                    </div>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
