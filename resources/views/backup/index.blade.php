<x-app-layout>
    <x-slot name="title">{{ __('Backup & Data') }}</x-slot>

    <x-ui.page-header :title="__('Backup & Data')" :description="__('Create and download backups of your POS database.')" />

    @if (session('success'))
        <x-ui.alert type="success" class="mb-6">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="max-w-3xl space-y-6">
        {{-- Create backup --}}
        <x-ui.card :title="__('Database Backup')" :description="__('Saves a copy of the database to the server storage and lets you download it.')">
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
                <strong>{{ __('Automatic backups:') }}</strong>
                {{ __('A backup is created automatically every Sunday at 02:00. The 30 most recent backups are kept.') }}
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
    </div>
</x-app-layout>
