<?php

namespace App\Settings;

/**
 * Every application setting: its group (settings page section), type and default.
 * Values live in the `settings` table; anything not saved yet falls back to the default here.
 *
 * Types: string, integer, decimal, boolean, json.
 */
final class SettingDefinitions
{
    public const RECEIPT_WIDTHS = ['58mm', '80mm', 'a4'];

    public const KOT_WIDTHS = ['58mm', '80mm'];

    public const KOT_ITEM_SIZES = ['normal', 'large', 'extra_large'];

    public const FONT_SIZES = ['sm', 'md', 'lg'];

    public const FONT_WEIGHTS = ['normal' => 400, 'medium' => 500, 'semibold' => 600, 'bold' => 700, 'extrabold' => 800, 'black' => 900];

    public const ALIGNMENTS = ['left', 'center', 'right'];

    public const DATE_FORMATS = ['d M Y', 'd/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y'];

    public const TIME_FORMATS = ['H:i', 'h:i A'];

    /** Dashboard widgets that can be switched off (each still needs its permission). */
    public const DASHBOARD_WIDGETS = ['sales', 'purchases', 'returns', 'expenses', 'customers', 'sales_chart', 'recent_sales', 'recent_purchases', 'top_products', 'daily_report'];

    /** Colour presets for the "Theme" choice; "custom" keeps whatever colours were picked. */
    public const THEMES = [
        'indigo' => ['primary' => '#4f46e5', 'secondary' => '#059669', 'button' => '#4f46e5', 'sidebar' => '#ffffff', 'sidebar_text' => '#4b5563'],
        'blue' => ['primary' => '#2563eb', 'secondary' => '#0d9488', 'button' => '#2563eb', 'sidebar' => '#ffffff', 'sidebar_text' => '#4b5563'],
        'emerald' => ['primary' => '#059669', 'secondary' => '#2563eb', 'button' => '#059669', 'sidebar' => '#ffffff', 'sidebar_text' => '#4b5563'],
        'rose' => ['primary' => '#e11d48', 'secondary' => '#059669', 'button' => '#e11d48', 'sidebar' => '#ffffff', 'sidebar_text' => '#4b5563'],
        'amber' => ['primary' => '#d97706', 'secondary' => '#059669', 'button' => '#d97706', 'sidebar' => '#ffffff', 'sidebar_text' => '#4b5563'],
        'dark' => ['primary' => '#6366f1', 'secondary' => '#10b981', 'button' => '#4f46e5', 'sidebar' => '#111827', 'sidebar_text' => '#d1d5db'],
    ];

