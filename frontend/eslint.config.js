import base from '@web-revizor/ui-kit/eslint-config';
import reactRefresh from 'eslint-plugin-react-refresh';

export default [
  {
    ignores: [
      'dist',
      'node_modules',
      '**/*.config.js',
      '**/*.config.ts',
      'vite-svg-sprite-plugin.js',
    ],
  },
  ...base,
  {
    files: ['**/*.{ts,tsx}'],
    plugins: { 'react-refresh': reactRefresh },
    rules: { 'react-refresh/only-export-components': 'warn' },
  },
];
