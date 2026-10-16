{{--
    Page shell for printed documents: no sidebar, navbar or buttons in print (.no-print).
    ?autoprint=1 opens the print dialog on load.
--}}
@props(['title', 'back' => null, 'edit' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · {{ settings()->business()['name'] }}</title>
        @vite(['resources/css/app.css'])
        <x-theme />
    </head>
    <body class="bg-gray-100 py-6 antialiased" data-print-page>
        <div class="no-print mx-auto mb-4 flex max-w-sm gap-2 px-4">
            <button type="button" onclick="window.print()" class="flex-1 rounded-lg bg-btn-600 px-4 py-2 text-sm font-semibold text-white hover:bg-btn-500">{{ __('Print') }}</button>
            @if ($edit)
                <a href="{{ $edit['url'] }}" class="flex-1 rounded-lg bg-white px-4 py-2 text-center text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">{{ $edit['label'] }}</a>
            @endif
            @if ($back)
                <a href="{{ $back['url'] }}" class="flex-1 rounded-lg bg-white px-4 py-2 text-center text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">{{ $back['label'] }}</a>
            @endif
        </div>

        <div class="space-y-6">
            {{ $slot }}
        </div>

        @if (request()->boolean('autoprint') || request()->boolean('print'))
            <script>window.addEventListener('load', () => document.fonts.ready.then(() => window.print()));</script>
        @endif
    </body>
</html>
