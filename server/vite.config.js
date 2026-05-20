import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
            '~bootstrap-icons': path.resolve(__dirname, 'node_modules/bootstrap-icons'),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
    },
});
