@use('App\Enums\RecordStatus')

{{-- $customer: App\Models\Customer (new or existing) --}}
<form
    method="POST"
    action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
>
    @csrf
    @if ($customer->exists)
        @method('PUT')
    @endif

    <div class="space-y-6 lg:col-span-2">
        <x-ui.card :title="__('Customer')">
            <div class="space-y-5">
                <x-ui.input name="name" :label="__('Name')" :value="$customer->name" required maxlength="150" autofocus />
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="phone" type="tel" :label="__('Phone')" :value="$customer->phone" maxlength="30" />
                    <x-ui.input name="email" type="email" :label="__('Email')" :value="$customer->email" maxlength="150" />
                </div>
                <x-ui.textarea name="address" :label="__('Address')" :value="$customer->address" rows="3" maxlength="1000" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Notes')">
            <x-ui.textarea name="notes" :value="$customer->notes" rows="4" maxlength="2000" :aria-label="__('Notes')" :placeholder="__('Preferences, allergies, delivery instructions…')" />
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card :title="__('Status')">
            <x-ui.select name="status" :options="RecordStatus::options()" :selected="$customer->status?->value ?? RecordStatus::Active->value" :aria-label="__('Status')" required />
        </x-ui.card>
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="$customer->exists ? route('customers.show', $customer) : route('customers.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $customer->exists ? __('Save changes') : __('Create customer') }}</x-ui.button>
    </div>
</form>
