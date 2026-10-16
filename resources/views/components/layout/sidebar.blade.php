<aside
    id="app-sidebar"
    class="app-sidebar"
    x-bind:class="{ 'is-open': sidebarOpen }"
    aria-label="{{ __('Main navigation') }}"
>
    <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4">
        @foreach ($sections as $section)
            <div @class(['mt-6' => ! $loop->first])>
                @if ($section['heading'])
                    <p class="rail-hide mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-sidebar-text/60">
                        {{ $section['heading'] }}
                    </p>
                    <div class="rail-only mx-auto mb-3 h-px w-8 bg-sidebar-text/20" aria-hidden="true"></div>
                @endif

                <ul role="list" class="space-y-0.5">
                    @foreach ($section['items'] as $item)
                        <li>
                            <x-layout.sidebar-link :item="$item" />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- Sync status footer — only shown when DEVICE_ID is set (desktop mode) --}}
    @if (config('app.device_id'))
        <div
            class="rail-hide shrink-0 border-t border-sidebar-text/10 px-4 py-2"
            x-data="syncStatus()"
            x-init="init()"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-sidebar-text/50">{{ __('Sync') }}</p>
                    <p class="truncate text-[11px] text-sidebar-text/60" x-text="label">{{ __('Checking…') }}</p>
                </div>
                <form method="POST" action="{{ route('desktop.sync') }}">
                    @csrf
                    <button
                        type="submit"
                        title="{{ __('Sync now') }}"
                        class="flex items-center justify-center rounded p-1 text-sidebar-text/50 transition hover:bg-sidebar-text/10 hover:text-sidebar-text"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="shrink-0 border-t border-sidebar-text/10 px-4 py-3">
        <p class="rail-hide text-[11px] text-sidebar-text/50">
            {{ config('app.name') }} &middot; v{{ config('app.version') }}
            @if (config('app.device_id'))
                &middot; {{ config('app.device_id') }}
            @endif
        </p>
        <p class="rail-only text-center text-[10px] text-sidebar-text/50">
            v{{ config('app.version') }}
        </p>
    </div>

    @if (config('app.device_id'))
        @once
            @push('scripts')
            <script>
            function syncStatus() {
                return {
                    label: '{{ __("Checking…") }}',
                    init() {
                        fetch('{{ route("desktop.sync-status") }}')
                            .then(r => r.json())
                            .then(d => {
                                if (d.status === 'never') {
                                    this.label = '{{ __("Never synced") }}';
                                } else if (d.synced_at) {
                                    const diff = Math.round((Date.now() - new Date(d.synced_at)) / 60000);
                                    this.label = diff < 1
                                        ? '{{ __("Just now") }}'
                                        : diff < 60
                                            ? `${diff} {{ __("min ago") }}`
                                            : '{{ __("Over 1 hr ago") }}';
                                } else {
                                    this.label = '{{ __("Offline") }}';
                                }
                            })
                            .catch(() => { this.label = '{{ __("Offline") }}'; });
                    }
                };
            }
            </script>
            @endpush
        @endonce
    @endif
</aside>
