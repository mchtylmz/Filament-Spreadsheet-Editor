import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        emptyOutDir: true,
        lib: {
            entry: [
                'resources/js/index.js',
                'resources/css/index.css',
            ],
            name: 'FilamentSpreadsheetEditor',
        },
        outDir: 'dist',
        rollupOptions: {
            output: {
                assetFileNames: 'filament-spreadsheet-editor.[ext]',
                entryFileNames: 'filament-spreadsheet-editor.js',
            },
        },
    },
});
