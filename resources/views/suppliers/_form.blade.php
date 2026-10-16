@use('App\Enums\RecordStatus')

{{-- $supplier: App\Models\Supplier (new or existing) --}}
<form
    method="POST"
    action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
>
    @csrf
    @if ($supplier->exists)
        @method('PUT')
    @endif

    <div class="space-y-6 lg:col-span-2">
        <x-ui.card :title="__('Supplier')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.input name="name" :label="__('Supplier name')" :value="$supplier->name" required maxlength="150" autofocus />
                <x-ui.input name="company_name" :label="__('Company name')" :value="$supplier->company_name" maxlength="150" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Contact')">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="phone" type="tel" :label="__('Phone')" :value="$supplier->phone" maxlength="30" />
                    <x-ui.input name="email" type="email" :label="__('Email')" :value="$supplier->email" maxlength="150" />
                </div>
                <x-ui.textarea name="address" :label="__('Address')" :value="$supplier->address" rows="3" maxlength="1000" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Notes')">
            <x-ui.textarea name="notes" :value="$supplier->notes" rows="4" maxlength="2000" :aria-label="__('Notes')" :placeholder="__('Payment terms, delivery days, contact person…')" />
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card :title="__('Tax & registration')">
            <div class="space-y-5">
                <x-ui.input name="tax_number" :label="__('Tax number')" :value="$supplier->tax_number" maxlength="50" />
                <x-ui.input name="registration_number" :label="__('Registration number')" :value="$supplier->registration_number" maxlength="50" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Status')">
            <x-ui.select name="status" :options="RecordStatus::options()" :selected="$supplier->status?->value ?? RecordStatus::Active->value" :aria-label="__('Status')" :hint="__('Inactive suppliers cannot be chosen for new purchases.')" required />
        </x-ui.card>
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="$supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $supplier->exists ? __('Save changes') : __('Create supplier') }}</x-ui.button>
    </div>
</form>
