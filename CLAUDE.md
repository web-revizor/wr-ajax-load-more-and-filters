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
  - `wp_query_args()` — повний масив аргументів `WP_Query` (`post_status=publish` завжди, `meta_query` для «Hide from list», `tax_query`, `s` + query-var `wralm_search`, sort-мапінг через `WRALM_Woo::orderby_args` для product або локальну fallback-мапу, `apply_filters('wralm_query_args', $args, $config)`);
  - **Сортування** — один селект. `orderby`/`order` парсяться зі значення `sort` (`"orderby:order"`, `split_sort_value()` — whitelist `ORDERBY_WHITELIST` + `asc`/`DESC`). `from_atts`: `?sort` з URL (коли `sync_filters_url`) → інакше `WRALM_Filter_Config::default_sort_for($filter_id)` (реєстр, який панель заповнює під час свого рендеру — вона вище списку в нормальному layout) → інакше `date:desc`. `from_request`: клієнт шле `sort` + `default_sort` (перший `<option>` з DOM). `filter_query_args()` кладе `sort` в URL **лише коли** активний ≠ `default_sort`. `data-sort` на `.wr-posts__list` = що сервер відрендерив (fallback для JS у edge-кейсі панель-нижче-списку).
  - `data_attrs()` / `render_data_attr_string()` — набір `data-*` для `.wr-posts__list`;
  - `pagination_args($base, $format, $add_args)` — аргументи для `WRALM_Pagination::links()`.
  - `resolve_sync_flags($filters, $pagination)` → `[bool, bool]` — кожен прапорець: default `true`, будь-яке значення крім рядка `"false"` = on. Викликається і з `from_atts()`, і з `from_request()`.
  - І `WRALM_Shortcode::render_posts`, і `WRALM_Load_More::build_list_response` делегують сюди — запит визначено один раз.
  - **URL-sync** двома незалежними прапорцями (обидва default `true`, обидва в `data-*` на `.wr-posts__list` як `data-sync-filters-url` / `data-sync-pagination-url`): `sync_filters_url` — pushState при фільтрі/пошуку/сортуванні; `sync_pagination_url` — pushState при кліку нумерованої пагінації + справжні vs `#` href + pretty `/page/N/`. Кнопка «Show more» URL не чіпає ніколи (акумулює сторінки).
  - `resolve_posts_per_page($post_type, $requested)` — для `post_type=product` повертає `WRALM_Woo::per_page()` (налаштування каталогу WooCommerce), ігноруючи значення шорткоду/запиту; решта типів — без змін. Викликається і з `from_atts`, і з `from_request` (після clamp).
