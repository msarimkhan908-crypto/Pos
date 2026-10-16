<?php

namespace App\Enums;

/**
 * How much of a sale has been returned (pending and completed returns count; cancelled don't).
 */
enum SaleReturnStatus: string
{
    case None = 'none';
    case Partial = 'partial';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::None => __('Not returned'),
            self::Partial => __('Partially returned'),
            self::Full => __('Fully returned'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Partial => 'warning',
            self::Full => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
