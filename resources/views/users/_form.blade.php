{{-- $user (User model), $roles (name => name map), $editing (bool) --}}
<x-ui.card>
    <div class="grid gap-5 sm:grid-cols-2">

        <div class="sm:col-span-2">
            <x-ui.input
                name="name"
                :label="__('Full name')"
                :value="old('name', $user->name)"
                :placeholder="__('e.g. Ahmed Khan')"
                required
                autocomplete="name"
            />
        </div>

        <div class="sm:col-span-2">
            <x-ui.input
                name="email"
                type="email"
                :label="__('Email address')"
                :value="old('email', $user->email)"
                :placeholder="__('user@example.com')"
                required
                autocomplete="email"
            />
        </div>

        <div>
            <x-ui.input
                name="password"
                type="password"
                :label="$editing ? __('New password') . ' ' . __('(leave blank to keep current)') : __('Password')"
                :required="! $editing"
                autocomplete="{{ $editing ? 'new-password' : 'new-password' }}"
            />
        </div>

        <div>
            <x-ui.input
                name="password_confirmation"
                type="password"
                :label="__('Confirm password')"
                :required="! $editing"
                autocomplete="new-password"
            />
        </div>

        <div class="sm:col-span-2">
            <x-ui.select
                name="role"
                :label="__('Role')"
                :options="$roles"
                :selected="old('role', $user->roles->first()?->name)"
                :placeholder="__('Select a role…')"
                required
            />
        </div>

    </div>
</x-ui.card>
