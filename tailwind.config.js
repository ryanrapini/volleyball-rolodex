import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                ink: '#17204D',
                paper: '#FFFAFC',
                riso: {
                    pink: '#F237A1',
                    blue: '#2C40A7',
                },
            },
            fontFamily: {
                sans: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
                mono: ['Overpass Mono', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                print: '6px 6px 0 #2C40A7',
                'print-sm': '4px 4px 0 #2C40A7',
                'print-pink': '6px 6px 0 #F237A1',
            },
        },
    },

    plugins: [forms],
};
