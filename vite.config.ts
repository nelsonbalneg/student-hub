import { fileURLToPath, URL } from 'node:url';
import fs from 'node:fs';

import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: 'studenthub.test',
        port: 5173,
        strictPort: true,
        origin: 'https://studenthub.test:5173',
        cors: {
            origin: 'https://studenthub.test',
            credentials: true,
        },
        https: {
            cert: fs.readFileSync('C:/laragon/etc/ssl/laragon.crt'),
            key: fs.readFileSync('C:/laragon/etc/ssl/laragon.key'),
        },
        hmr: {
            host: 'studenthub.test',
            protocol: 'wss',
            clientPort: 5173,
        },
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
