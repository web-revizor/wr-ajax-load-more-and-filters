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
            external: ['react', 'react-dom'],
            output: {
                globals: {
                    react: 'React',
                    'react-dom': 'ReactDOM',
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
