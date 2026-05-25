import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        outDir: '../../public/build-ai',
        emptyOutDir: true,
        manifest: true,
    },
    plugins: [
        laravel({
            publicDirectory: '../../public',
            buildDirectory: 'build-ai',
            input: [
                __dirname + '/resources/public/assets/sass/app.scss',
                __dirname + '/resources/public/assets/js/app.js'
            ],
            refresh: true,
        }),
    ],
});

//export const paths = [
//    'Modules/$STUDLY_NAME$/resources/public/assets/sass/app.scss',
//    'Modules/$STUDLY_NAME$/resources/public/assets/js/app.js',
//];