import { Config } from 'tailwindcss';
import plugin from 'tailwindcss/plugin';
// The shared package's "exports" map points this subpath at a plain .js
// file with no accompanying .d.ts and no "types"/"typesVersions" entry, so
// TS's `bundler` moduleResolution can't find a type for it even with
// `allowJs: true` (allowJs only covers .js files inside this project's own
// rootDir, not ones resolved through another package's export map) — this
// is a real, minor gap in the shared package, not a bug in this file.
// @ts-expect-error -- no types published for '@web-revizor/ui-kit/tailwind-preset'
import sharedPreset from '@web-revizor/ui-kit/tailwind-preset';

const config: Config = {
  presets: [sharedPreset],
  important: '.web-revizor-container',
  content: ['./*.php', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      borderRadius: {
        2: '2px',
      },
      spacing: {
        'margin-desktop': '48px',
        'margin-mobile': '16px',
        'container-max': '1264px',
      },
      fontFamily: {
        'headline-md': ['var(--font-montserrat)'],
        button: ['var(--font-montserrat)'],
        display: ['var(--font-montserrat)'],
        'body-md': ['var(--font-inter)'],
        'body-lg': ['var(--font-inter)'],
        'headline-lg': ['var(--font-montserrat)'],
        'headline-lg-mobile': ['var(--font-montserrat)'],
        'label-bold': ['var(--font-inter)'],
      },
      fontSize: {
        'headline-md': ['24px', { lineHeight: '1.3', fontWeight: '600' }],
        button: ['16px', { lineHeight: '1.0', fontWeight: '600' }],
        display: [
          '64px',
          { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '700' },
        ],
        'body-md': ['16px', { lineHeight: '1.6', fontWeight: '400' }],
        'body-lg': ['18px', { lineHeight: '1.6', fontWeight: '400' }],
        'headline-lg': ['40px', { lineHeight: '1.2', fontWeight: '700' }],
        'headline-lg-mobile': [
          '36px',
          { lineHeight: '1.2', fontWeight: '700' },
        ],
        'label-bold': [
          '14px',
          { lineHeight: '1.2', letterSpacing: '0.05em', fontWeight: '600' },
        ],
      },
      screens: {
        375: '375px',
        425: '425px',
        475: '475px',
        550: '550px',
        650: '650px',
        767: '767px',
        991: '991px',
        1024: '1024px',
        1200: '1200px',
        1300: '1300px',
      },
      transitionTimingFunction: {
        bounce: 'cubic-bezier(0.68, -0.3, 0.32, 1.2)',
        'glass-card': 'cubic-bezier(0.4, 0, 0.2, 1)',
        global: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
      backgroundImage: {
        mesh: 'radial-gradient(circle at 50% 50%, #3c002b 0%, #131316 100%)',
      },
      boxShadow: {
        smallLight: '0px 0px 8px rgba(100, 100, 100, 0.25)',
        smallDark: '0px 0px 8px rgba(0, 0, 0, 0.25)',
        'glass-card': '0 0 30px rgba(189, 0, 120, 0.15)',
        'magenta-glow': '0 0 20px rgba(189, 0, 120, 0.3)',
        select: '0 0 5px rgba(189, 0, 120, 0.3)',
        'magenta-glow-hover': '0 0 35px rgba(189, 0, 120, 0.5)',
        'chat-shadow': '0px 0px 10px 1px rgba(0, 0, 0, 0.3)',
      },

      animation: {
        'model-message':
          'model-message 300ms cubic-bezier(0.22, 1, 0.36, 1) forwards',
        'user-message':
          'user-message 300ms cubic-bezier(0.22, 1, 0.36, 1) forwards',
        'shadow-pulse': 'shadow-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'add-message': 'add-message 0.5s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        'remove-message':
          'remove-message 0.5s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        infinite: 'infinite 2s linear infinite',
        'infinite-width': 'infinite-width 2s linear infinite',
        infiniteScroll: 'infiniteScroll 5s linear infinite',
        'opacity-in': 'opacity-in 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        'opacity-out': 'opacity-out 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        pulse: 'pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'to-top': 'to-top 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        'to-center': 'to-center 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        'to-bottom': 'to-bottom 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        'to-right': 'to-right 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        'to-left': 'to-left 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        openModal: 'openModal 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        closeModal: 'closeModal 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards',
        loadingBar: 'loadingBar 1.2s ease-out forwards',
        'button-icon': 'button-icon 4s ease-out forwards',
        'button-icon-rocket': 'button-icon-rocket 4s ease-out forwards',
      },
      keyframes: {
        'model-message': {
          '0%': {
            transform: 'translate3d(-12px, 8px, 0) scale(0.9)',
            opacity: '0',
          },
          '70%': {
            transform: 'translate3d(0, 0, 0) scale(1.03)',
            opacity: '1',
          },
          '100%': {
            transform: 'translate3d(0, 0, 0) scale(1)',
            opacity: '1',
          },
        },
        'user-message': {
          '0%': {
            transform: 'translate3d(12px, 8px, 0) scale(0.9)',
            opacity: '0',
          },
          '70%': {
            transform: 'translate3d(0, 0, 0) scale(1.03)',
            opacity: '1',
          },
          '100%': {
            transform: 'translate3d(0, 0, 0) scale(1)',
            opacity: '1',
          },
        },
        'shadow-pulse': {
          '0%': { boxShadow: '0px 0px 10px 1px rgba(189, 0, 120, 0.1)' },
          '50%': { boxShadow: '0px 0px 10px 1px rgba(189, 0, 120, 0.9)' },
          '100%': { boxShadow: '0px 0px 10px 1px rgba(189, 0, 120, 0.1)' },
        },
        'add-message': {
          '0%': { transform: 'translateX(-20px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        'remove-message': {
          '0%': { transform: 'translateX(0)', opacity: '1' },
          '100%': { transform: 'translateX(20px)', opacity: '0' },
        },
        loadingBar: {
          '0%': { transform: 'scaleX(0)' },
          '100%': { transform: 'scaleX(1)' },
        },
        infinite: {
          '0%': { transform: 'rotate(0)' },
          '100%': { transform: 'rotate(360deg)' },
        },
        'infinite-width': {
          '0%': { width: '0' },
          '100%': { width: '100%' },
        },
        infiniteScroll: {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(-50%)' },
        },
        'opacity-in': {
          '0%': { opacity: '0' },
          '100%': { opacity: '100%' },
        },
        'opacity-out': {
          '0%': { opacity: '100%' },
          '100%': { opacity: '0' },
        },
        'to-top': {
          '0%': { transform: 'translateY(100%)', opacity: '0' },

          '100%': { transform: 'translateX(0)', opacity: '100%' },
        },
        'to-center': {
          '0%': { transform: 'translateY(100%)', opacity: '0' },
          '100%': { transform: 'translateY(-50%)', opacity: '100%' },
        },
        'to-bottom': {
          '0%': { transform: 'translateX(0)', opacity: '100%' },
          '100%': { transform: 'translateY(100%)', opacity: '0' },
        },
        'to-right': {
          '0%': { transform: 'translateX(100%)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '100%' },
        },
        'to-left': {
          '0%': { transform: 'translateX(0)', opacity: '100' },
          '100%': { transform: 'translateX(100%)', opacity: '0' },
        },
        pulse: {
          '0%': { opacity: '0' },
          '50%': { opacity: '100%' },
          '100%': { opacity: '0' },
        },
        openModal: {
          '0%': { transform: 'translateY(-100%)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        closeModal: {
          '0%': { transform: 'translateY(0)', opacity: '1' },
          '100%': { transform: 'translateY(100%)', opacity: '0' },
        },
        'button-icon': {
          '0%': { transform: 'rotate(0)', opacity: '1' },
          '10%': { transform: 'rotate(0) scale(1.1)', opacity: '1' },
          '20%': { transform: 'rotate(360deg) scale(1.1)', opacity: '1' },
          '40%': {
            transform: 'rotate(360deg) scale(1.1) translateX(100%)',
            opacity: '0',
          },
          '50%': {
            transform: 'rotate(360deg) scale(1.1) translateX(1000%)',
            opacity: '0',
          },
          '51%': {
            transform: 'rotate(0) scale(1.1) translateX(-1000%)',
            opacity: '0',
          },
          '60%': {
            transform: 'rotate(0) scale(1.1) translateX(-100%)',
            opacity: '0',
          },
          '80%': { transform: 'rotate(0) scale(1.1) ', opacity: '1' },
          '90%': { transform: 'rotate(0) scale(1.1) ', opacity: '1' },
          '100%': { transform: 'rotate(0)', opacity: '1' },
        },
        'button-icon-rocket': {
          '0%': { transform: 'translate(0, 0) scale(1)', opacity: '1' },
          '10%': { transform: 'translate(0, 0) scale(1.3)', opacity: '1' },
          '35%': {
            transform: 'translate(200%, -200%) scale(1.3)',
            opacity: '0',
          },
          '50%': {
            transform: 'translate(1000%, -1000%) scale(1.3)',
            opacity: '0',
          },
          '51%': {
            transform: 'translate(-1000%, 1000%) scale(1.3)',
            opacity: '0',
          },
          '65%': {
            transform: 'translate(-200%, 200%) scale(1.3)',
            opacity: '0',
          },
          '80%': { transform: 'translate(0, 0) scale(1.3)', opacity: '1' },
          '90%': { transform: 'translate(0, 0) scale(1.3)', opacity: '1' },
          '100%': { transform: 'translate(0, 0) scale(1)', opacity: '1' },
        },
      },
    },
  },
  corePlugins: {
    container: false,
    preflight: false,
  },
  darkMode: 'selector',
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/container-queries'),
    plugin(({ matchUtilities }) => {
      matchUtilities(
        {
          clamp: (value) => {
            const [min, fluid, max] = value.split('-');

            if (!min || !fluid || !max) return null;

            return {
              fontSize: `clamp(${min}, ${fluid}, ${max})`,
            };
          },
        },
        {
          values: {},
        }
      );
    }),
    plugin(function ({ matchUtilities, theme }) {
      const durations = theme('transitionDuration');
      matchUtilities(
        {
          'animation-duration': (value) => ({
            'animation-duration': value,
          }),
        },
        { values: durations, type: 'any' }
      );
    }),
    plugin(function ({ matchUtilities }) {
      matchUtilities(
        {
          'animation-play': (value) => ({
            'animation-play-state': value,
          }),
        },
        { values: { pause: 'paused', run: 'running' }, type: 'any' }
      );
    }),
  ],
};
export default config;
