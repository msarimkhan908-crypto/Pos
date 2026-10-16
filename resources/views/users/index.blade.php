<x-app-layout>
    <x-slot name="title">{{ __('Users') }}</x-slot>

    <x-ui.page-header :title="__('Users')" :description="__('People who can log in and use this system.')">
        <x-slot:actions>
            @can('users.create')
                <x-ui.button :href="route('users.create')" icon="plus">{{ __('New User') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.input name="search" type="search" :value="$search" :placeholder="__('Name or email…')" :aria-label="__('Search')" />
            </div>
            <div class="sm:w-44">
                <x-ui.select name="role" :options="$roles" :selected="$role" :placeholder="__('All roles')" :aria-label="__('Role')" />
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if ($search || $role)
                    <x-ui.button :href="route('users.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($users->isEmpty())
            <x-ui.empty-state
                icon="users"
                :title="$search || $role ? __('No matching users') : __('No users yet')"
                :description="$search || $role ? __('Try a different search or filter.') : __('Add users to give your team access to this system.')"
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Role') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Joined') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 sm:hidden">{{ $user->email }}</p>
                        </td>
                        <td class="hidden px-4 py-3 text-gray-600 sm:table-cell">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @foreach ($user->roles as $userRole)
                                <x-ui.badge color="primary">{{ $userRole->name }}</x-ui.badge>
                            @endforeach
                            @if ($user->roles->isEmpty())
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-500 md:table-cell">{{ $user->created_at->isoFormat('D MMM YYYY') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @can('users.edit')
                                    <x-ui.icon-button :href="route('users.edit', $user)" icon="pencil" :label="__('Edit')" />
                                @endcan
                                @can('users.delete')
                                    @if ($user->id !== auth()->id())
                                        <x-ui.icon-button
                                            icon="trash"
                                            variant="danger"
                                            :label="__('Delete')"
                                            :confirm="[
                                                'action' => route('users.destroy', $user),
                                                'title' => __('Delete :name?', ['name' => $user->name]),
                                                'message' => __('This will permanently remove their account.'),
                                            ]"
                                        />
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ __('Showing :from–:to of :total', ['from' => $users->firstItem(), 'to' => $users->lastItem(), 'total' => $users->total()]) }}</p>
                @if ($users->hasPages())
                    {{ $users->links() }}
                @endif
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
