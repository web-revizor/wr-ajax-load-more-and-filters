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

- **`WRALM_Query_Config`** (`inc/class-query-config.php`) — єдине джерело істини для запиту списку `[all_posts_ajax]`. `from_atts()` будує його з атрибутів шорткоду, `from_request()` — з (unslashed) `$_POST` AJAX-запиту (з санітизацією й whitelist). З нього ж:
  - `wp_query_args()` — повний масив аргументів `WP_Query` (`post_status=publish` завжди, `meta_query` для «Hide from list», `tax_query`, `s` + query-var `wralm_search`, orderby-мапінг, `apply_filters('wralm_query_args', $args, $config)`);
  - `data_attrs()` / `render_data_attr_string()` — набір `data-*` для `.ajax_row`;
  - `pagination_args($base, $format, $add_args)` — аргументи для `WRALM_Pagination::links()`.
  - `resolve_sync_flags($filters, $pagination, $alias)` → `[bool, bool]` — розрулює пару `sync_filters_url` / `sync_pagination_url`: власний атрибут виграє, інакше deprecated-аліас `update_url` б'є по обох, інакше `true`. Викликається і з `from_atts()`, і з `from_request()`.
  - І `WRALM_Shortcode::render_posts`, і `WRALM_Load_More::handle_ajax` делегують сюди — запит визначено один раз.
  - **URL-sync** двома незалежними прапорцями (обидва default `true`, обидва в `data-*` на `.ajax_row` як `data-sync-filters-url` / `data-sync-pagination-url`): `sync_filters_url` — pushState при фільтрі/пошуку/сортуванні; `sync_pagination_url` — pushState при кліку нумерованої пагінації + справжні vs `#` href + pretty `/page/N/`. Кнопка «Show more» URL не чіпає ніколи (акумулює сторінки). `update_url` — deprecated-аліас.
  - `resolve_posts_per_page($post_type, $requested)` — для `post_type=product` повертає `WRALM_Woo::per_page()` (налаштування каталогу WooCommerce), ігноруючи значення шорткоду/запиту; решта типів — без змін. Викликається і з `from_atts`, і з `from_request` (після clamp).
- **`WRALM_Filter_Config`** (`inc/class-filter-config.php`) — замінює ручне складання глобалі `$load_more_variables` для в'юх панелі фільтрів; `from_atts()` будує DTO, `to_legacy_array()` віддає стару асоціативну форму, і глобаль **усе ще виставляється** з неї заради back-compat. Має статичний `visible_count($post_type)` — кешований (5-хв transient) підрахунок для лічильника «All (N)», і `term_visible_counts($post_type, $taxonomy)` → `[term_id => count]` (той самий 5-хв transient per (post_type, taxonomy)) для лічильників на кнопках фільтрів. Обидва виключають `all_posts_ajax_hide` + WooCommerce catalog/stock visibility; `term_visible_counts` рахує з дочірніми термами (як і `build_tax_query`). `inc/views/filter.php` (button mode) віддає ці числа в `wralm_render_filter_terms()`; терм з нульовим видимим лічильником у панелі не рендериться. Атрибут `show_filter_count="false"` на `[all_posts_ajax_filters]` (default `true`) прибирає `<span class="postCount">` з усіх кнопок і пропускає ці запити повністю (тоді zero-терми рендеряться як звичайно).
- **`WRALM_Woo`** (`inc/class-woo.php`) — статичний хелпер, **без хуків**: `is_product_query()`, `per_page()` (per-page з каталогу WooCommerce — `wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()`, fallback на опції `woocommerce_catalog_columns`/`_rows`, потім filter `loop_shop_per_page`), `visibility_tax_query()`, `orderby_args()`, `setup_loop()` / `reset_loop()`. Кожен метод — безпечний no-op, коли WooCommerce неактивний. Підключається першим, щоб `class_exists('WRALM_Woo')`-гварди в `WRALM_Query_Config` резолвилися.
- **`WRALM_Shortcode`** — `[all_posts_ajax]` (список + пагінація) і `[all_posts_ajax_filters]` (панель фільтрів/пошуку/сортування).
  - `[all_posts_ajax]` рендерить `.ajax_row_holder > .ajax_row`; конфіг тепер тече через `WRALM_Query_Config`, який серіалізує всі параметри в `data-*` на `.ajax_row` (окремого localize для неї немає). `data-filter-id` виставляється **завжди**.
  - `[all_posts_ajax_filters]` будує `WRALM_Filter_Config`, кладе `to_legacy_array()` у глобальну `$load_more_variables`, яку читають в'юхи `inc/views/filter.php` та `inc/views/order.php`. На `.ajax_filters_wrapper` — лише `data-filter-id` (JS читає URL-sync-прапорці з `.ajax_row`, не з панелі).
  - Зв'язок двох шорткодів — атрибут `filter_id` (за замовчуванням `<post_type>_filter`), який консоль підставляє в обидва. Кілька незалежних пар список+фільтри на одній сторінці = різний `filter_id` на кожну. Усі id тепер instance-unique (`all_posts_filter_<filter_id>`, `all-post-search-<filter_id>`, `js-post-order-<filter_id>`); у обгортки пагінації id прибрано — ціль `.pagination_holder`.
