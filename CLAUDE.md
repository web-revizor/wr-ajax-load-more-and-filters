# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Що це

WordPress-плагін «Web Revizor: Ajax Load More & Filters». Дає шорткоди для AJAX-пагінації, фільтрації за таксономіями, пошуку (з підтримкою ACF) та сортування списків постів. Робочий каталог сам є каталогом плагіна всередині WordPress-інсталяції (OpenServer).

## Збірка

Є **дві незалежні збірки**, обидві на Vite, обидві запускаються командою `yarn build` у своєму каталозі. Менеджер пакетів — **yarn** (`yarn.lock`; CI теж на yarn через corepack).

| Що збирається | Каталог | Конфіг | Вхід | Вихід (у git) |
|---|---|---|---|---|
| Публічний скрипт load-more/фільтрів | корінь | `vite.config.js` | `src/js/load_more_and_filter.js` | `dist/js/load_more_and_filter.js` |
| Адмін-консоль (React+TS конструктор шорткодів) | `frontend/` | `frontend/vite.config.ts` | `frontend/src/index.tsx` | `dist/app.js`, `dist/style.css` |

Команди:
- Корінь: `yarn install && yarn build` (watch: `yarn dev` = `vite build --watch`).
- Консоль: `cd frontend && yarn install && yarn build` (watch: `yarn watch`; є також `yarn dev` — звичайний Vite dev-server, але для WordPress не використовується).

Тестів немає. Lint: `cd frontend && yarn lint` (flat config `frontend/eslint.config.js`, ESLint 9).

### Згенеровані файли, які треба комітити

Збірка `frontend/` через `vite-svg-sprite-plugin.js` збирає всі `frontend/src/icons/*.svg` у:
- `template-parts/sprite.php` — інлайновий SVG-спрайт, підключається у `WRALM_Admin::render_page()`;
- `frontend/src/components/sharedComponents/Icon/sprite-info.ts` — union-тип і масив id іконок.

`dist/*` і ці два файли **закомічені** — плагін вантажить саме їх. Після будь-якої зміни в `src/js/`, `frontend/src/` чи `frontend/src/icons/` перезбирай і комітай оновлені артефакти.

### Обидва React зовнішні

Адмін-збірка — `format: 'iife'`, `react`/`react-dom` винесені в `external`. `WRALM_Admin::enqueue_assets()` підключає `dist/app.js` із залежностями `['react', 'react-dom']` — WordPress має віддати їх окремими скриптами.

## Версіонування та реліз

Версія дублюється в трьох місцях і має збігатися:
- заголовок `Version:` у `wr-ajax-load-more-and-filters.php`
- константа `WRALM_VERSION` там же (використовується як cache-buster для enqueue)
- `package.json` і `frontend/package.json`

CI (`.github/workflows/build.yml`) на push у `main` читає `Version:` з PHP-файлу, збирає обидва бандли, пакує плагін у zip (без `src/`, `frontend/`, dev-тулінгу) і **створює GitHub Release з тегом `v<version>`**. Тобто bump заголовка `Version:` у коміті в `main` = автоматичний реліз.

Локально те саме робить `build.ps1` (`yarn package`, або `yarn package:fast` без `yarn install`): збирає обидва бандли, бере slug через `gh repo view` (fallback — git remote / назва теки), версію — із заголовка `Version:`, а список виключень **парсить прямо з `--exclude=` рядків `.github/workflows/build.yml`**, щоб не розходитися з CI. Результат — `plugin-build/<slug>/` + `<slug>_<version>.zip` у корені (обидва в `.gitignore`).

## Архітектура PHP

`wr-ajax-load-more-and-filters.php` підключає `inc/class-*.php` і в конструкторі `Web_Revizor_Ajax_Load_More` створює по одному об'єкту кожного підсистемного класу. **Кожен клас реєструє власні WordPress-хуки у власному конструкторі** — центрального реєстру хуків немає.

- **`WRALM_Shortcode`** — `[all_posts_ajax]` (список + пагінація) і `[all_posts_ajax_filters]` (панель фільтрів/пошуку/сортування).
  - `[all_posts_ajax]` рендерить `.ajax_row_holder > .ajax_row` і серіалізує всю конфігурацію шорткоду в `data-*`-атрибути на `.ajax_row` — саме звідти JS бере параметри, окремого localize для неї немає.
  - `[all_posts_ajax_filters]` кладе конфіг у глобальну `$load_more_variables`, яку читають в'юхи `inc/views/filter.php` та `inc/views/order.php`.
  - Зв'язок двох шорткодів — атрибут `filter_id` (за замовчуванням `<post_type>_filter`), який консоль підставляє в обидва.
