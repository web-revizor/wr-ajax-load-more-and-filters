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
            // `react-dom/client` (used by src/index.tsx's createRoot) is a
            // different module specifier from the bare `react-dom` below —
            // Rollup doesn't treat it as covered by that external entry, so
            // it was bundling react-dom/client's own full reconciler +
            // scheduler into app.js. That gives the page two separate
            // ReactDOM renderer instances at runtime (this bundle's own
            // createRoot vs. the WP-provided global used by createPortal),
            // corrupting React's shared internal dispatcher state as soon
            // as anything portals (Tooltip) into a tree rooted by the
            // other instance.
            external: ['react', 'react-dom', 'react-dom/client'],
            output: {
                globals: {
                    react: 'React',
                    'react-dom': 'ReactDOM',
                    'react-dom/client': 'ReactDOM',
                },
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
