@php($b = settings()->business())
@php($org = auth()->user()->organization)

<x-ui.card :title="__('Business information')" :description="__('Shown on receipts, printed documents and in the app header.')">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.input name="name" :label="__('Business name')" :value="old('name', $b['name'])" required maxlength="150" class="sm:col-span-2" />
        <div class="sm:col-span-2">
            <x-ui.select name="business_type" :label="__('Business type')" :value="old('business_type', $org->business_type ?? 'general')">
                @foreach (\App\Models\Organization::BUSINESS_TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(old('business_type', $org->business_type ?? 'general') === $value)>{{ __($label) }}</option>
                @endforeach
            </x-ui.select>
            <p class="mt-1 text-xs text-gray-500">{{ __('Restaurant & Café accounts see Tables, Kitchen Orders, and Modifier menus. Other business types see a simpler interface.') }}</p>
        </div>
        <x-ui.input name="phone" :label="__('Phone')" :value="old('phone', $b['phone'])" maxlength="30" />
        <x-ui.input name="email" type="email" :label="__('Email')" :value="old('email', $b['email'])" maxlength="150" />
        <x-ui.input name="website" type="url" :label="__('Website')" :value="old('website', $b['website'])" maxlength="200" placeholder="https://" />
        <x-ui.input name="tax_number" :label="__('Tax number (NTN / GST / VAT)')" :value="old('tax_number', $b['tax_number'])" maxlength="50" />
        <x-ui.input name="registration_number" :label="__('Registration number')" :value="old('registration_number', $b['registration_number'])" maxlength="50" />
    </div>
    <div class="mt-5">
        <x-ui.textarea name="address" :label="__('Address')" :value="old('address', $b['address'])" rows="3" maxlength="500" />
    </div>
</x-ui.card>

<x-ui.card :title="__('Business logo')" :description="__('JPG, PNG or WEBP. Used in the header and on receipts (unless a receipt header image is set).')">
    <x-ui.image-upload name="logo" remove-name="remove_logo" :current="$b['logo_url']" />
</x-ui.card>
