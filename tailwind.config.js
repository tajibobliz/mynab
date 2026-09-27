import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            // `base` solo como fondo: en `colors` generaría un `text-base` (color) que choca con el `text-base` de tamaño de fuente.
            backgroundColor: {
                base: '#0a0a0a',
            },
            colors: {
                surface: {
                    DEFAULT: '#141414',
                    elevated: '#1c1c1c',
                },
                border: {
                    DEFAULT: '#262626',
                },
                text: {
                    DEFAULT: '#fafafa',
                    secondary: '#a3a3a3',
                },
                accent: {
                    primary: '#22c55e',
                    secondary: '#8b5cf6',
                },
                status: {
                    success: '#22c55e',
                    warning: '#f59e0b',
                    danger: '#ef4444',
                    info: '#3b82f6',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
        },
    },

    plugins: [forms, typography],
};
