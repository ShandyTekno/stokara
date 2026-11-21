import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { applyTheme } from './composables/useTheme';

const defaultAppName = import.meta.env.VITE_APP_NAME || 'STOKARA';
let currentAppName = defaultAppName;

createInertiaApp({
    title: (title) => (title ? `${title} - ${currentAppName}` : currentAppName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const site = props.initialPage?.props?.site;
        if (site?.app_name) {
            currentAppName = site.app_name;
        }
        if (site?.settings) {
            applyTheme(site.settings);
        }

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