- **`WRALM_Load_More`** — `wp_enqueue_scripts` (підключає `dist/js/...`, віддає `loadmore_params` через `wp_localize_script`) + обробник `wp_ajax_loadmore` / `wp_ajax_nopriv_loadmore`. Обробник будує `WP_Query` з `$_POST`, рендерить картки й повертає `wp_send_json(['html', 'pagination', 'max_page', 'base_url'])`.
- **`WRALM_Pagination::links()`** — статичний генератор розмітки пагінації (`list` / `both` / `none` / default-кнопка). Спільний для першого рендера в шорткоді і для AJAX-відповіді.
- **`WRALM_Admin`** — пункт меню «WR Ajax Load More», монтує React у `#wralm-console`, віддає йому `window.wralmSettings` (`postTypes`, `taxonomies` — тільки публічні не-вбудовані + примусово `post`). Хук активації `create_card_template` створює в темі каталог `all_posts_ajax/` і порожній `post-card.php`.
- **`WRALM_Hide_Meta_Box`** — чекбокс «Hide from list» на всіх публічних типах записів; пише мету `all_posts_ajax_hide`. Обидва `WP_Query` (шорткод і AJAX) виключають такі записи через `meta_query`.
- **`WRALM_Search_ACF`** — розширює `posts_search`, щоб пошук бив ще й по значеннях ACF-полів, іменах термінів і коментарях. Список ACF-полів кешується в опції `wralm_searchable_acf_fields`, перебудовується на `acf/save_post` тільки коли зберігають `acf-field-group`.

### Контракт із темою

Картки постів рендеряться через `get_template_part('all_posts_ajax/' . $post_type . '-card')`. Тема **мусить** мати `all_posts_ajax/{post_type}-card.php` (для звичайних постів — `post-card.php`). Плагін цих шаблонів не постачає, лише створює порожній `post-card.php` на активації.

## Архітектура фронтенду

### Публічний скрипт (`src/js/load_more_and_filter.js`)

Один IIFE на jQuery (`jQuery` — глобал від WordPress, не бандлиться). Керується даними з DOM: читає `data-*` з `.ajax_row`, класи `.js-category-filter` / `.js-category-filter-select` / `#all-post-search` / `#js-post-order` / `#all_posts_filter`. Синхронізує стан фільтрів з URL (`history.pushState`, параметри `<taxonomy>=slug` і `filter_search`), відновлює стан з URL на завантаженні. Після кожного запиту тригерить події `AjaxPaginationDone` / `AjaxFilterDone` на `document` — публічний API для тем.

### Адмін-консоль (`frontend/`)

Vite + React 18 + TypeScript. Призначення — **тільки клієнтський конструктор рядка шорткоду**, жодних запитів на бекенд (тому в `frontend/` немає і не має з'являтися каталогу `client/`, поки консоль не почне ходити в `admin-ajax.php`). Читає лише `window.wralmSettings`.

- Стан форми — цілком у `frontend/src/hooks/useShortcodeBuilder.ts`; компоненти презентаційні, отримують стан і сетери пропсами. Там же логіка складання обох рядків шорткоду (helper `attr()` пропускає порожні значення).
- Вкладки в `frontend/src/components/Tabs/` (`main` / `classes` / `filters` / `search` / `order`), спільні інпути — в `sharedComponents/`.
- Аліас `@` → корінь `frontend/`, тому імпорти виглядають як `@/src/...`.
- Стилі: `frontend/src/styles.scss` (директиви `@tailwind` + кастомні `@layer`-утиліти) + Tailwind (`tailwind.config.ts`, `postcss.config.js`, sass). `frontend/src/styles.css` — порожній невикористаний залишок.
- Глобал IIFE-збірки — `WebRevizorAiAgent` (`vite.config.ts` `lib.name`), вхід `frontend/src/index.tsx`.

### Іконки

Нова іконка = поклади `frontend/src/icons/<name>.svg`, перезбери `frontend/` → спрайт і `sprite-info.ts` оновляться автоматично. У коді: `<Icon name="common/<name>" />` (усі іконки в категорії `common`).

### Додавання поля в конструктор

1. Розшир відповідний тип у `frontend/src/types/index.ts`.
2. Додай дефолт у `frontend/src/hooks/useShortcodeBuilder.ts` і, за потреби, рядок у `attr(...)`-складання.
3. Додай контрол у відповідну вкладку в `frontend/src/components/Tabs/`.
4. Додай атрибут у `$default` відповідного методу `WRALM_Shortcode` (`render_posts` або `render_filters`) і проведи його до JS/в'юхи.

## Каталоги з локальною документацією

`frontend/AGENTS.md`, `frontend/src/components/sharedComponents/Button/AGENTS.md`, `.../SlideDown/AGENTS.md` — читай їх перед роботою у відповідних місцях.
