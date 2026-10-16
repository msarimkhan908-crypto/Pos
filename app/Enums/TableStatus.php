<?php

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Occupied => __('Occupied'),
            self::Reserved => __('Reserved'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Occupied => 'danger',
            self::Reserved => 'warning',
        };
    }
}
