import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        emptyOutDir: true,
        outDir: 'dist',
        manifest: true,
        rollupOptions: {
            input: {
                'spreadsheet-editor': 'resources/js/spreadsheet-editor.js',
                'spreadsheet-editor-style': 'resources/css/spreadsheet-editor.css',
            },
            output: {
                assetFileNames: '[name].[ext]',
                entryFileNames: '[name].js',
            },
        },
    },
});