- **`WRALM_Filter_Config`** (`inc/class-filter-config.php`) — DTO конфіга панелі фільтрів; `from_atts()` будує його, в'юхи `inc/views/*.php` читають об'єкт напряму. Атрибут `sort_options` (`"orderby:order:label|..."`) → `parse_sort_options()` → `[{orderby,order,value,label}]` (record split `|`, поле split по перших 2 `:`, label може мати `:`; порожній label → з ключа). `from_atts` реєструє перший запис у `self::$default_sort[$filter_id]`; `default_sort_for($filter_id)` віддає його `WRALM_Query_Config`. Має статичний `visible_count($post_type)` — кешований (5-хв transient) підрахунок для лічильника «All (N)», і `term_visible_counts($post_type, $taxonomy)` → `[term_id => count]` (той самий 5-хв transient per (post_type, taxonomy)) для лічильників на кнопках фільтрів. Обидва виключають `all_posts_ajax_hide='1'` + WooCommerce catalog/stock visibility. `term_visible_counts` тепер робить **один** згрупований SQL-запит прямих лічильників (`compute_term_visible_counts()`, `GROUP BY tt.term_id` + `NOT EXISTS` на meta + Woo-visibility), потім у PHP підсумовує вгору по ієрархії (parent += child), тож рахує з дочірніми термами як `build_tax_query` — раніше був один `WP_Query` на кожен терм (N+1). Обидва методи мають stampede-lock через `wp_cache_add(..._lock, LOCK_TTL=30s)`: поки один запит перебудовує кеш, інші віддають fallback (`wp_count_posts` publish для `visible_count`, порожній масив для `term_visible_counts`) — ефективно лише з persistent object cache. `inc/views/filter.php` (button mode) віддає ці числа в `wralm_render_filter_terms()` (приймає `WRALM_Filter_Config $config`); терм з нульовим видимим лічильником у панелі не рендериться. Дерево термів для рендера будується одним `get_terms()` через `wralm_term_tree()` (`[parent_id => WP_Term[]]`) — рекурсія рендера більше не кличе `get_terms(parent=X)` на кожен терм. Атрибут `show_filter_count="false"` (default `true`) прибирає `.wr-filters__item-count` з усіх кнопок і пропускає ці запити повністю (тоді zero-терми рендеряться як звичайно).
- **`WRALM_Woo`** (`inc/class-woo.php`) — статичний хелпер, **без хуків**: `is_product_query()`, `per_page()` (per-page з каталогу WooCommerce — `wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()`, fallback на опції `woocommerce_catalog_columns`/`_rows`, потім filter `loop_shop_per_page`), `visibility_tax_query()`, `orderby_args($orderby, $order)` (поважає переданий `$order` для всіх ключів, зокрема `popularity`/`rating` — раніше форсив `DESC`), `setup_loop()` / `reset_loop()`. Кожен метод — безпечний no-op, коли WooCommerce неактивний. Підключається першим, щоб `class_exists('WRALM_Woo')`-гварди в `WRALM_Query_Config` резолвилися.
- **`WRALM_Shortcode`** — `[all_posts_ajax]` (список + пагінація) і `[all_posts_ajax_filters]` (панель фільтрів/пошуку/сортування).
  - `[all_posts_ajax]` рендерить `.wr-posts > .wr-posts__list`; конфіг тече через `WRALM_Query_Config`, серіалізується в `data-*` на `.wr-posts__list`. `data-filter-id` + `data-init-page` на `.wr-posts`. `order.php` рендериться коли `parse_sort_options($config->sort_options)` не порожній (окремого `enable_order` немає).
  - `[all_posts_ajax_filters]` будує `WRALM_Filter_Config` і `require`-ить в'юхи `inc/views/filter.php` / `inc/views/order.php`, які читають об'єкт `$config` **напряму**. На `.wr-filters` — лише `data-filter-id` (JS читає URL-sync-прапорці з `.wr-posts__list`, не з панелі).
  - Зв'язок двох шорткодів — атрибут `filter_id` (за замовчуванням `<post_type>_filter`), який консоль підставляє в обидва. Кілька незалежних пар на одній сторінці = різний `filter_id`. **Жодних `id` на елементах** — `data-filter-id` на `.wr-posts` / `.wr-filters` — єдиний механізм парування.
- **`WRALM_Load_More`** — `wp_enqueue_scripts` (підключає `dist/js/...`, віддає `loadmore_params`: `resturl`, `current_page`, `max_page`, `nonce` = `wp_rest`). Ендпоінт списку — **лише REST GET** `wralm/v1/list` (`handle_rest` → `build_list_response` → `WP_REST_Response` з `Cache-Control: public, max-age=30`, щоб reverse-proxy/CDN кешували; `admin-ajax` `loadmore` більше немає). `build_list_response(array $params)` делегує в `WRALM_Query_Config::from_request`, рендерить картки й віддає `['html', 'pagination', 'max_page', 'canonical_url']` (`canonical_url` — точний URL стану фільтри+сторінка, `WRALM_Pagination::page_url()` з тих самих `base`/`format`/`add_args`, що й пагінація; `add_args` = `filter_query_args()`, порожньо коли `sync_filters_url=false`). **Без nonce-гейта** — анонімний nonce не секрет, а список опублікованих постів не CSRF-ціль; зловживання обмежує `rest_permission()` — per-IP rate limit (transient, default 60/хв, filter `wralm_rate_limit`, `429` при перевищенні). JS шле GET на `resturl` з `X-WP-Nonce` (тільки щоб залогінений глядач резолвився).
- **`WRALM_Pagination::links()`** — статичний генератор розмітки пагінації (`list` / `both` / `none` / default-кнопка). Коли передано явні `base` / `format` / `total` / `current` — працює лише з `$args`, не чіпаючи WP-глобалі (потрібно для AJAX). Honorить `sync_pagination_url` (при `false` усі `href` = `#`).
  - `WRALM_Pagination::resolve_base($sync_pagination_url, $archive_context, $url)` → `[$base, $format]` — зрізає наявний `/page/N/` чи `?paged=N` з URL і вибирає pretty-vs-query формат пагінації за контекстом (pretty `/page/N/` лише на archive/home + `sync_pagination_url=true` + pretty permalinks, інакше `?paged=%#%`).
  - `WRALM_Pagination::page_url($base, $format, $page, $add_args, $fragment)` → рядок URL сторінки N (підстановка `%_%`/`%#%`, `add_query_arg(urlencode_deep($add_args))`; сторінка 1 без сегмента). `urlencode_deep` обов'язковий — `add_query_arg` значення **не** кодує, тож кома в multi-term значенні лишилась би літеральною; тепер `filter_product_cat=courses%2Ccmm`, `URLSearchParams` у JS декодує назад. Єдине джерело — `links()` і `canonical_url` у `WRALM_Load_More` кличуть його, тому формат не розходиться.
