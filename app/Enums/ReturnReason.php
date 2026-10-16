<?php

namespace App\Enums;

enum ReturnReason: string
{
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case QualityIssue = 'quality_issue';
    case Expired = 'expired';
    case ChangedMind = 'changed_mind';
    case OrderMistake = 'order_mistake';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => __('Damaged / defective'),
            self::WrongItem => __('Wrong item'),
            self::QualityIssue => __('Quality issue'),
            self::Expired => __('Expired'),
            self::ChangedMind => __('Customer changed mind'),
            self::OrderMistake => __('Order mistake'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
