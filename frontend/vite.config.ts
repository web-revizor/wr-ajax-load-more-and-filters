import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import * as path from 'node:path';
import {svgSpritePlugin} from './vite-svg-sprite-plugin.js';

const isWatch = process.argv.includes('--watch') || process.argv.includes('-w');
export default defineConfig({
    plugins: [
        react(),
        svgSpritePlugin({
            iconsDir: 'src/icons',
            outputDir: '../template-parts',
            outputName: 'sprite.php',
        }),
    ],
    define: {
        'process.env': {},
        'process.env.NODE_ENV': '"production"',
    },
    build: {
        outDir: '../dist',
        minify: isWatch ? 'esbuild' : 'terser',
        terserOptions: isWatch
            ? undefined
            : {
                compress: {
                    drop_console: false,
                    drop_debugger: true,
                    passes: 2,
                },
            },
        lib: {
            entry: './src/index',
            name: 'WebRevizorAiAgent',
            formats: ['iife'],
            fileName: () => 'app.js',
            cssFileName: 'style',
        },
        rollupOptions: {
            // react/react-dom are bundled in full, NOT externalized.
            // WordPress's own bundled React version is outside our control
            // (observed: 18.3.1, vs. this project's own react@19.2.8) —
            // externalizing to WP's global React/ReactDOM makes element
            // creation (this bundle's jsx-runtime, built against React 19's
            // element marker) and rendering (WP's global reconciler, React
            // 18) use two different, incompatible element-tag symbols,
            // which crashes immediately with React error #31 the moment
            // anything renders. Bundling our own consistent React 19 copy
            // end-to-end avoids this entirely, at the cost of a second
            // React runtime on the page alongside WP's own — acceptable
            // since this widget never portals into WP's own React tree,
            // only into plain DOM (document.body).
            output: {
                inlineDynamicImports: true,
            },
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './'),
        },
    },
});