- **Namespaced URL-параметри фільтрів.** Таксономійні фільтри в URL — `filter_<taxonomy>=slug,slug` (не голе `<taxonomy>=`), плюс `filter_search` і `sort` (`"orderby:order"`, лише коли ≠ `default_sort`). Голе `?product_cat=a,b` збігається з query-var WooCommerce → WP робить 301 на `%2C`-версію й міняє контекст; namespaced `filter_product_cat` не query-var, тож ні 301, ні 404, ні стрибків формату пагінації. `get_queried_object()`-терм завжди справжній архів. Серіалізація в `WRALM_Query_Config::filter_query_args()`; читання в JS `readUrlState()`. **`from_atts` теж читає ці GET-параметри** коли `sync_filters_url` — тож забукмарканий URL рендериться правильним на сервері. `render_posts` кладе відрендерений стан у `data-init-filters` на `.wr-posts`; JS `restoreFromUrl()` порівнює з адресним рядком (включно з `sort` vs `data-sort`) і **пропускає** початковий re-fetch при збігу.
- **`WRALM_Admin`** — пункт меню «WR Ajax Load More», монтує React у `#wralm-console`, віддає йому `window.wralmSettings` (`postTypes`, `taxonomies` — тільки публічні не-вбудовані + примусово `post`). Хук активації `create_card_template` створює в темі каталог `all_posts_ajax/` і порожній `post-card.php`.
- **`WRALM_Hide_Meta_Box`** — чекбокс «Hide from list» на всіх публічних типах записів. Мета `all_posts_ajax_hide` пишеться **лише коли приховано** (`update_post_meta('1')`); знято → `delete_post_meta`. Одноразовий `maybe_cleanup_legacy_meta()` (хук `init`, gate — автозавантажена опція `wralm_hide_meta_cleaned`) чистить legacy-рядки зі значенням ≠ `'1'` (старі версії писали `'0'` на кожному save). Тому `WRALM_Query_Config::hide_meta_query()`, `WRALM_Filter_Config::visible_count` і `term_visible_counts` — усі використовують `NOT EXISTS` на `meta_value='1'` замість пари `OR` + `!=`.
- **`WRALM_Search_Settings`** (`inc/class-search-settings.php`) — підменю «Search» під меню «WR Ajax Load More» (Settings API, опція `wralm_search_settings`, capability `manage_options`). Адмін явно обирає ACF-поля для пошуку (`<select multiple>`, значення — `name` полів, top-level only), тумблери «шукати в коментарях» / «в іменах термінів» (default обидва on) і ліміти `max_words` (default 6) / `min_word_length` (default 3). `::get()` віддає нормалізований масив з дефолтами; `::available_acf_fields()` збирає список полів через `acf_get_field_groups`/`acf_get_fields` по публічних типах. **Порожній список ACF-полів = пошук взагалі не чіпає `wp_postmeta`.**
- **`WRALM_Search_ACF`** — розширює `posts_search` тільки для WRALM-запитів (query-var `wralm_search`; глобально — `add_filter('wralm_extend_all_search', '__return_true')`). Title + content завжди; ACF-поля / коментарі / імена термінів — за налаштуваннями `WRALM_Search_Settings`. Слова коротші за `min_word_length` відкидаються, кількість слів обрізається до `max_words` (більше немає необмеженого числа AND-блоків EXISTS). ACF-`meta_key` завжди `IN (обрані поля)` — повного скану `wp_postmeta` немає. Старого авто-кешу полів (`wralm_searchable_acf_fields`, `acf/save_post`, `admin_init` bootstrap) більше немає.