- **`WRALM_Load_More`** — `wp_enqueue_scripts` (підключає `dist/js/...`, віддає `loadmore_params` через `wp_localize_script`, зокрема `nonce`) + обробник `wp_ajax_loadmore` / `wp_ajax_nopriv_loadmore`. Обробник делегує в `WRALM_Query_Config::from_request`, рендерить картки й повертає `wp_send_json(['html', 'pagination', 'max_page', 'canonical_url'])`, де `canonical_url` — точний URL поточного стану (фільтри + сторінка), зібраний `WRALM_Pagination::page_url()` з тих самих `base`/`format`/`add_args`, що й пагінаційні посилання; `add_args` = `WRALM_Query_Config::filter_query_args()` (порожньо коли `sync_filters_url=false`). JS пушить його в адресний рядок. Nonce перевіряється м'яко; `add_filter('wralm_require_nonce', '__return_true')` робить його обов'язковим (403 при невалідному).
- **`WRALM_Pagination::links()`** — статичний генератор розмітки пагінації (`list` / `both` / `none` / default-кнопка). Коли передано явні `base` / `format` / `total` / `current` — працює лише з `$args`, не чіпаючи WP-глобалі (потрібно для AJAX). Honorить `sync_pagination_url` (при `false` усі `href` = `#`).
  - `WRALM_Pagination::resolve_base($sync_pagination_url, $archive_context, $url)` → `[$base, $format]` — зрізає наявний `/page/N/` чи `?paged=N` з URL і вибирає pretty-vs-query формат пагінації за контекстом (pretty `/page/N/` лише на archive/home + `sync_pagination_url=true` + pretty permalinks, інакше `?paged=%#%`).
  - `WRALM_Pagination::page_url($base, $format, $page, $add_args, $fragment)` → рядок URL сторінки N (підстановка `%_%`/`%#%`, `add_query_arg($add_args)`; сторінка 1 без сегмента). Коми в multi-term значеннях лишаються `%2C` (як `add_query_arg` їх кодує); `URLSearchParams` у JS декодує назад при читанні стану. Єдине джерело — `links()` і `canonical_url` у `WRALM_Load_More` кличуть його, тому формат не розходиться.
