import { fileURLToPath, URL } from 'node:url';
import fs from 'node:fs';

import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

const laragonCertificate = 'C:/laragon/etc/ssl/laragon.crt';
const laragonKey = 'C:/laragon/etc/ssl/laragon.key';
const hasLaragonCertificate =
    fs.existsSync(laragonCertificate) && fs.existsSync(laragonKey);

const normalizeLaravelManifest = () => ({
    name: 'normalize-laravel-manifest',
    enforce: 'post' as const,
    closeBundle() {
        const manifestPath = fileURLToPath(
            new URL('./public/build/manifest.json', import.meta.url),
        );

        if (!fs.existsSync(manifestPath)) {
            return;
        }

        const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
        const normalizedManifest = Object.fromEntries(
            Object.entries(manifest).map(([key, value]) => {
                const normalizedKey = key.replace(
                    /^.*?(?=resources\/)/,
                    '',
                );
                const entry = value as {
                    src?: string;
                    imports?: string[];
                    dynamicImports?: string[];
                };

                if (entry.src) {
                    entry.src = entry.src.replace(/^.*?(?=resources\/)/, '');
                }

                for (const property of ['imports', 'dynamicImports'] as const) {
                    if (entry[property]) {
                        entry[property] = entry[property].map((item) =>
                            item.replace(/^.*?(?=resources\/)/, ''),
                        );
                    }
                }

                return [normalizedKey, entry];
            }),
        );

        fs.writeFileSync(
            manifestPath,
            `${JSON.stringify(normalizedManifest, null, 2)}\n`,
        );
    },
});

export default defineConfig({
    server: {
        host: 'studenthub.test',
        port: 5173,
        strictPort: true,
        origin: hasLaragonCertificate
            ? 'https://studenthub.test:5173'
            : 'http://studenthub.test:5173',
        cors: {
            origin: 'https://studenthub.test',
            credentials: true,
        },
        https: hasLaragonCertificate
            ? {
                  cert: fs.readFileSync(laragonCertificate),
                  key: fs.readFileSync(laragonKey),
              }
            : undefined,
        hmr: {
            host: 'studenthub.test',
            protocol: hasLaragonCertificate ? 'wss' : 'ws',
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
        normalizeLaravelManifest(),
    ],
});
