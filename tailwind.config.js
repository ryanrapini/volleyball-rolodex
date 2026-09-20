/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    /*
     * No theme overrides: PrimeVue's Aura preset owns colours, typography and
     * elevation now, and Tailwind is only here for layout utilities. The
     * @tailwindcss/forms plugin is gone with it, since every input in the app
     * is a PrimeVue component.
     */
    theme: {},

    plugins: [],
};
