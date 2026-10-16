@php
    $user = auth()->user();
    $initials = collect(explode(' ', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $role = $user->getRoleNames()->first();
    $iconButton = 'inline-flex size-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200';
@endphp

<header class="fixed inset-x-0 top-0 z-40 h-16 border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex h-full items-center gap-2 px-3 sm:gap-3 sm:px-4">
        <!-- Mobile / tablet: open the navigation drawer -->
        <button
            type="button"
            class="{{ $iconButton }} lg:hidden"
            x-on:click="toggleSidebar()"
            x-bind:aria-expanded="sidebarOpen.toString()"
            aria-controls="app-sidebar"
        >
            <span class="sr-only">{{ __('Toggle navigation') }}</span>
            <x-icon name="menu" x-show="! sidebarOpen" />
            <x-icon name="x" x-show="sidebarOpen" x-cloak />
        </button>

        <!-- Desktop: collapse / expand the sidebar -->
        <button
            type="button"
            class="{{ $iconButton }} hidden lg:inline-flex"
            x-on:click="toggleCollapsed()"
            x-bind:aria-pressed="collapsed.toString()"
            aria-controls="app-sidebar"
        >
            <span class="sr-only">{{ __('Collapse sidebar') }}</span>
            <x-icon name="panel-left" />
        </button>

        <!-- Brand -->
        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            @php($business = settings()->business())
            @if ($business['logo_url'])
                <img src="{{ $business['logo_url'] }}" alt="" class="size-9 rounded-lg object-contain" data-business-logo>
            @else
                <span class="flex size-9 items-center justify-center rounded-lg bg-primary-600 text-white shadow-sm">
                    <x-icon name="store" class="size-5" />
                </span>
            @endif
            <span class="hidden text-base font-semibold tracking-tight text-gray-900 sm:block dark:text-gray-100" data-business-name>
                {{ $business['name'] }}
            </span>
        </a>

        <!-- Search (placeholder until global search is implemented) -->
        <div class="ms-4 hidden max-w-md flex-1 md:block">
            <label for="global-search" class="sr-only">{{ __('Search') }}</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                <input
                    id="global-search"
                    type="search"
                    disabled
                    placeholder="{{ __('Search products, orders, customers…') }}"
                    title="{{ __('Search is coming soon') }}"
                    class="block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 py-2 pe-3 ps-9 text-sm text-gray-500 placeholder:text-gray-400"
                >
            </div>
        </div>

        <div class="ms-auto flex items-center gap-1 sm:gap-2">
            <!-- Theme toggle -->
            <button
                type="button"
                class="{{ $iconButton }}"
                x-on:click="cycleTheme()"
                x-bind:title="{ light: '{{ __('Light mode') }}', dark: '{{ __('Dark mode') }}', auto: '{{ __('Auto (system)') }}' }[theme]"
                aria-label="{{ __('Toggle theme') }}"
            >
                <x-icon name="sun" x-show="theme === 'light'" />
                <x-icon name="moon" x-show="theme === 'dark'" x-cloak />
                <x-icon name="monitor" x-show="theme === 'auto'" x-cloak />
            </button>

            <!-- Notifications (placeholder) -->
            <x-dropdown align="right" width="w-72 sm:w-80" contentClasses="bg-white dark:bg-gray-800 dark:border dark:border-gray-700">
                <x-slot name="trigger">
                    <button type="button" class="{{ $iconButton }}">
                        <span class="sr-only">{{ __('Notifications') }}</span>
                        <x-icon name="bell" />
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Notifications') }}</p>
                    </div>
                    <div class="px-4 py-8 text-center">
                        <x-icon name="bell" class="mx-auto size-8 text-gray-300 dark:text-gray-600" />
                        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __("You're all caught up") }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Notifications will appear here.') }}</p>
                    </div>
                </x-slot>
            </x-dropdown>

            <div class="mx-1 hidden h-6 w-px bg-gray-200 sm:block dark:bg-gray-700" aria-hidden="true"></div>

            <!-- User menu -->
            <x-dropdown align="right" width="w-60" contentClasses="bg-white py-1 dark:bg-gray-800 dark:border dark:border-gray-700">
                <x-slot name="trigger">
                    <button type="button" class="flex items-center gap-2 rounded-lg p-1 text-start transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:pe-2 dark:hover:bg-gray-800">
                        <span class="flex size-8 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                            {{ $initials }}
                        </span>
                        <span class="hidden leading-tight sm:block">
                            <span class="block max-w-[10rem] truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $user->name }}</span>
                            @if ($role)
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $role }}</span>
                            @endif
                        </span>
                        <x-icon name="chevron-down" class="hidden size-4 text-gray-400 dark:text-gray-500 sm:block" />
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    </div>

                    <div class="py-1">
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700">
                            <x-icon name="user" class="size-4 text-gray-400 dark:text-gray-500" />
                            {{ __('Profile') }}
                        </a>
                    </div>

                    <div class="border-t border-gray-100 py-1 dark:border-gray-700">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-gray-700 transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700">
                                <x-icon name="log-out" class="size-4 text-gray-400 dark:text-gray-500" />
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </div>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</header>
