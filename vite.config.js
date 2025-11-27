import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/leaflet-map-style.css',
                'resources/js/leafletMapComponent.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
