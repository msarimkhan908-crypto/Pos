<?php

namespace App\Enums;

/**
 * Life of a kitchen order ticket and of each of its items:
 * Pending -> Preparing -> Ready -> Completed. Cancelled is reserved for voids (not used yet).
 */
enum KitchenStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Preparing => __('Preparing'),
            self::Ready => __('Ready'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Preparing => 'warning',
            self::Ready => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Position in the workflow; the kitchen only moves items forward.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Preparing => 1,
            self::Ready => 2,
            self::Completed => 3,
            self::Cancelled => 4,
        };
    }

    /**
     * Statuses the kitchen can move items to.
     *
     * @return list<self>
     */
    public static function targets(): array
    {
        return [self::Preparing, self::Ready, self::Completed];
    }

    /**
     * Statuses still in the kitchen.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Preparing, self::Ready];
    }
}
