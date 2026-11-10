import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx',
                // Módulo Cliente: consulta el DNI sin recargar la página.
                'resources/js/clientes.js',
                // Mostrador del laboratorio: escaner de QR y marcado de tomas.
                'resources/js/laboratorio.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        react(),
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
            // En Docker sobre Windows los eventos de archivos no llegan; usar polling.
            // Cada vuelta del polling hace stat() de todo lo vigilado y, sobre este
            // montaje, un stat() cuesta ~4 ms: por eso se vigila lo menos posible
            // y con un intervalo holgado.
            usePolling: true,
            interval: 1000,
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/storage/**',
                '**/public/build/**',
                '**/.git/**',
            ],
        },
    },
});
