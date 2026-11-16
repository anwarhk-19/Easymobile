import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command }) => ({
    // Production builds use a relative base: lazily loaded chunks resolve next to the entry file,
    // so one build works at a domain root and in a sub-folder (e.g. https://devpulsetech.com/easymobile).
    base: command === 'build' ? './' : undefined,
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.jsx'], refresh: true }),
        react(),
        tailwindcss(),
    ],
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
}));
