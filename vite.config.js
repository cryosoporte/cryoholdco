import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import inertia from "@inertiajs/vite";
import { fileURLToPath, URL } from "node:url";
import { existsSync } from "node:fs";

// Dentro de Docker (volumen montado desde Windows) Vite no puede renombrar
// node_modules/.vite/deps_temp_* -> deps (EACCES), la optimización de
// dependencias falla, el navegador recibe "504 Outdated Optimize Dep" y la
// página de Inertia queda en blanco. Guardamos la caché fuera del volumen.
const inDocker = existsSync("/.dockerenv");

export default defineConfig({
    cacheDir: inDocker ? "/tmp/vite-cache" : "node_modules/.vite",
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/inertia.tsx",
            ],
            refresh: true,
            fonts: [
                bunny("Instrument Sans", {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        react(),
        // Sin SSR: el plugin, al no encontrar resources/js/ssr.*, tomaba
        // resources/js/app.js (vacío) como entrada SSR y fallaba con
        // "SSR entry must export a render function" -> pantalla en blanco.
        inertia({ ssr: false }),
    ],
    server: {
        // Vite corre dentro del contenedor Docker: escuchar en todas las interfaces
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        // El navegador (en el host) debe pedir los assets a localhost:5173
        origin: "http://localhost:5173",
        cors: true,
        hmr: {
            host: "localhost",
        },
        watch: {
            // En Docker sobre Windows los eventos de archivos no llegan; usar polling.
            // Cada vuelta del polling hace stat() de todo lo vigilado y, sobre este
            // montaje, un stat() cuesta ~4 ms: por eso se vigila lo menos posible
            // y con un intervalo holgado.
            usePolling: true,
            interval: 300,
            binaryInterval: 1000,
            ignored: [
                "**/vendor/**",
                "**/node_modules/**",
                "**/storage/**",
                "**/bootstrap/cache/**",
                "**/public/**",
                "**/tests/**",
                "**/.git/**",
            ],
        },
    },
    // Pre-empaquetar al arrancar las dependencias que se cargan en cascada desde
    // las páginas de Inertia. Si Vite las descubre tarde (p. ej. tras un
    // `npx shadcn add` con el servidor corriendo) responde "504 Outdated
    // Optimize Dep" y la página queda en blanco.
    optimizeDeps: {
        // Escanear todas las páginas/componentes al arrancar (Inertia las carga
        // con import.meta.glob y el escáner no siempre las sigue).
        entries: ["resources/js/**/*.{ts,tsx}"],
        include: [
            "react",
            "react-dom",
            "react-dom/client",
            "react/jsx-dev-runtime",
            "@inertiajs/react",
            "@base-ui/react/avatar",
            "@base-ui/react/button",
            "@base-ui/react/checkbox",
            "@base-ui/react/dialog",
            "@base-ui/react/drawer",
            "@base-ui/react/input",
            "@base-ui/react/menu",
            "@base-ui/react/progress",
            "@base-ui/react/merge-props",
            "@base-ui/react/select",
            "@base-ui/react/separator",
            "@base-ui/react/tabs",
            "@base-ui/react/toggle",
            "@base-ui/react/toggle-group",
            "@base-ui/react/tooltip",
            "@base-ui/react/use-render",
            "class-variance-authority",
            "cn",
            "recharts",
            "sonner",
            "lucide-react",
            "next-themes",
        ],
    },
    resolve: {
        alias: {
            "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
        },
    },
});
