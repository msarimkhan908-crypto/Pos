{{--
    Single-series daily sales bar chart (HTML/CSS, no chart library).
    $days: list of ['date' => CarbonImmutable, 'total' => float, 'orders' => int], oldest first.
--}}
@props(['days', 'currency'])

@use('Illuminate\Support\Number')

@php
    $max = max(array_column($days, 'total') ?: [0]);
    $hasData = $max > 0;

    // Round the scale up to a readable number (e.g. 1,340 -> 2,000).
    $magnitude = $hasData ? 10 ** floor(log10($max)) : 1;
    $scaleMax = $hasData ? ceil($max / $magnitude) * $magnitude : 1;

    $gridlines = [1, 0.75, 0.5, 0.25, 0];
    $labelEvery = count($days) > 10 ? 5 : 1;
    $lastIndex = count($days) - 1;
@endphp

<div {{ $attributes }}>
    <div class="flex gap-3">
        {{-- Y axis --}}
        <div class="flex h-56 w-12 shrink-0 flex-col justify-between text-end text-[11px] tabular-nums text-gray-400" aria-hidden="true">
            @foreach ($gridlines as $step)
                <span class="-translate-y-1/2 first:translate-y-0 last:translate-y-0">
                    {{ $hasData ? Number::abbreviate($scaleMax * $step, maxPrecision: 1) : ($step === 0 ? '0' : '') }}
                </span>
            @endforeach
        </div>

        {{-- Plot --}}
        <div class="relative min-w-0 flex-1">
            <div class="pointer-events-none absolute inset-x-0 top-0 flex h-56 flex-col justify-between" aria-hidden="true">
                @foreach ($gridlines as $step)
                    <div @class(['border-t', 'border-gray-300' => $step === 0, 'border-dashed border-gray-100' => $step !== 0])></div>
                @endforeach
            </div>

            <div class="relative flex h-56 items-end" aria-hidden="true">
                @foreach ($days as $i => $day)
                    <div class="group relative flex h-full flex-1 items-end justify-center px-px sm:px-0.5" tabindex="{{ $hasData ? 0 : -1 }}">
                        <div
                            class="w-full max-w-[2.5rem] rounded-t bg-primary-500 transition-colors group-hover:bg-primary-600 group-focus:bg-primary-600"
                            style="height: {{ round($day['total'] / $scaleMax * 100, 2) }}%"
                        ></div>

                        @if ($hasData)
                            <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus:block">
                                <p class="font-medium">{{ $day['date']->isoFormat('ddd, D MMM') }}</p>
                                <p class="tabular-nums text-gray-300">{{ \App\Support\Money::format($day['total']) }} · {{ trans_choice(':count order|:count orders', $day['orders']) }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @unless ($hasData)
                <div class="absolute inset-x-0 top-0 flex h-56 items-center justify-center">
                    <div class="rounded-lg bg-white/90 px-4 py-3 text-center">
                        <x-icon name="trending-up" class="mx-auto size-6 text-gray-300" />
                        <p class="mt-1.5 text-sm font-medium text-gray-700">{{ __('No sales recorded in this period') }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('Daily sales will be charted here.') }}</p>
                    </div>
                </div>
            @endunless

            {{-- X axis --}}
            <div class="mt-2 flex text-[11px] text-gray-400" aria-hidden="true">
                @foreach ($days as $i => $day)
                    <span @class(['flex-1 truncate text-center', 'font-semibold text-gray-600' => $i === $lastIndex])>
                        @if ($i % $labelEvery === 0 || $i === $lastIndex)
                            {{ $labelEvery > 1 ? $day['date']->isoFormat('D MMM') : $day['date']->isoFormat('ddd') }}
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Accessible data table --}}
    <table class="sr-only">
        <caption>{{ __('Daily sales') }}</caption>
        <thead>
            <tr><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Sales') }}</th><th scope="col">{{ __('Orders') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($days as $day)
                <tr>
                    <th scope="row">{{ $day['date']->isoFormat('dddd, D MMMM') }}</th>
                    <td>{{ \App\Support\Money::format($day['total']) }}</td>
                    <td>{{ $day['orders'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
