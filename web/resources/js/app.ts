import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true, import: 'default' });

void createInertiaApp({
    resolve: (name) => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
