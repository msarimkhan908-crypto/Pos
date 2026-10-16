<?php

namespace App\Support;

/**
 * Registry of bundled fonts available for receipts, KOT tickets, and the app UI.
 * All fonts are installed via @fontsource npm packages — no CDN required.
 *
 * @phpstan-type FontDefinition array{label: string, css_stack: string, category: string, weights: int[]}
 */
final class FontRegistry
{
    /**
     * All registered fonts, keyed by slug.
     *
     * @return array<string, FontDefinition>
     */
    public static function all(): array
    {
        return [
            // Monospace
            'courier-prime' => [
                'label' => 'Courier Prime',
                'css_stack' => '"Courier Prime", ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],
            'roboto-mono' => [
                'label' => 'Roboto Mono',
                'css_stack' => '"Roboto Mono", ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],
            'jetbrains-mono' => [
                'label' => 'JetBrains Mono',
                'css_stack' => '"JetBrains Mono", ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],
            'ibm-plex-mono' => [
                'label' => 'IBM Plex Mono',
                'css_stack' => '"IBM Plex Mono", ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],
            'space-mono' => [
                'label' => 'Space Mono',
                'css_stack' => '"Space Mono", ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],
            'inconsolata' => [
                'label' => 'Inconsolata',
                'css_stack' => 'Inconsolata, ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],
            'source-code-pro' => [
                'label' => 'Source Code Pro',
                'css_stack' => '"Source Code Pro", ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],
            'ubuntu-mono' => [
                'label' => 'Ubuntu Mono',
                'css_stack' => '"Ubuntu Mono", ui-monospace, monospace',
                'category' => 'monospace',
                'weights' => [400, 700],
            ],

            // Sans-serif
            'figtree' => [
                'label' => 'Figtree (default)',
                'css_stack' => 'Figtree, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 500, 600, 700],
            ],
            'inter' => [
                'label' => 'Inter',
                'css_stack' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 500, 700],
            ],
            'roboto' => [
                'label' => 'Roboto',
                'css_stack' => 'Roboto, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 500, 700],
            ],
            'open-sans' => [
                'label' => 'Open Sans',
                'css_stack' => '"Open Sans", ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 600, 700],
            ],
            'lato' => [
                'label' => 'Lato',
                'css_stack' => 'Lato, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 700],
            ],
            'poppins' => [
                'label' => 'Poppins',
                'css_stack' => 'Poppins, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 500, 600, 700],
            ],
            'montserrat' => [
                'label' => 'Montserrat',
                'css_stack' => 'Montserrat, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 500, 600, 700],
            ],
            'nunito' => [
                'label' => 'Nunito',
                'css_stack' => 'Nunito, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 600, 700],
            ],
            'rubik' => [
                'label' => 'Rubik',
                'css_stack' => 'Rubik, ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 500, 700],
            ],
            'noto-sans' => [
                'label' => 'Noto Sans',
                'css_stack' => '"Noto Sans", ui-sans-serif, system-ui, sans-serif',
                'category' => 'sans',
                'weights' => [400, 700],
            ],

            // Condensed
            'oswald' => [
                'label' => 'Oswald',
                'css_stack' => 'Oswald, ui-sans-serif, system-ui, sans-serif',
                'category' => 'condensed',
                'weights' => [400, 500, 700],
            ],
            'barlow-condensed' => [
                'label' => 'Barlow Condensed',
                'css_stack' => '"Barlow Condensed", ui-sans-serif, system-ui, sans-serif',
                'category' => 'condensed',
                'weights' => [400, 600, 700],
            ],
            'roboto-condensed' => [
                'label' => 'Roboto Condensed',
                'css_stack' => '"Roboto Condensed", ui-sans-serif, system-ui, sans-serif',
                'category' => 'condensed',
                'weights' => [400, 700],
            ],

            // Slab serif
            'roboto-slab' => [
                'label' => 'Roboto Slab',
                'css_stack' => '"Roboto Slab", Georgia, serif',
                'category' => 'slab',
                'weights' => [400, 700],
            ],

            // Display
            'bebas-neue' => [
                'label' => 'Bebas Neue',
                'css_stack' => '"Bebas Neue", Impact, ui-sans-serif, sans-serif',
                'category' => 'display',
                'weights' => [400],
            ],

            // Urdu / Arabic
            'noto-nastaliq-urdu' => [
                'label' => 'Noto Nastaliq Urdu',
                'css_stack' => '"Noto Nastaliq Urdu", serif',
                'category' => 'urdu',
                'weights' => [400, 700],
            ],
            'noto-naskh-arabic' => [
                'label' => 'Noto Naskh Arabic',
                'css_stack' => '"Noto Naskh Arabic", serif',
                'category' => 'arabic',
                'weights' => [400, 700],
            ],
        ];
    }

    /**
     * All valid font keys for validation.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * The CSS font-family stack for a given key.
     * Returns the default monospace stack when the key is empty or unknown.
     */
    public static function cssStack(string $key, string $default = "ui-monospace, SFMono-Regular, Menlo, Consolas, 'Courier New', monospace"): string
    {
        return self::all()[$key]['css_stack'] ?? $default;
    }

    /**
     * Whether a key is in the registry.
     */
    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Select options, grouped by category.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::all())
            ->mapWithKeys(fn (array $font, string $key) => [$key => $font['label']])
            ->all();
    }
}