    /**
     * @return array<string, array{group: string, type: string, default: mixed}>
     */
    public static function all(): array
    {
        return [
            // Business information
            'business.name' => ['group' => 'business', 'type' => 'string', 'default' => config('pos.store_name')],
            'business.logo' => ['group' => 'business', 'type' => 'string', 'default' => null],
            'business.address' => ['group' => 'business', 'type' => 'string', 'default' => null],
            'business.phone' => ['group' => 'business', 'type' => 'string', 'default' => null],
            'business.email' => ['group' => 'business', 'type' => 'string', 'default' => null],
            'business.website' => ['group' => 'business', 'type' => 'string', 'default' => null],
            'business.tax_number' => ['group' => 'business', 'type' => 'string', 'default' => null],
            'business.registration_number' => ['group' => 'business', 'type' => 'string', 'default' => null],

            // General
            'general.business_day_start' => ['group' => 'general', 'type' => 'string', 'default' => '00:00'],
            'general.currency' => ['group' => 'general', 'type' => 'string', 'default' => config('pos.currency')],
            'general.currency_symbol' => ['group' => 'general', 'type' => 'string', 'default' => null],
            'general.date_format' => ['group' => 'general', 'type' => 'string', 'default' => 'd M Y'],
            'general.time_format' => ['group' => 'general', 'type' => 'string', 'default' => 'H:i'],
            'general.timezone' => ['group' => 'general', 'type' => 'string', 'default' => config('app.timezone')],
            'general.invoice_prefix' => ['group' => 'general', 'type' => 'string', 'default' => 'INV-'],
            'general.purchase_prefix' => ['group' => 'general', 'type' => 'string', 'default' => 'PUR-'],
            'general.return_prefix' => ['group' => 'general', 'type' => 'string', 'default' => 'RET-'],
            'general.expense_prefix' => ['group' => 'general', 'type' => 'string', 'default' => 'EXP-'],
            'general.kot_prefix' => ['group' => 'general', 'type' => 'string', 'default' => 'KOT-'],
            'general.hide_trailing_zeros' => ['group' => 'general', 'type' => 'boolean', 'default' => true],

            // Appearance
            'appearance.color_scheme' => ['group' => 'appearance', 'type' => 'string', 'default' => 'auto'],
            'appearance.font_family' => ['group' => 'appearance', 'type' => 'string', 'default' => 'figtree'],
            'appearance.theme' => ['group' => 'appearance', 'type' => 'string', 'default' => 'indigo'],
            'appearance.primary_color' => ['group' => 'appearance', 'type' => 'string', 'default' => '#4f46e5'],
            'appearance.secondary_color' => ['group' => 'appearance', 'type' => 'string', 'default' => '#059669'],
            'appearance.button_color' => ['group' => 'appearance', 'type' => 'string', 'default' => '#4f46e5'],
            'appearance.sidebar_color' => ['group' => 'appearance', 'type' => 'string', 'default' => '#ffffff'],
            'appearance.sidebar_text_color' => ['group' => 'appearance', 'type' => 'string', 'default' => '#4b5563'],

            // Dashboard
            'dashboard.widgets' => ['group' => 'dashboard', 'type' => 'json', 'default' => self::DASHBOARD_WIDGETS],

            // Tax
            'tax.default_rate' => ['group' => 'tax', 'type' => 'decimal', 'default' => '0'],
            'tax.label' => ['group' => 'tax', 'type' => 'string', 'default' => 'Tax'],

            // POS screen colours
            'pos.bg_color' => ['group' => 'pos', 'type' => 'string', 'default' => '#f3f4f6'],
            'pos.header_bg_color' => ['group' => 'pos', 'type' => 'string', 'default' => '#ffffff'],
            'pos.cart_bg_color' => ['group' => 'pos', 'type' => 'string', 'default' => '#ffffff'],

            // Printing
            'print.auto_receipt' => ['group' => 'printing', 'type' => 'boolean', 'default' => (bool) config('pos.auto_print', true)],
            'print.auto_kot' => ['group' => 'printing', 'type' => 'boolean', 'default' => (bool) config('pos.auto_print', true)],
            'print.layout' => ['group' => 'printing', 'type' => 'string', 'default' => 'combined'],

            // Customer receipt
            'receipt.customer' => ['group' => 'receipt', 'type' => 'json', 'default' => self::receiptDefaults()],
            'receipt.header_image' => ['group' => 'receipt', 'type' => 'string', 'default' => null],
            'receipt.desktop_printer' => ['group' => 'receipt', 'type' => 'string', 'default' => null],
            'receipt.kot_printer' => ['group' => 'receipt', 'type' => 'string', 'default' => null],

            // Kitchen receipt (KOT)
            'receipt.kitchen' => ['group' => 'kot', 'type' => 'json', 'default' => self::kotDefaults()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function receiptDefaults(): array
    {
        return [
            'width' => '80mm',
            'font_family' => 'courier-prime',
            'heading_font' => '',
            'font_size' => 'md',
            'font_weight' => 'bold',
            'header_align' => 'center',
            'footer_align' => 'center',
            'item_layout' => 'detailed',   // detailed: name, then "qty × price"; compact: one line per item
            'logo_size' => 'md',
            'logo_custom_height' => '',
            'show_logo' => true,
            'show_address' => true,
            'show_phone' => true,
            'show_email' => false,
            'show_website' => false,
            'show_tax_number' => true,
            'show_customer' => true,
            'show_cashier' => true,
            'show_order_type' => true,
            'show_discount' => true,
            'show_tax' => true,
            'show_payment' => true,
            'show_item_notes' => true,
            'show_footer' => true,
            'show_borders' => true,
            'show_separators' => true,
            'header_text' => '',
            'footer_text' => 'Thank you for your purchase!',
            'notes' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function kotDefaults(): array
    {
        return [
            'width' => '80mm',
            'font_family' => 'courier-prime',
            'font_size' => 'lg',
            'item_size' => 'large',
            'align' => 'center',
            'header_text' => 'KOT (Kitchen)',
            'show_logo' => false,
            'show_invoice' => true,
            'show_table' => true,
            'show_order_type' => true,
            'show_customer' => true,
            'show_cashier' => true,
            'show_notes' => true,
            'show_station_headings' => true,
            'show_separators' => true,
            'footer_text' => '',
        ];
    }

    /**
     * @return array{group: string, type: string, default: mixed}|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
