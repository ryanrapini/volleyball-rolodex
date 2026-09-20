import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import Aura from '@primeuix/themes/aura';
import 'primeicons/primeicons.css';

const appName = import.meta.env.VITE_APP_NAME || 'Volleyball Rolodex';

createInertiaApp({
    // Pages whose title is already the app name don't need it twice.
    title: (title) => (title && title !== appName ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        // The app is light-only, so PrimeVue should not follow the
                        // operating system's dark preference.
                        darkModeSelector: false,
                        cssLayer: false,
                    },
                },
            })
            .use(ToastService)
            .mount(el);
    },
    progress: {
        color: '#3B82F6',
    },
});
