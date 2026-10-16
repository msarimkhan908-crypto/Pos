<?php

namespace App\Http\Requests;

use App\Rules\ImageUpload;
use App\Settings\SettingDefinitions;
use App\Support\FontRegistry;
use App\Support\ImageStorage;
use App\Support\Palette;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one Settings section and turns it into setting key => value pairs.
 * Saving needs settings.manage; the receipt / KOT live preview needs settings.view.
 */
class SettingsRequest extends FormRequest
{
    /** Longest side allowed for logos and receipt header images, in pixels. */
    public const MAX_IMAGE_PX = 4000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->isPreview() ? 'settings.view' : 'settings.manage');
    }

    public function section(): string
    {
        return (string) ($this->route('section') ?? $this->route('kind'));
    }

    public function isPreview(): bool
    {
        return $this->routeIs('settings.preview');
    }

    /**
     * Remove any uploaded files from preview requests before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isPreview()) {
            foreach (['header_image', 'logo'] as $field) {
                $this->files->remove($field);
            }
        }
    }

    /**
     * For preview requests, swallow ALL validation failures silently.
     * The preview controller only needs raw input — no image uploads, no strict rules.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->isPreview()) {
            return;
        }
        parent::failedValidation($validator);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Preview requests never need to upload images — skip image validation entirely.
        $image = $this->isPreview() ? ['nullable'] : [...ImageUpload::rules(), 'dimensions:max_width='.self::MAX_IMAGE_PX.',max_height='.self::MAX_IMAGE_PX];
        $prefix = ['required', 'string', 'regex:/^[A-Za-z0-9\-\/_]{1,10}$/'];
        $color = ['required', 'regex:'.Palette::HEX];
        $booleans = fn (array $defaults) => collect($defaults)->filter(fn ($v) => is_bool($v))->mapWithKeys(fn ($v, $k) => [$k => ['nullable', 'boolean']])->all();

        return match ($this->section()) {
            'business' => [
                'name' => ['required', 'string', 'max:150'],
                'address' => ['nullable', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'regex:'.SupplierRequest::PHONE_PATTERN],
                'email' => ['nullable', 'email', 'max:150'],
                'website' => ['nullable', 'url:http,https', 'max:200'],
                'tax_number' => ['nullable', 'string', 'max:50'],
                'registration_number' => ['nullable', 'string', 'max:50'],
                'logo' => $image,
                'remove_logo' => ['nullable', 'boolean'],
            ],
            'general' => [
                'business_day_start' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
                'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
                'currency_symbol' => ['nullable', 'string', 'max:5'],
                'date_format' => ['required', Rule::in(SettingDefinitions::DATE_FORMATS)],
                'time_format' => ['required', Rule::in(SettingDefinitions::TIME_FORMATS)],
                'timezone' => ['required', 'timezone:all'],
                'invoice_prefix' => $prefix,
                'purchase_prefix' => $prefix,
                'return_prefix' => $prefix,
                'expense_prefix' => $prefix,
                'kot_prefix' => $prefix,
                'hide_trailing_zeros' => ['nullable', 'boolean'],
            ],
            'appearance' => [
                'theme' => ['required', Rule::in([...array_keys(SettingDefinitions::THEMES), 'custom'])],
                'font_family' => ['nullable', Rule::in(FontRegistry::keys())],
                'primary_color' => $color,
                'secondary_color' => $color,
                'button_color' => $color,
                'sidebar_color' => $color,
                'sidebar_text_color' => $color,
            ],
            'dashboard' => [
                'widgets' => ['nullable', 'array'],
                'widgets.*' => [Rule::in(SettingDefinitions::DASHBOARD_WIDGETS)],
            ],
            'tax' => [
                'default_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
                'label' => ['required', 'string', 'max:20'],
            ],
            'pos' => [
                'auto_receipt' => ['nullable', 'boolean'],
                'auto_kot' => ['nullable', 'boolean'],
                'layout' => ['required', Rule::in(['combined', 'separate'])],
                'bg_color' => ['required', 'regex:'.Palette::HEX],
                'header_bg_color' => ['required', 'regex:'.Palette::HEX],
                'cart_bg_color' => ['required', 'regex:'.Palette::HEX],
            ],
            'receipt' => [
                'width' => ['required', Rule::in(SettingDefinitions::RECEIPT_WIDTHS)],
                'font_family' => ['nullable', Rule::in(['', ...FontRegistry::keys()])],
                'heading_font' => ['nullable', Rule::in(['', ...FontRegistry::keys()])],
                'font_size' => ['required', Rule::in(SettingDefinitions::FONT_SIZES)],
                'font_weight' => ['required', Rule::in(array_keys(SettingDefinitions::FONT_WEIGHTS))],
                'header_align' => ['required', Rule::in(SettingDefinitions::ALIGNMENTS)],
                'footer_align' => ['required', Rule::in(SettingDefinitions::ALIGNMENTS)],
                'item_layout' => ['required', Rule::in(['detailed', 'compact'])],
                'logo_size' => ['required', Rule::in(['sm', 'md', 'lg', 'custom'])],
                'logo_custom_height' => ['nullable', 'integer', 'min:20', 'max:300'],
                'header_text' => ['nullable', 'string', 'max:200'],
                'footer_text' => ['nullable', 'string', 'max:300'],
                'notes' => ['nullable', 'string', 'max:500'],
                'header_image' => $image,
                'remove_header_image' => ['nullable', 'boolean'],
                ...$booleans(SettingDefinitions::receiptDefaults()),
            ],
            'kot' => [
                'width' => ['required', Rule::in(SettingDefinitions::KOT_WIDTHS)],
                'font_family' => ['nullable', Rule::in(['', ...FontRegistry::keys()])],
                'font_size' => ['required', Rule::in(SettingDefinitions::FONT_SIZES)],
                'item_size' => ['required', Rule::in(SettingDefinitions::KOT_ITEM_SIZES)],
                'align' => ['required', Rule::in(SettingDefinitions::ALIGNMENTS)],
                'header_text' => ['nullable', 'string', 'max:100'],
                'footer_text' => ['nullable', 'string', 'max:200'],
                ...$booleans(SettingDefinitions::kotDefaults()),
            ],
            default => abort(404),
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...ImageUpload::messages('logo'),
            ...ImageUpload::messages('header_image'),
            'logo.dimensions' => __('The logo must be at most :px pixels wide and tall.', ['px' => self::MAX_IMAGE_PX]),
            'header_image.dimensions' => __('The header image must be at most :px pixels wide and tall.', ['px' => self::MAX_IMAGE_PX]),
            'currency.regex' => __('Use a 3-letter currency code such as PKR or USD.'),
            '*_prefix.regex' => __('Prefixes may use letters, numbers, - / _ and be at most 10 characters.'),
            '*_color.regex' => __('Choose a colour like #4f46e5.'),
        ];
    }

    /**
     * Setting key => value pairs to save (uploads are stored here).
     *
     * @return array<string, mixed>
     */
    public function settingsValues(): array
    {
        $v = fn (string $key) => $this->validated($key);

        return match ($this->section()) {
            'business' => [
                'business.name' => $v('name'),
                'business.address' => $v('address'),
                'business.phone' => $v('phone'),
                'business.email' => $v('email'),
                'business.website' => $v('website'),
                'business.tax_number' => $v('tax_number'),
                'business.registration_number' => $v('registration_number'),
                'business.logo' => ImageStorage::replace($this->file('logo'), settings('business.logo'), 'settings', $this->boolean('remove_logo')),
            ],
            'general' => [
                ...collect(['business_day_start', 'currency', 'currency_symbol', 'date_format', 'time_format', 'timezone', 'invoice_prefix', 'purchase_prefix', 'return_prefix', 'expense_prefix', 'kot_prefix'])
                    ->mapWithKeys(fn (string $key) => ["general.{$key}" => $v($key)])->all(),
                'general.hide_trailing_zeros' => $this->boolean('hide_trailing_zeros'),
            ],
            'appearance' => [
                ...collect(['theme', 'primary_color', 'secondary_color', 'button_color', 'sidebar_color', 'sidebar_text_color'])
                    ->mapWithKeys(fn (string $key) => ["appearance.{$key}" => strtolower((string) $v($key))])->all(),
                'appearance.font_family' => $v('font_family') ?: 'figtree',
            ],
            'dashboard' => ['dashboard.widgets' => array_values(array_intersect(SettingDefinitions::DASHBOARD_WIDGETS, (array) $v('widgets')))],
            'tax' => ['tax.default_rate' => $v('default_rate'), 'tax.label' => $v('label')],
            'pos' => [
                'print.auto_receipt' => $this->boolean('auto_receipt'),
                'print.auto_kot' => $this->boolean('auto_kot'),
                'print.layout' => $v('layout'),
                'pos.bg_color' => strtolower((string) $v('bg_color')),
                'pos.header_bg_color' => strtolower((string) $v('header_bg_color')),
                'pos.cart_bg_color' => strtolower((string) $v('cart_bg_color')),
            ],
            'receipt' => [
                'receipt.customer' => $this->receiptConfig(),
                'receipt.header_image' => ImageStorage::replace($this->file('header_image'), settings('receipt.header_image'), 'settings', $this->boolean('remove_header_image')),
                'receipt.desktop_printer' => $this->input('desktop_printer') ?: null,
                'receipt.kot_printer' => $this->input('kot_printer') ?: null,
            ],
            'kot' => ['receipt.kitchen' => $this->kotConfig()],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptConfig(): array
    {
        return $this->config(SettingDefinitions::receiptDefaults());
    }

    /**
     * @return array<string, mixed>
     */
    public function kotConfig(): array
    {
        return $this->config(SettingDefinitions::kotDefaults());
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function config(array $defaults): array
    {
        // Preview requests may not have passed validation — use raw input safely.
        $get = $this->isPreview()
            ? fn (string $k) => $this->input($k)
            : fn (string $k) => $this->validated($k);

        return collect($defaults)->map(fn ($default, string $key) => is_bool($default)
            ? $this->boolean($key)
            : (string) ($get($key) ?? ''))->all();
    }
}