- **Namespaced URL-параметри фільтрів.** Таксономійні фільтри в URL — `filter_<taxonomy>=slug,slug` (не голе `<taxonomy>=`), плюс `filter_search`. Голе `?product_cat=a,b` збігається з query-var WooCommerce → WP робить 301 на `%2C`-версію й міняє контекст; namespaced `filter_product_cat` не query-var, тож ні 301, ні 404, ні стрибків формату пагінації. Тому `from_atts` більше не має спец-логіки «GET-параметр ≠ архів» — `get_queried_object()`-терм завжди справжній архів. `filter.php` не робить `exclude` queried-терму — усі кнопки термів рендеряться завжди. Серіалізація в `WRALM_Query_Config::filter_query_args()`; читання в JS `readUrlState()`.
- **`WRALM_Admin`** — пункт меню «WR Ajax Load More», монтує React у `#wralm-console`, віддає йому `window.wralmSettings` (`postTypes`, `taxonomies` — тільки публічні не-вбудовані + примусово `post`). Хук активації `create_card_template` створює в темі каталог `all_posts_ajax/` і порожній `post-card.php`.
- **`WRALM_Hide_Meta_Box`** — чекбокс «Hide from list» на всіх публічних типах записів; пише мету `all_posts_ajax_hide`. Обидва `WP_Query` (шорткод і AJAX) виключають такі записи через `meta_query`.
- **`WRALM_Search_ACF`** — розширює `posts_search`, щоб пошук бив ще й по значеннях ACF-полів, іменах термінів і коментарях. Тепер застосовується **лише** до WRALM-запитів (перевіряє query-var `wralm_search`, яку виставляє `WRALM_Query_Config::wp_query_args`); глобальну поведінку повертає `add_filter('wralm_extend_all_search', '__return_true')`. `meta_key` завжди обмежений `IN (...)` — повного скану `wp_postmeta` більше немає. Список ACF-полів кешується в опції `wralm_searchable_acf_fields`, будується на `admin_init` (bootstrap, якщо опції немає) і перебудовується на `acf/save_post` `acf-field-group`.

### Фільтри / хуки

- `wralm_query_args` — filter, `($args, WRALM_Query_Config $config)`; фінальна зміна аргументів `WP_Query`.
- `wralm_require_nonce` — filter, default `false`; `true` → жорстка перевірка nonce на AJAX-ендпоінті.
- `wralm_extend_all_search` — filter, default `false`; `true` → ACF-розширення пошуку знову глобальне.

### Контракт із темою

Картки постів рендеряться через `get_template_part('all_posts_ajax/' . $post_type . '-card')`. Тема **мусить** мати `all_posts_ajax/{post_type}-card.php` (для звичайних постів — `post-card.php`). Плагін цих шаблонів не постачає, лише створює порожній `post-card.php` на активації.

## Архітектура фронтенду

### Публічний скрипт (`src/js/load_more_and_filter.js`)

Один IIFE на jQuery (`jQuery` — глобал від WordPress, не бандлиться). Один контролер (`Instance`) на `.ajax_row_holder`, усе scoped по `[data-filter-id]`. Керується даними з DOM: читає `data-*` з `.ajax_row`, класи `.js-category-filter` / `.js-category-filter-select` / `.all-post-search` / `.js-post-order` / `.js-post-orderby` / `.all_posts_form` у межах панелі `.ajax_filters_wrapper[data-filter-id="…"]` (id тепер instance-suffixed, не селектори). Fallback: якщо жодна панель не збіглася за `filter-id`, а на сторінці рівно одна `.ajax_filters_wrapper` — береться вона (pre-1.5.0 пари з розбіжним implied `filter_id`). **Двобічний URL-sync.** `readUrlState()` дістає `{category, search, page}` з адресного рядка (`filter_<taxonomy>=slug,slug`, `filter_search`, `/page/N/` або `?paged=N`/`?page=N`); `applyUrlState()` силою виставляє DOM панелі під нього. Кожна дія (`filter`/`paginate`) шле запит і на `.done` пушить `res.canonical_url` (сервер — єдине джерело формату), гейт per-action: `filter`→`syncFiltersUrl`, нумерована пагінація→`syncPaginationUrl`. «Show more» URL не чіпає. `restoreFromUrl()` на завантаженні — і `popstate` handler на Back/Forward — перечитують URL, виставляють DOM, шлють запит **без** push. `restoreFromUrl` не робить запит лише коли URL = дефолт (нема фільтрів/пошуку і `page === initPage`). `filter()` завжди скидає на сторінку 1. Після кожного запиту тригерить події `AjaxPaginationDone` / `AjaxFilterDone` на `document` і на `.ajax_row_holder` — публічний API для тем.

