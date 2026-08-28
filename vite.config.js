import {defineConfig} from 'vite';

const isWatch = process.argv.includes('--watch') || process.argv.includes('-w');

// Builds the public-facing load-more/filter script into
// dist/js/load_more_and_filter.js. This is what WRALM_Load_More::enqueue_scripts()
// actually loads on the front end (see inc/class-load-more.php).
//
// jQuery is a global provided by WordPress (enqueued as a script
// dependency before this file loads), so it's referenced directly as
// `jQuery(...)` in the source rather than imported/bundled.
//
// The admin console (frontend/) is a separate Vite+React project with
// its own config — this one only ever builds this single plain script.
export default defineConfig({
    build: {
        outDir: 'dist/js',
        emptyOutDir: false,
        target: 'es2015',
        sourcemap: false,
        minify: isWatch ? 'esbuild' : 'terser',
        terserOptions: isWatch
            ? undefined
            : {
                compress: {
                    drop_debugger: true,
                    passes: 2,
                },
            },
        lib: {
            entry: './src/js/load_more_and_filter.js',
            name: 'WRALMLoadMore',
            formats: ['iife'],
            fileName: () => 'load_more_and_filter.js',
        },
        rollupOptions: {
            output: {
                inlineDynamicImports: true,
            },
        },
    },
});
