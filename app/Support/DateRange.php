<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A reporting period in the business timezone: a preset (today, yesterday, this week,
 * this month, last month) or a custom from/to date range. Defaults to today.
 */
final class DateRange
{
    public const PRESETS = ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'custom'];

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
    ) {}

    /**
     * @throws ValidationException
     */
    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:'.implode(',', self::PRESETS)],
            'from' => ['nullable', 'required_if:period,custom', 'date'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from'],
        ]);

        return self::preset($data['period'] ?? 'today', $data['from'] ?? null, $data['to'] ?? null);
    }

    public static function preset(string $preset, ?string $from = null, ?string $to = null): self
    {
        $today = CarbonImmutable::today();

        [$start, $end] = match ($preset) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'this_week' => [$today->startOfWeek(), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'custom' => [CarbonImmutable::parse($from ?? $today), CarbonImmutable::parse($to ?? $from ?? $today)],
            default => [$today, $today],
        };

        return new self($start->startOfDay(), $end->endOfDay(), in_array($preset, self::PRESETS, true) ? $preset : 'today');
    }

    public function label(): string
    {
        $label = [
            'today' => __('Today'),
            'yesterday' => __('Yesterday'),
            'this_week' => __('This week'),
            'this_month' => __('This month'),
            'last_month' => __('Last month'),
            'custom' => __('Custom'),
        ][$this->preset];

        $dates = $this->from->isSameDay($this->to)
            ? Format::date($this->from)
            : Format::date($this->from).' – '.Format::date($this->to);

        return "{$label} · {$dates}";
    }

    /**
     * Query-string parameters to keep this period in links (pagination, exports, other reports).
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->preset === 'custom'
            ? ['period' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['period' => $this->preset];
    }
}
