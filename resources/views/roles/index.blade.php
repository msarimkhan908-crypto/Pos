<x-app-layout>
    <x-slot name="title">{{ __('Roles & Permissions') }}</x-slot>

    <x-ui.page-header :title="__('Roles & Permissions')" :description="__('Control what each role can do.')">
        <x-slot:actions>
            @can('roles.create')
                <x-ui.button :href="route('roles.create')" icon="plus">{{ __('New Role') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($roles->isEmpty())
            <x-ui.empty-state icon="roles" :title="__('No roles yet')" :description="__('Run the roles seeder to create the default roles.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Users') }}</th>
                    <th class="hidden px-4 py-3 text-right sm:table-cell">{{ __('Permissions') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($roles as $role)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('roles.show', $role) }}" class="font-medium text-gray-900 hover:text-primary-600">
                                {{ $role->name }}
                            </a>
                            @if ($role->name === 'Super Admin')
                                <span class="ml-2 inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ __('System') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $role->users_count }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-gray-600 sm:table-cell">
                            @if ($role->name === 'Super Admin')
                                <span class="text-gray-400">{{ __('All') }}</span>
                            @else
                                {{ $role->permissions_count }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button :href="route('roles.show', $role)" icon="eye" :label="__('View')" />
                                @can('roles.edit')
                                    @if ($role->name !== 'Super Admin')
                                        <x-ui.icon-button :href="route('roles.edit', $role)" icon="pencil" :label="__('Edit')" />
                                    @endif
                                @endcan
                                @can('roles.delete')
                                    @if ($role->name !== 'Super Admin')
                                        <x-ui.icon-button
                                            icon="trash"
                                            variant="danger"
                                            :label="__('Delete')"
                                            :confirm="[
                                                'action' => route('roles.destroy', $role),
                                                'title' => __('Delete role \":name\"?', ['name' => $role->name]),
                                                'message' => __('Users in this role will lose their permissions.'),
                                            ]"
                                        />
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</x-app-layout>
