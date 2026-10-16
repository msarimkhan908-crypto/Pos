<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        <!-- Restore sidebar and theme state before first paint to avoid flash -->
        <script>
            try {
                if (localStorage.getItem('pos.sidebar.collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
                // localStorage overrides the server default; server default overrides 'auto'
                const serverDefault = '{{ settings('appearance.color_scheme', 'auto') }}';
                const t = localStorage.getItem('pos.theme') || serverDefault;
                if (t === 'dark' || (t === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-theme />
    </head>
    <body
        class="h-full bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100"
        x-data="appShell"
        x-on:keydown.escape.window="sidebarOpen = false"
        x-on:resize.window.debounce.150ms="onResize()"
    >
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:shadow-lg">
            {{ __('Skip to content') }}
        </a>

        <x-layout.navbar />

        <!-- Backdrop for the mobile / tablet drawer -->
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            x-cloak
            x-on:click="sidebarOpen = false"
            class="fixed inset-0 top-16 z-20 bg-gray-900/40 lg:hidden"
            aria-hidden="true"
        ></div>

        <x-layout.sidebar />

        <div class="app-main min-h-full">
            @isset($header)
                <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main id="main-content" class="px-4 py-6 sm:px-6 lg:px-8">
                @if (session('success') || session('error'))
                    <div class="mb-6 space-y-3">
                        @if (session('success'))
                            <x-ui.alert type="success" dismissible>{{ session('success') }}</x-ui.alert>
                        @endif

                        @if (session('error'))
                            <x-ui.alert type="danger" dismissible>{{ session('error') }}</x-ui.alert>
                        @endif
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        <x-ui.confirm-dialog />
        @stack('scripts')
    </body>
</html>
