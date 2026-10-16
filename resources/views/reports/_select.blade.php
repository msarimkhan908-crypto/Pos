{{-- A filter <select> for the report toolbar. --}}
<label class="block lg:w-44">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
    <select name="{{ $name }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
</label>