**Кнопка «All» (`allCategories`).** Клік скидає лише категорійні фільтри (кнопки + селекти), не чіпає пошук/сортування, і сабмітить форму. Стає активною автоматично коли знято останній фільтр (у т.ч. в `multiply-false` single-select режимі — клік по активній кнопці знімає її й вертає «All»). Окрема кнопка `.js-clear-filter` чистить усе (фільтри + пошук + селекти).

### Адмін-консоль (`frontend/`)

Vite + React 18 + TypeScript. Призначення — **тільки клієнтський конструктор рядка шорткоду**, жодних запитів на бекенд (тому в `frontend/` немає і не має з'являтися каталогу `client/`, поки консоль не почне ходити в `admin-ajax.php`). Читає лише `window.wralmSettings`.

- Стан форми — цілком у `frontend/src/hooks/useShortcodeBuilder.ts`; компоненти презентаційні, отримують стан і сетери пропсами. Там же логіка складання обох рядків шорткоду (helper `attr()` пропускає порожні значення).
- Вкладки в `frontend/src/components/Tabs/` (`main` / `classes` / `filters` / `search` / `order`), спільні інпути — в `sharedComponents/`.
- Поля URL-sync (вкладка Main): `MainSettings.syncFiltersUrl` / `MainSettings.syncPaginationUrl` (обидва bool, default `true`) → `sync_filters_url="false"` / `sync_pagination_url="false"` на `[all_posts_ajax]` (емітяться лише коли вимкнено). 1.5.0-поле `updateUrl` / атрибут `update_url` прибрано з консолі (PHP лишає `update_url` як deprecated-аліас).
- Поля 1.5.0 (лишилися): `OrderSettings.orderByOptions` (string[]) / `OrderSettings.orderByLabels` (csv-string) → `order_by_options` / `order_by_labels` на `[all_posts_ajax_filters]`, вкладка Order. Для `post_type="product"` вкладка Main показує інформаційну підказку про WooCommerce (нового атрибута немає — PHP автодетектить).
- Аліас `@` → корінь `frontend/`, тому імпорти виглядають як `@/src/...`.
- Стилі: `frontend/src/styles.scss` (директиви `@tailwind` + кастомні `@layer`-утиліти) + Tailwind (`tailwind.config.ts`, `postcss.config.js`, sass). `frontend/src/styles.css` — порожній невикористаний залишок.
- Глобал IIFE-збірки — `WebRevizorAiAgent` (`vite.config.ts` `lib.name`), вхід `frontend/src/index.tsx`.

### Іконки

Нова іконка = поклади `frontend/src/icons/<name>.svg`, перезбери `frontend/` → спрайт і `sprite-info.ts` оновляться автоматично. У коді: `<Icon name="common/<name>" />` (усі іконки в категорії `common`).

### Додавання поля в конструктор

1. Розшир відповідний тип у `frontend/src/types/index.ts`.
2. Додай дефолт у `frontend/src/hooks/useShortcodeBuilder.ts` і, за потреби, рядок у `attr(...)`-складання.
3. Додай контрол у відповідну вкладку в `frontend/src/components/Tabs/`.
4. Додай атрибут у `shortcode_defaults()` відповідного DTO (`WRALM_Query_Config` для `[all_posts_ajax]`, `WRALM_Filter_Config` для `[all_posts_ajax_filters]`), спарси його у `from_atts()` (і `from_request()` + `data_attrs()` для `WRALM_Query_Config`, або `to_legacy_array()` для `WRALM_Filter_Config`) і проведи до JS/в'юхи.

## Каталоги з локальною документацією

`frontend/AGENTS.md`, `frontend/src/components/sharedComponents/Button/AGENTS.md`, `.../SlideDown/AGENTS.md` — читай їх перед роботою у відповідних місцях.
