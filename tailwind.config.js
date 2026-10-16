import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Brand colours come from CSS variables so they can be changed in Settings → Appearance
 * without rebuilding (see App\Support\Palette and <x-theme />). Defaults live in app.css.
 */
const scale = (name) => Object.fromEntries(
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((shade) => [shade, `rgb(var(--color-${name}-${shade}) / <alpha-value>)`]),
);

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['var(--app-font, Figtree)', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: scale('primary'),
                secondary: scale('secondary'),
                btn: scale('btn'),
                sidebar: {
                    DEFAULT: 'rgb(var(--sidebar-bg) / <alpha-value>)',
                    text: 'rgb(var(--sidebar-text) / <alpha-value>)',
                },
            },
        },
    },

    plugins: [forms],
};
