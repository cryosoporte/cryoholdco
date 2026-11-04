import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Vite corre dentro del contenedor Docker: escuchar en todas las interfaces
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // El navegador (en el host) debe pedir los assets a localhost:5173
        origin: 'http://localhost:5173',
        cors: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            // En Docker sobre Windows los eventos de archivos no llegan; usar polling
            usePolling: true,
            interval: 300,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
