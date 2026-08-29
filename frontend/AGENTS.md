# Frontend DOX (frontend/)

## Purpose
Vite + React 18 + TypeScript console that renders the shortcode-builder UI
on the "WR Ajax Load More" admin page. Builds to a single IIFE
(`../dist/app.js`) plus `../dist/style.css`, which is what the plugin's
PHP (`inc/class-admin.php`) actually enqueues.

## Ownership
Web.Revizor.

## Local Contracts
- No backend/API calls exist for this console — it only reads
  `window.wralmSettings` (post types + taxonomies, injected by
  `WRALM_Admin::render_page()`) and computes shortcode strings entirely
  client-side. Because of that there is intentionally **no `client/`
  directory** — add one only if this console starts calling `admin-ajax.php`
  or a REST route.
- State for the shortcode form lives in `src/hooks/useShortcodeBuilder.ts`;
  components stay presentational and receive state + setters as props.
  That hook also owns the two shortcode-string builders (helper `attr()`
  skips empty values).
- Entry is `src/index.tsx` (Vite `lib.entry: './src/index'`). Must build as
  `format: 'iife'` exposing a single global (`WebRevizorAiAgent`, see
  `vite.config.ts` `lib.name`) — WordPress loads it as a plain `<script>`
  tag, not an ES module. `react` / `react-dom` are externalized and must
  be enqueued as separate script dependencies (globals `React` /
  `ReactDOM`).
- Styling is Tailwind: `src/styles.scss` holds the `@tailwind` directives
  plus custom `@layer` utilities, configured by `tailwind.config.ts` and
  `postcss.config.js` (SCSS compiled by `sass`). `index.tsx` imports
  `./styles.scss`.
- Path alias `@` → the `frontend/` root (`tsconfig.json` `@/*` +
  `vite.config.ts` `resolve.alias`), so imports read `@/src/...`.

## Work Guidance
- New form fields: extend the relevant type in `src/types/index.ts`, the
  matching default in `src/hooks/useShortcodeBuilder.ts` (and its
  `attr(...)` assembly), and the tab component in `src/components/Tabs/`.
  Then mirror the attribute in the matching `$default` of `WRALM_Shortcode`
  (`render_posts` / `render_filters`) and wire it through to the JS /
  view.
- Shared inputs (`Input`, `Select`, `Toggle`, `Button`, `SlideDown`,
  `Loader`, `Icon`, …) live in `src/components/sharedComponents/` — reuse
  them instead of hand-rolling new `<input>` markup per tab.
- New icon: drop `src/icons/<name>.svg`, rebuild — `vite-svg-sprite-plugin.js`
  regenerates `../template-parts/sprite.php` and
  `src/components/sharedComponents/Icon/sprite-info.ts`. Reference it as
  `<Icon name="common/<name>" />` (all icons sit under the `common`
  category).

## Verification
- `yarn install && yarn build` from `frontend/` regenerates
  `../dist/app.js`, `../dist/style.css`, the SVG sprite and `sprite-info.ts`.
  All of those are committed.
- `yarn watch` runs `vite build --watch` for iterative development.
  (`yarn dev` starts a plain Vite dev server — not usable inside WordPress.)
- `yarn lint` (`eslint .`) / `yarn lint:fix`. Flat config in
  `eslint.config.js` (ESLint 9): `@eslint/js` + `typescript-eslint`
  recommended, `react-hooks`, `react-refresh`, `prettier` last. Config
  files and `dist/` are ignored.
- To check it in WordPress: activate the plugin, open
  **WR Ajax Load More** in the admin menu, confirm the console mounts
  into `#wralm-console` with no console errors, and that both shortcode
  fields update live and copy correctly.
