<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') · SahulatPOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen flex">

{{-- Sidebar --}}
<aside class="w-64 min-h-screen bg-gray-900 flex flex-col flex-shrink-0">
    <div class="px-6 py-5 border-b border-gray-700">
        <div class="text-white font-bold text-lg">SahulatPOS</div>
        <div class="text-gray-400 text-xs mt-0.5">Admin Panel</div>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-1">
        <a href="{{ route('admin.dashboard') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                  {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a href="{{ route('admin.organizations.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                  {{ request()->routeIs('admin.organizations.*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            Organizations
        </a>
    </nav>

    <div class="px-4 py-4 border-t border-gray-700">
        <div class="text-xs text-gray-500 mb-3">Signed in as</div>
        <div class="text-sm text-gray-300 font-medium truncate">{{ auth()->user()->name }}</div>
        <div class="flex items-center gap-3 mt-3">
            <a href="{{ route('dashboard') }}"
               class="text-xs text-gray-400 hover:text-white transition">← POS Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                @csrf
                <button type="submit" class="text-xs text-gray-400 hover:text-red-400 transition">Logout</button>
            </form>
        </div>
    </div>
</aside>

{{-- Main content --}}
<div class="flex-1 flex flex-col min-h-screen overflow-auto">

    {{-- Top bar --}}
    <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-800">@yield('heading', 'Admin')</h1>
        <div class="text-sm text-gray-500">{{ now()->format('d M Y') }}</div>
    </header>

    {{-- Flash message --}}
    @if (session('success'))
        <div class="mx-8 mt-6 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Page content --}}
    <main class="flex-1 px-8 py-6">
        @yield('content')
    </main>
</div>

</body>
</html>