### Фільтри / хуки

- `wralm_query_args` — filter, `($args, WRALM_Query_Config $config)`; фінальна зміна аргументів `WP_Query`.
- `wralm_rate_limit` — filter, default `60`; максимум запитів на IP за хвилину до REST-ендпоінта списку (`0` або менше → без ліміту).
- `wralm_max_posts_per_page` — filter, default `200`; clamp для `posts_per_page` з запиту (`from_request`).
- `wralm_extend_all_search` — filter, default `false`; `true` → ACF-розширення пошуку знову глобальне.

### Контракт із темою

Картки постів рендеряться через `get_template_part('all_posts_ajax/' . $post_type . '-card')`. Тема **мусить** мати `all_posts_ajax/{post_type}-card.php` (для звичайних постів — `post-card.php`). Плагін цих шаблонів не постачає, лише створює порожній `post-card.php` на активації.

### CSS-контракт

Плагін не постачає фронтенд-CSS, лише стабільну структуру класів (BEM, префікс `wr-`; стан-класи `is-active` / `is-current` / `is-disabled` / `is-hidden`). Джерела: `inc/views/filter.php`, `inc/views/order.php`, `inc/class-pagination.php`, `inc/class-shortcode.php`.

Список: `.wr-posts` › `.wr-posts__list` (+ `data-*`) + `.wr-posts__pagination` (`a.wr-posts__page` + `--prev`/`--next`/`--more`/`--dots`, `.is-current`; нумеровані також мають `page-numbers`) + `.wr-posts__empty`.

Панель: `.wr-filters` › `form.wr-filters__form` › `.wr-filters__search` (`__search-input`, `__search-submit`); `.wr-filters__list` › `button.wr-filters__item` (+ `--all`/`--parent`/`--child`, `data-multiply`) з `.wr-filters__item-label` + `.wr-filters__item-count`, `p.wr-filters__heading`, `.wr-filters__select` › `select.wr-filters__select-control`, `button.wr-filters__clear`; `.wr-filters__expand`; `.wr-filters__sort` › `select.wr-filters__sort-control`.

Атрибути `row_classes` / `filter_row_classes` / `filter_item_classes` / `filter_expand_class` **додають** класи до відповідного структурного елемента (не замінюють); їхні старі дефолти (`posts_row`, `filter_row`, `filter_item`, `filter_expand`) прибрані.

## Архітектура фронтенду

### Публічний скрипт (`src/js/load_more_and_filter.js`)

Один IIFE на jQuery (`jQuery` — глобал від WordPress, не бандлиться). Один контролер (`Instance`) на `.wr-posts`, усе scoped по `[data-filter-id]`. Керується даними з DOM: читає `data-*` з `.wr-posts__list`, класи `.wr-filters__item` / `.wr-filters__select-control` / `.wr-filters__search-input` / `.wr-filters__sort-control` / `.wr-filters__form` / `.wr-filters__clear` у межах `.wr-filters[data-filter-id="…"]`. Стан-класи `is-active` (кнопки фільтрів), `is-hidden` (overflow за `filter_item_limit`). Панель матчиться строго за `[data-filter-id]`. **Двобічний URL-sync.** `readUrlState()` дістає `{category, search, sort, page}` (`filter_<taxonomy>=slug,slug`, `filter_search`, `sort=orderby:order`, `/page/N/` або `?paged=N`/`?page=N`); `applyUrlState()` виставляє DOM панелі (включно з `.wr-filters__sort-control` → `state.sort || defaultSort()`). Кожна дія (`filter`/`paginate`) шле **GET на `params.resturl`** (`wralm/v1/list`, заголовок `X-WP-Nonce`) з `sort` + `default_sort` (перший `<option>` панелі); на `.done` пушить `res.canonical_url`, гейт per-action: `filter`→`syncFiltersUrl`, нумерована пагінація→`syncPaginationUrl`. «Show more» URL не чіпає. `restoreFromUrl()` + `popstate` перечитують URL, виставляють DOM, шлють запит **без** push. `restoreFromUrl` пропускає запит коли URL = дефолт (нема фільтрів/пошуку, `sort` збігається з `data-sort`, `page === initPage`) **або** коли `data-init-filters` + `data-sort` + сторінка вже збігаються. `filter()` завжди скидає на сторінку 1. Події `AjaxPaginationDone` / `AjaxFilterDone` на `document` і на `.wr-posts`.

