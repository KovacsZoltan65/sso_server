import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import 'primeicons/primeicons.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const SsoPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#f1f8ff',
            100: '#dceeff',
            200: '#b9ddff',
            300: '#89c5ff',
            400: '#53a7ff',
            500: '#2f84ff',
            600: '#1f67db',
            700: '#1d53b1',
            800: '#20478d',
            900: '#213d73',
            950: '#17264a',
        },
    },
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: SsoPreset,
                    options: {
                        darkModeSelector: false,
                    },
                },
            })
            .use(ConfirmationService)
            .use(ToastService)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#2f84ff',
    },
});
