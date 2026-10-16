<?php

namespace App\Exceptions;

use App\Enums\PurchaseStatus;
use RuntimeException;

/**
 * A purchase operation not allowed in the purchase's current status. Messages are user-safe.
 */
class PurchaseException extends RuntimeException
{
    public static function notEditable(PurchaseStatus $status): self
    {
        return new self(__('Only draft purchases can be edited. This purchase is :status.', ['status' => strtolower($status->label())]));
    }

    public static function invalidTransition(PurchaseStatus $from, PurchaseStatus $to): self
    {
        return new self(__('A :from purchase cannot be changed to :to.', [
            'from' => strtolower($from->label()),
            'to' => strtolower($to->label()),
        ]));
    }

    public static function cancelled(): self
    {
        return new self(__('This purchase is cancelled and can no longer be changed.'));
    }

    public static function notDeletable(): self
    {
        return new self(__('Only draft purchases can be deleted. Cancel it instead.'));
    }
}
