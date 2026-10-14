import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            // Paleta institucional: azul noche (marca), oro (acento) y marfil (fondos).
            colors: {
                marca: {
                    50: '#F3F5FB', 100: '#E4E8F5', 200: '#C8D0EA', 300: '#9AA8D4', 400: '#6A7DB8',
                    500: '#44579A', 600: '#2F4180', 700: '#20306A', 800: '#15214F', 900: '#0D1638', 950: '#080E26',
                },
                oro: {
                    50: '#FDF9EC', 100: '#FAF0CF', 200: '#F4DE9B', 300: '#ECC862', 400: '#E2B13B',
                    500: '#C99A2E', 600: '#A87B22', 700: '#84601E', 800: '#6B4D1F', 900: '#59401D',
                },
                marfil: '#FBF9F4',
            },
        },
    },

    plugins: [forms, typography],
};
