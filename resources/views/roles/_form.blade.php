{{-- $role (Role), $grouped (array from RoleController::groupedPermissions) --}}
<x-ui.card>
    <x-ui.input
        name="name"
        :label="__('Role name')"
        :value="old('name', $role->name)"
        :placeholder="__('e.g. Supervisor')"
        required
        class="mb-5 max-w-sm"
    />

    <p class="mb-3 text-sm font-medium text-gray-700">{{ __('Permissions') }}</p>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($grouped as $module => $group)
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $group['label'] }}</p>
                <ul class="space-y-1.5">
                    @foreach ($group['permissions'] as $action => $perm)
                        <li>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $perm['name'] }}"
                                    @checked(old('permissions') ? in_array($perm['name'], (array) old('permissions')) : $perm['active'])
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                >
                                <span class="text-gray-700 dark:text-gray-300">{{ $perm['label'] }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-ui.card>
