import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // app.js imports Bootstrap first, then app.css, so our overrides win.
            input: ['resources/js/app.js'],
            refresh: true,
        }),
    ],
});
