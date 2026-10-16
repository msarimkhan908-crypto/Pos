<x-app-layout>
    <x-slot name="title">{{ $role->name }}</x-slot>

    <x-ui.page-header :title="$role->name" :description="__(':count permission(s)', ['count' => $role->permissions->count()])">
        <x-slot:actions>
            <x-ui.button :href="route('roles.index')" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-ui.button>
            @can('roles.edit')
                @if ($role->name !== 'Super Admin')
                    <x-ui.button :href="route('roles.edit', $role)" variant="secondary" icon="pencil">{{ __('Edit') }}</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($role->name === 'Super Admin')
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('Super Admin has every permission and cannot be edited.') }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($grouped as $module => $group)
            <x-ui.card :title="$group['label']">
                <ul class="space-y-1.5 text-sm">
                    @foreach ($group['permissions'] as $action => $perm)
                        <li class="flex items-center gap-2">
                            @if ($perm['active'] || $role->name === 'Super Admin')
                                <x-icon name="check-circle" class="h-4 w-4 shrink-0 text-green-500" />
                                <span class="text-gray-800">{{ $perm['label'] }}</span>
                            @else
                                <span class="h-4 w-4 shrink-0 rounded-full border-2 border-gray-200"></span>
                                <span class="text-gray-400">{{ $perm['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endforeach
    </div>
</x-app-layout>
