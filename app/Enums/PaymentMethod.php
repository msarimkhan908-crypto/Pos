<?php

namespace App\Enums;

/**
 * How a sale was paid. A sale is cash, card, easypaisa, jazzcash or mixed;
 * each sale_payments row uses one of the non-mixed methods.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Easypaisa = 'easypaisa';
    case JazzCash = 'jazzcash';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::Easypaisa => __('Easypaisa'),
            self::JazzCash => __('JazzCash'),
            self::Mixed => __('Mixed'),
        };
    }

    /** Returns true for digital wallet methods that behave like card (exact total, no change). */
    public function isWallet(): bool
    {
        return match ($this) {
            self::Easypaisa, self::JazzCash => true,
            default => false,
        };
    }
}
