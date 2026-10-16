<?php

namespace App\Support;

use App\Settings\SettingDefinitions;

/**
 * Builds Tailwind-style 50–950 colour scales from one hex colour, as "r g b" triplets for
 * CSS variables (tailwind.config.js maps primary / secondary / btn to these variables).
 * The chosen colour becomes shade 600.
 */
class Palette
{
    /** Shade => [mix colour, amount]; the base colour is 600. */
    private const STEPS = [
        50 => ['white', 0.95], 100 => ['white', 0.9], 200 => ['white', 0.75], 300 => ['white', 0.55],
        400 => ['white', 0.3], 500 => ['white', 0.12], 600 => ['white', 0.0], 700 => ['black', 0.15],
        800 => ['black', 0.3], 900 => ['black', 0.45], 950 => ['black', 0.6],
    ];

    public const HEX = '/^#[0-9a-fA-F]{6}$/';

    /**
     * @return array<int, string> shade => "r g b"
     */
    public static function scale(string $hex): array
    {
        [$r, $g, $b] = self::rgb($hex);

        return collect(self::STEPS)->map(function (array $step) use ($r, $g, $b) {
            [$with, $amount] = $step;
            $target = $with === 'white' ? 255 : 0;
            $mix = fn (int $channel) => (int) round($channel + ($target - $channel) * $amount);

            return $mix($r).' '.$mix($g).' '.$mix($b);
        })->all();
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = preg_match(self::HEX, $hex) ? ltrim($hex, '#') : '4f46e5';

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * CSS custom properties for the current appearance settings.
     */
    public static function css(): string
    {
        $vars = [];

        foreach (['primary' => 'appearance.primary_color', 'secondary' => 'appearance.secondary_color', 'btn' => 'appearance.button_color'] as $name => $key) {
            foreach (self::scale((string) settings($key)) as $shade => $rgb) {
                $vars[] = "--color-{$name}-{$shade}: {$rgb};";
            }
        }

        $vars[] = '--sidebar-bg: '.implode(' ', self::rgb((string) settings('appearance.sidebar_color'))).';';
        $vars[] = '--sidebar-text: '.implode(' ', self::rgb((string) settings('appearance.sidebar_text_color'))).';';

        // Font family variables
        $appFontKey = (string) settings('appearance.font_family', 'figtree');
        $vars[] = '--app-font: '.FontRegistry::cssStack($appFontKey, 'Figtree, ui-sans-serif, system-ui, sans-serif').';';

        $receiptConfig = settings()->receipt();
        $receiptFontKey = $receiptConfig['font_family'] ?? 'courier-prime';
        $vars[] = '--receipt-font: '.FontRegistry::cssStack($receiptFontKey).';';

        $weightKey = $receiptConfig['font_weight'] ?? 'bold';
        $vars[] = '--receipt-weight: '.(SettingDefinitions::FONT_WEIGHTS[$weightKey] ?? 700).';';

        $headingFontKey = $receiptConfig['heading_font'] ?? '';
        if ($headingFontKey && FontRegistry::has($headingFontKey)) {
            $vars[] = '--receipt-heading-font: '.FontRegistry::cssStack($headingFontKey).';';
        }

        $kotConfig = settings()->kot();
        $kotFontKey = $kotConfig['font_family'] ?? 'courier-prime';
        $vars[] = '--kot-font: '.FontRegistry::cssStack($kotFontKey).';';

        return ':root{'.implode('', $vars).'}';
    }
}