**Кнопка «All» (`.wr-filters__item--all`).** Клік скидає лише категорійні фільтри (кнопки + селекти), не чіпає пошук/сортування, сабмітить форму. Активна автоматично коли знято останній фільтр (у т.ч. в single-select режимі `data-multiply="false"` — клік по активній кнопці знімає її й вертає «All»). Окрема `.wr-filters__clear` чистить усе.

### Адмін-консоль (`frontend/`)

Vite + React 18 + TypeScript. Призначення — **тільки клієнтський конструктор рядка шорткоду**, жодних запитів на бекенд (тому в `frontend/` немає і не має з'являтися каталогу `client/`, поки консоль не почне ходити в `admin-ajax.php`). Читає лише `window.wralmSettings`.

- Стан форми — цілком у `frontend/src/hooks/useShortcodeBuilder.ts`; компоненти презентаційні, отримують стан і сетери пропсами. Там же логіка складання обох рядків шорткоду (helper `attr()` пропускає порожні значення).
- Вкладки в `frontend/src/components/Tabs/` (`main` / `classes` / `filters` / `search` / `order`), спільні інпути — в `sharedComponents/`.
- Поля URL-sync (вкладка Main): `MainSettings.syncFiltersUrl` / `MainSettings.syncPaginationUrl` (обидва bool, default `true`) → `sync_filters_url="false"` / `sync_pagination_url="false"` на `[all_posts_ajax]` (емітяться лише коли вимкнено). 1.5.0-поле `updateUrl` / атрибут `update_url` прибрано і з консолі, і з PHP.
- Вкладка Order: `OrderSettings = { enableOrder: bool; sortRows: {label, orderBy, direction}[] }`. `OrderTab` — тумблер + repeater (label input, «Sort by» select, direction select, remove; «Add option»). Перше вмикання сідить `SEED_SORT_ROWS` (Newest/Oldest/Title A–Z/Title Z–A — англ. рядки, юзер редагує). `useShortcodeBuilder` кодує в `sort_options="orderby:order:label|..."` (`|` у label → `/`). `hasFilters` враховує `sortRows.length`. Для `post_type="product"` вкладка Main показує підказку про WooCommerce.
- Аліас `@` → корінь `frontend/`, тому імпорти виглядають як `@/src/...`.
- Стилі: `frontend/src/styles.scss` (директиви `@tailwind` + кастомні `@layer`-утиліти) + Tailwind (`tailwind.config.ts`, `postcss.config.js`, sass). `frontend/src/styles.css` — порожній невикористаний залишок.
- Глобал IIFE-збірки — `WebRevizorAiAgent` (`vite.config.ts` `lib.name`), вхід `frontend/src/index.tsx`.

### Іконки

Нова іконка = поклади `frontend/src/icons/<name>.svg`, перезбери `frontend/` → спрайт і `sprite-info.ts` оновляться автоматично. У коді: `<Icon name="common/<name>" />` (усі іконки в категорії `common`).

### Додавання поля в конструктор

1. Розшир відповідний тип у `frontend/src/types/index.ts`.
2. Додай дефолт у `frontend/src/hooks/useShortcodeBuilder.ts` і, за потреби, рядок у `attr(...)`-складання.
3. Додай контрол у відповідну вкладку в `frontend/src/components/Tabs/`.
4. Додай атрибут у `shortcode_defaults()` відповідного DTO (`WRALM_Query_Config` для `[all_posts_ajax]`, `WRALM_Filter_Config` для `[all_posts_ajax_filters]`), спарси його у `from_atts()` (для `WRALM_Query_Config` ще `from_request()` + `data_attrs()`) і проведи до JS / в'юхи (в'юхи читають `$config` напряму).

## Каталоги з локальною документацією

`frontend/AGENTS.md`, `frontend/src/components/sharedComponents/Button/AGENTS.md`, `.../SlideDown/AGENTS.md` — читай їх перед роботою у відповідних місцях.
