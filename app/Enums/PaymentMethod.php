<?php

namespace App\Enums;

/**
 * How a sale was paid. A sale is cash, card or mixed; each sale_payments row is cash or card.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::Mixed => __('Mixed'),
        };
    }
}
