# WRALM Fixes & Improvements — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the pagination / filtering / search / WooCommerce bugs in the plugin and remove config duplication behind a single DTO, without dropping any existing feature, adding an opt-in `update_url` flag for real-URL behaviour.

**Architecture:** Introduce `WRALM_Query_Config` — one plain object that is built either from shortcode attributes or from the AJAX request, and that produces the `data-*` attribute set, the `WP_Query` args, and the pagination args. `WRALM_Shortcode::render_posts` and `WRALM_Load_More::handle_ajax` both delegate to it, so the query is defined once. `WRALM_Pagination` gets a base/format resolver (kills the 3× copy-paste) and learns the `update_url` flag. The public JS is refactored per-instance so `filter_id` finally scopes selectors, with in-flight request cancellation and single `pushState`. Search is scoped to our queries and stops full-scanning `wp_postmeta`. WooCommerce visibility + loop setup are honoured when `post_type=product`.

**Tech Stack:** PHP 8.3 / WordPress, jQuery (WP global, not bundled), Vite; admin console React 18 + TS + Tailwind (external React).

**Spec:** `docs/superpowers/specs/2026-08-29-wralm-fixes-and-improvements.md`

## Global Constraints

- Nothing removed from the public feature set. Existing shortcodes behave identically unless the owner opts into a new attribute.
- Warning-clean on PHP 8.1+ (dev env is 8.3).
- Package manager **yarn**. Two builds: root `yarn build` → `dist/js/load_more_and_filter.js`; `frontend/` `yarn build` → `dist/app.js`, `dist/style.css`, `template-parts/sprite.php`, `frontend/src/components/sharedComponents/Icon/sprite-info.ts`. All committed.
- Version identical in 4 places (`Version:` header, `WRALM_VERSION`, `package.json`, `frontend/package.json`). Currently header=`1.4.0`, package=`1.3.1` (drift — fix). Target `1.5.0`.
- **No automated test harness exists.** Per-task verification = `php -l <file>` on every touched PHP file, `cd frontend && yarn lint`, both `yarn build`s succeed, plus the manual checklist in Appendix A run in a local WP + WooCommerce install.
- jQuery stays a WP global. Admin React stays external. Public JS events (`AjaxPaginationDone`, `AjaxFilterDone`) and all existing CSS class/id hooks stay.
- Commit after every task. Branch: `fix/wralm-1.5.0` off `main`.

**TDD note:** the repo has no PHP/JS test runner and adding one is out of scope. Each task therefore substitutes a concrete manual verification for the automated red/green cycle. Do not skip it — run the check and paste the result into the task before committing.

---

## File Structure

**New**
- `inc/class-query-config.php` — `WRALM_Query_Config` DTO. Builds from atts or request; emits `data-*`, `WP_Query` args, pagination args. Single source of truth for the list query.
- `inc/class-filter-config.php` — `WRALM_Filter_Config` DTO. Replaces the `$load_more_variables` global for the filter panel views (global still set for back-compat).
- `inc/class-woo.php` — `WRALM_Woo` helper: `is_product_query()`, `visibility_tax_query()`, `setup_loop()`, `reset_loop()`, `orderby_args()`. No-ops when WooCommerce is inactive.

**Modified**
- `wr-ajax-load-more-and-filters.php` — require new files; version bump.
- `inc/class-shortcode.php` — `render_posts` / `render_filters` delegate to the DTOs.
- `inc/class-load-more.php` — `handle_ajax` delegates to `WRALM_Query_Config::from_request`; localize adds `nonce`; drop dead `query` payload reliance.
- `inc/class-pagination.php` — `links()` accepts `base`/`format`/`add_args`/`update_url` without re-reading globals; new `resolve_base()` static.
- `inc/views/filter.php` — child buttons get `data-taxonomy`; `get_terms` new signature; queried-object guard; item-limit counting fix; recursive nesting; class hooks for scoping; `enable_clear_button`-only render.
- `inc/views/order.php` — optional `orderby` select alongside the existing direction select.
- `inc/class-search-acf.php` — scope guard; `meta_key IN` / drop unfiltered postmeta scan; bootstrap the field cache; include `post`.
- `src/js/load_more_and_filter.js` — per-instance controller, `filter_id` scoping, single `pushState`, request cancellation, `page` fallback, `update_url` honouring, send `orderby`/`nonce`, drop `query`.
- `frontend/src/types/index.ts`, `frontend/src/hooks/useShortcodeBuilder.ts`, `frontend/src/components/Tabs/MainTab.tsx`, `.../OrderTab.tsx` — new controls: `update_url`, `orderby` options, Woo visibility opt-out.
- `README.md`, `CLAUDE.md` — document DTO, `update_url`, `orderby`, WooCommerce behaviour, multi-instance.

---

## Phase 1 — DTO foundation (no behaviour change)

### Task 1.1: `WRALM_Query_Config` skeleton + `from_atts` + `data_attrs`

**Files:**
- Create: `inc/class-query-config.php`
- Modify: `wr-ajax-load-more-and-filters.php:22-27` (add `require_once`)

**Interfaces — Produces:**
```php
class WRALM_Query_Config {
    public string $post_type = 'post';
    public int    $posts_per_page = 10;
    public int    $paged = 1;
    public string $pagination_type = 'default';
    public string $row_classes = 'posts_row';
    public string $load_more_label = '';
    public string $load_more_classes = 'load_more_button';
    public string $prev_text = '';
    public string $next_text = '';
    public string $filter_id = '';
    public bool   $update_url = true;
    public bool   $archive_context = false;
    /** @var array<string,string[]> taxonomy => slugs (operator IN) */
    public array  $tax_filters = array();
    public int    $archive_term_id = 0;
    public string $archive_taxonomy = '';
    public string $search = '';
    public string $orderby = 'date';
    public string $order = 'DESC';
    public string $base_url = '';

    const ORDERBY_WHITELIST = array(
        'date','title','menu_order','rand','modified','comment_count',
        'price','popularity','rating',
    );

    public static function shortcode_defaults(): array;   // for shortcode_atts()
    public static function from_atts( $atts ): self;
    public function data_attrs(): array;                   // ['data-...' => 'value']
    public function render_data_attr_string(): string;     // ' data-x="y" data-z="w"'
}
```

- [ ] **Step 1: Write the class file**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Single source of truth for the [all_posts_ajax] list query.
 * Built either from shortcode attributes (from_atts) or from the AJAX
 * request (from_request, added in Task 1.2). Produces the data-* set,
 * the WP_Query args, and the pagination args.
 */
class WRALM_Query_Config {

    public $post_type = 'post';
    public $posts_per_page = 10;
    public $paged = 1;
    public $pagination_type = 'default';
    public $row_classes = 'posts_row';
    public $load_more_label = '';
    public $load_more_classes = 'load_more_button';
    public $prev_text = '';
    public $next_text = '';
    public $filter_id = '';
    public $update_url = true;
    public $archive_context = false;
    public $tax_filters = array();
    public $archive_term_id = 0;
    public $archive_taxonomy = '';
    public $search = '';
    public $orderby = 'date';
    public $order = 'DESC';
    public $base_url = '';

    const ORDERBY_WHITELIST = array(
        'date', 'title', 'menu_order', 'rand', 'modified', 'comment_count',
        'price', 'popularity', 'rating',
    );

    public static function shortcode_defaults() {
        return array(
            'post_type'         => 'post',
            'posts_per_page'    => '10',
            'type_pagination'   => 'default',
            'row_classes'       => 'posts_row',
            'load_more_label'   => __( 'Show more', 'wr-ajax-load-more-and-filters' ),
            'load_more_classes' => 'load_more_button',
            'prev_text'         => __( 'Previous', 'wr-ajax-load-more-and-filters' ),
            'next_text'         => __( 'Next', 'wr-ajax-load-more-and-filters' ),
            'filter_id'         => '',
            'update_url'        => 'true',
            'orderby'           => 'date',
        );
    }

    public static function from_atts( $atts ) {
        $a = shortcode_atts( self::shortcode_defaults(), $atts, 'all_posts_ajax' );
        $c = new self();

        $c->post_type         = sanitize_key( $a['post_type'] );
        $c->posts_per_page    = (int) $a['posts_per_page'];
        $c->pagination_type   = sanitize_key( $a['type_pagination'] );
        $c->row_classes       = sanitize_text_field( $a['row_classes'] );
        $c->load_more_label   = sanitize_text_field( $a['load_more_label'] );
        $c->load_more_classes = sanitize_text_field( $a['load_more_classes'] );
        $c->prev_text         = sanitize_text_field( $a['prev_text'] );
        $c->next_text         = sanitize_text_field( $a['next_text'] );
        $c->update_url        = ( 'false' !== strtolower( (string) $a['update_url'] ) );
        $c->orderby           = self::sanitize_orderby( $a['orderby'] );

        $c->filter_id = $a['filter_id'] !== ''
            ? sanitize_key( $a['filter_id'] )
            : $c->post_type . '_filter';

        // Page context: real /page/N/ URLs only make sense on archive-ish pages.
        $c->archive_context = ( is_archive() || is_home() || is_front_page()
            || is_post_type_archive() || is_category() || is_tag() || is_tax() );

        $c->paged = self::current_paged();

        $term = get_queried_object();
        if ( $term instanceof WP_Term ) {
            $c->archive_term_id  = (int) $term->term_id;
            $c->archive_taxonomy = (string) $term->taxonomy;
        }

        return $c;
    }

    public static function sanitize_orderby( $value ) {
        $value = sanitize_key( $value );
        return in_array( $value, self::ORDERBY_WHITELIST, true ) ? $value : 'date';
    }

    /** Front page uses ?page, everything else ?paged. */
    public static function current_paged() {
        $paged = (int) get_query_var( 'paged' );
        if ( ! $paged ) {
            $paged = (int) get_query_var( 'page' );
        }
        return max( 1, $paged );
    }

    public function data_attrs() {
        return array(
            'data-filter-id'        => $this->filter_id,
            'data-pagination-type'  => $this->pagination_type,
            'data-posts-per-page'   => (string) $this->posts_per_page,
            'data-posts-type'       => $this->post_type,
            'data-more-classes'     => $this->load_more_classes,
            'data-more-label'       => $this->load_more_label,
            'data-prev-text'        => $this->prev_text,
            'data-next-text'        => $this->next_text,
            'data-cat-id'           => (string) $this->archive_term_id,
            'data-cat-taxonomy'     => $this->archive_taxonomy,
            'data-orderby'          => $this->orderby,
            'data-update-url'       => $this->update_url ? 'true' : 'false',
            'data-archive-context'  => $this->archive_context ? 'true' : 'false',
        );
    }

    public function render_data_attr_string() {
        $out = '';
        foreach ( $this->data_attrs() as $k => $v ) {
            $out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
        }
        return $out;
    }
}
```

- [ ] **Step 2: Wire the require**

In `wr-ajax-load-more-and-filters.php`, add after line 22 (`require_once ... class-pagination.php;`):
```php
require_once WRALM_PATH . 'inc/class-query-config.php';
```

- [ ] **Step 3: Verify**

Run: `php -l inc/class-query-config.php` → `No syntax errors`.
Manual: add `error_log( print_r( WRALM_Query_Config::from_atts( array( 'post_type' => 'page', 'update_url' => 'false' ) ), true ) );` to a scratch mu-plugin, load any page, confirm `filter_id = page_filter`, `update_url = false`, `paged >= 1`. Remove the scratch line.

- [ ] **Step 4: Commit**

```bash
git add inc/class-query-config.php wr-ajax-load-more-and-filters.php
git commit -m "feat: add WRALM_Query_Config DTO (from_atts + data-* emitter)"
```

---

### Task 1.2: `from_request` + `wp_query_args` + `pagination_args`

**Files:**
- Modify: `inc/class-query-config.php`

**Interfaces — Consumes:** the class from Task 1.1.
**Interfaces — Produces:**
```php
public static function from_request( array $req ): self;   // sanitises $_POST-shaped input
public function wp_query_args(): array;                     // full WP_Query args
public function pagination_args( string $base, string $format, array $add_args = array() ): array;
```

- [ ] **Step 1: Add `from_request`**

```php
public static function from_request( array $req ) {
    $c = new self();

    $pt = isset( $req['post_type'] ) ? sanitize_key( $req['post_type'] ) : 'post';
    if ( ! post_type_exists( $pt ) || ! is_post_type_viewable( $pt ) ) {
        $pt = 'post';
    }
    $c->post_type = $pt;

    $c->posts_per_page  = isset( $req['posts_per_page'] ) ? (int) $req['posts_per_page'] : 10;
    $c->pagination_type = isset( $req['pagination_type'] ) ? sanitize_key( $req['pagination_type'] ) : 'default';
    $c->load_more_classes = isset( $req['more_classes'] ) ? sanitize_text_field( $req['more_classes'] ) : '';
    $c->load_more_label   = isset( $req['more_label'] ) ? sanitize_text_field( $req['more_label'] ) : '';
    $c->prev_text = isset( $req['prev_text'] ) ? sanitize_text_field( $req['prev_text'] ) : '';
    $c->next_text = isset( $req['next_text'] ) ? sanitize_text_field( $req['next_text'] ) : '';
    $c->filter_id = isset( $req['filter_id'] ) ? sanitize_key( $req['filter_id'] ) : '';
    $c->update_url = ! isset( $req['update_url'] ) || 'false' !== strtolower( (string) $req['update_url'] );
    $c->archive_context = isset( $req['archive_context'] ) && 'true' === strtolower( (string) $req['archive_context'] );

    $c->paged = isset( $req['page'] ) ? max( 1, absint( $req['page'] ) ) : 1;

    $c->base_url = isset( $req['base_url'] ) ? esc_url_raw( $req['base_url'] ) : home_url( '/' );

    $c->archive_term_id  = isset( $req['category_id'] ) ? absint( $req['category_id'] ) : 0;
    $c->archive_taxonomy = isset( $req['category_taxonomy'] ) ? sanitize_key( $req['category_taxonomy'] ) : '';

    if ( ! empty( $req['search'] ) ) {
        $c->search = sanitize_text_field( wp_unslash( $req['search'] ) );
    }

    $c->order   = ( isset( $req['order'] ) && 'ASC' === strtoupper( $req['order'] ) ) ? 'ASC' : 'DESC';
    $c->orderby = isset( $req['orderby'] ) ? self::sanitize_orderby( $req['orderby'] ) : 'date';

    if ( ! empty( $req['category'] ) && is_array( $req['category'] ) ) {
        foreach ( $req['category'] as $taxonomy => $slugs ) {
            $taxonomy = sanitize_key( $taxonomy );
            if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }
            $slugs = array_values( array_filter( array_map( 'sanitize_title', (array) $slugs ) ) );
            if ( $slugs ) {
                $c->tax_filters[ $taxonomy ] = $slugs;
            }
        }
    }

    return $c;
}
```

- [ ] **Step 2: Add the hide-meta + tax-query helpers**

```php
private function hide_meta_query() {
    return array(
        'relation' => 'OR',
        array( 'key' => 'all_posts_ajax_hide', 'value' => '1', 'compare' => '!=' ),
        array( 'key' => 'all_posts_ajax_hide', 'compare' => 'NOT EXISTS' ),
    );
}

/**
 * Merge the archive term scope with the user's taxonomy filters.
 * A user filter on the SAME taxonomy as the archive term replaces the
 * archive scope (the user explicitly chose terms there); filters on other
 * taxonomies are AND-ed on top. Fixes the "archive + same-taxonomy filter
 * = zero results" bug.
 */
private function build_tax_query() {
    $clauses = array( 'relation' => 'AND' );

    foreach ( $this->tax_filters as $taxonomy => $slugs ) {
        $clauses[] = array(
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $slugs,
            'operator' => 'IN',
        );
    }

    if ( $this->archive_term_id && $this->archive_taxonomy
        && ! isset( $this->tax_filters[ $this->archive_taxonomy ] ) ) {
        $clauses[] = array(
            'taxonomy' => $this->archive_taxonomy,
            'field'    => 'term_id',
            'terms'    => array( $this->archive_term_id ),
        );
    }

    if ( class_exists( 'WRALM_Woo' ) && WRALM_Woo::is_product_query( $this->post_type ) ) {
        $vis = WRALM_Woo::visibility_tax_query();
        if ( $vis ) {
            $clauses[] = $vis;
        }
    }

    return count( $clauses ) > 1 ? $clauses : array();
}
```
(`WRALM_Woo` is added in Phase 9; the `class_exists` guard keeps this safe until then.)

- [ ] **Step 3: Add `wp_query_args`**

```php
public function wp_query_args() {
    $args = array(
        'post_type'      => $this->post_type,
        'posts_per_page' => $this->posts_per_page,
        'post_status'    => 'publish',
        'paged'          => $this->paged,
        'meta_query'     => $this->hide_meta_query(),
        'order'          => $this->order,
    );

    $tax_query = $this->build_tax_query();
    if ( $tax_query ) {
        $args['tax_query'] = $tax_query;
    }

    if ( '' !== $this->search ) {
        $args['s'] = $this->search;
        $args['wralm_search'] = 1; // scope flag for WRALM_Search_ACF
    }

    // orderby mapping (WooCommerce-aware keys handled in Phase 9 via WRALM_Woo)
    if ( class_exists( 'WRALM_Woo' ) && WRALM_Woo::is_product_query( $this->post_type ) ) {
        $args = array_merge( $args, WRALM_Woo::orderby_args( $this->orderby, $this->order ) );
    } else {
        $map = array( 'price' => 'date', 'popularity' => 'comment_count', 'rating' => 'date' );
        $orderby = isset( $map[ $this->orderby ] ) ? $map[ $this->orderby ] : $this->orderby;
        $args['orderby'] = $orderby;
    }

    return apply_filters( 'wralm_query_args', $args, $this );
}
```

- [ ] **Step 4: Add `pagination_args`**

```php
public function pagination_args( $base, $format, $add_args = array() ) {
    return array(
        'base'              => $base,
        'format'            => $format,
        'current'           => $this->paged,
        'type'              => $this->pagination_type,
        'update_url'        => $this->update_url,
        'add_args'          => $add_args,
        'load_more_classes' => $this->load_more_classes,
        'load_more_label'   => $this->load_more_label !== '' ? $this->load_more_label : __( 'Show more', 'wr-ajax-load-more-and-filters' ),
        'prev_text'         => $this->prev_text !== '' ? $this->prev_text : __( 'Previous', 'wr-ajax-load-more-and-filters' ),
        'next_text'         => $this->next_text !== '' ? $this->next_text : __( 'Next', 'wr-ajax-load-more-and-filters' ),
    );
}
```

- [ ] **Step 5: Verify**

Run: `php -l inc/class-query-config.php`.
Manual (scratch mu-plugin): `WRALM_Query_Config::from_request( array( 'post_type' => 'post', 'page' => '', 'category' => array( 'category' => array( 'news' ) ) ) )->wp_query_args()` — confirm `paged === 1` (not 0), `tax_query` has one `slug`/`IN` clause, `post_status === 'publish'`.

- [ ] **Step 6: Commit**

```bash
git add inc/class-query-config.php
git commit -m "feat: WRALM_Query_Config from_request + wp_query_args + pagination_args"
```

---

### Task 1.3: `WRALM_Pagination::resolve_base()` + `links()` without globals

**Files:**
- Modify: `inc/class-pagination.php`

**Interfaces — Produces:**
```php
public static function resolve_base( bool $update_url, bool $archive_context, string $url ): array; // [ $base, $format ]
// links() now honours $args['update_url'] (bool) and $args['add_args'],
// and skips get_pagenum_link()/get_query_var() when base+format+total+current are all supplied.
```

- [ ] **Step 1: Add `resolve_base`**

```php
/**
 * Compute the paginate_links() base + format for a given context.
 * Pretty /page/N/ URLs are only emitted on archive-ish pages AND when
 * update_url is on; everywhere else fall back to ?paged=N so the plugin
 * never produces a broken /page/N/ link on a static page.
 */
public static function resolve_base( $update_url, $archive_context, $url ) {
    global $wp_rewrite;

    $url  = html_entity_decode( $url );
    $path = strtok( $url, '?' );
    $base = trailingslashit( $path ) . '%_%';

    $pretty = $update_url && $archive_context && $wp_rewrite->using_permalinks();

    if ( $pretty ) {
        $format  = $wp_rewrite->using_index_permalinks() && false === strpos( $base, 'index.php' ) ? 'index.php/' : '';
        $format .= user_trailingslashit( $wp_rewrite->pagination_base . '/%#%', 'paged' );
    } else {
        $format = '?paged=%#%';
    }

    return array( $base, $format );
}
```

- [ ] **Step 2: Make `links()` not depend on globals when fully supplied**

At the top of `links()`, replace the "Setting up default values based on the current URL" block (lines ~17-30) with:
```php
$has_explicit = is_array( $args )
    && isset( $args['base'], $args['format'], $args['total'], $args['current'] );

if ( $has_explicit ) {
    $url_parts     = array( strtok( (string) $args['base'], '?' ) );
    $pagenum_link  = $args['base'];
    $format        = $args['format'];
} else {
    $pagenum_link = html_entity_decode( get_pagenum_link() );
    $url_parts    = explode( '?', $pagenum_link );
    $total        = isset( $GLOBALS['wp_query']->max_num_pages ) ? $GLOBALS['wp_query']->max_num_pages : 1;
    $current      = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
    $pagenum_link = trailingslashit( $url_parts[0] ) . '%_%';
    $format  = $wp_rewrite->using_index_permalinks() && ! strpos( $pagenum_link, 'index.php' ) ? 'index.php/' : '';
    $format .= $wp_rewrite->using_permalinks() ? user_trailingslashit( $wp_rewrite->pagination_base . '/%#%', 'paged' ) : '?paged=%#%';
}
```
Add `'update_url' => true,` to the `$defaults` array.

- [ ] **Step 3: Honour `update_url` on every generated `href`**

Add a helper inside the class:
```php
private static function href( $link, $update_url ) {
    return $update_url ? esc_url( apply_filters( 'paginate_links', $link ) ) : '#';
}
```
Replace each `esc_url( apply_filters( 'paginate_links', $link ) )` in `links()` (prev link, page links, next link, load_more link — 4 sites) with `self::href( $link, $args['update_url'] )`.

- [ ] **Step 4: Verify**

Run: `php -l inc/class-pagination.php`.
Manual: `WRALM_Pagination::resolve_base( false, false, 'https://x.test/blog/page/3/' )` → `[ 'https://x.test/blog/page/3/%_%', '?paged=%#%' ]`. `resolve_base( true, true, ... )` on a permalink site → pretty format.
Manual: call `WRALM_Pagination::links( array( 'base' => 'https://x.test/blog/%_%', 'format' => '?paged=%#%', 'total' => 3, 'current' => 1, 'type' => 'default', 'update_url' => false ) )` — the `load_more` `<a>` has `href="#"` and `data-page="2"`.

- [ ] **Step 5: Commit**

```bash
git add inc/class-pagination.php
git commit -m "feat: WRALM_Pagination::resolve_base + update_url-aware links() without globals"
```

---

### Task 1.4: `render_posts` delegates to the DTO (output byte-compatible for defaults)

**Files:**
- Modify: `inc/class-shortcode.php:18-138`

**Interfaces — Consumes:** `WRALM_Query_Config::from_atts`, `->wp_query_args`, `->render_data_attr_string`, `->pagination_args`; `WRALM_Pagination::resolve_base`.

- [ ] **Step 1: Rewrite `render_posts`**

```php
public function render_posts( $atts ) {
    $config = WRALM_Query_Config::from_atts( $atts );

    $wp_query = new WP_Query( $config->wp_query_args() );

    list( $base, $format ) = WRALM_Pagination::resolve_base(
        $config->update_url,
        $config->archive_context,
        get_pagenum_link()
    );

    $is_product = class_exists( 'WRALM_Woo' ) && WRALM_Woo::is_product_query( $config->post_type );

    $posts_result = '';
    $pagination   = '';

    if ( $wp_query->have_posts() ) {
        if ( $is_product ) { WRALM_Woo::setup_loop( $wp_query ); }
        ob_start();
        while ( $wp_query->have_posts() ) {
            $wp_query->the_post();
            get_template_part( 'all_posts_ajax/' . $config->post_type . '-card' );
        }
        $posts_result = ob_get_clean();
        if ( $is_product ) { WRALM_Woo::reset_loop(); }

        if ( $wp_query->max_num_pages > 1 ) {
            $pagination = WRALM_Pagination::links(
                $config->pagination_args( $base, $format ) + array( 'total' => $wp_query->max_num_pages )
            );
        }
        wp_reset_postdata();
    }

    $results  = '<div class="ajax_row_holder" data-init-page="' . esc_attr( $config->paged ) . '"';
    $results .= ' data-filter-id="' . esc_attr( $config->filter_id ) . '">';
    $results .= '<div class="ajax_row ' . esc_attr( $config->row_classes ) . '"'
        . $config->render_data_attr_string() . '>';
    $results .= $posts_result;
    $results .= '</div>';
    $results .= $pagination;
    $results .= '</div>';

    return $results;
}
```
Notes: the `WRALM_Woo` calls are guarded by `class_exists` and are inert until Phase 9. `data-filter-id` is now always present (was conditional) — additive, needed for JS scoping in Phase 6.

- [ ] **Step 2: Verify (regression — this is the critical no-change gate)**

Manual, in a WP install with an existing `[all_posts_ajax post_type="post" posts_per_page="5"]` page:
- View source before/after. The `.ajax_row` `data-*` set is the same keys/values plus the new `data-filter-id`, `data-orderby="date"`, `data-update-url="true"`, `data-archive-context`.
- Pagination markup identical for `type_pagination` = `default`, `list`, `both`, `none`.
- On a **static page** the pagination links are now `?paged=2` instead of `/page/2/` (bug fix — call this out in the task notes as expected).
- `php -l inc/class-shortcode.php`.

- [ ] **Step 3: Commit**

```bash
git add inc/class-shortcode.php
git commit -m "refactor: render_posts delegates to WRALM_Query_Config; adds data-filter-id"
```

---

### Task 1.5: `WRALM_Filter_Config` + `render_filters` + views use it

**Files:**
- Create: `inc/class-filter-config.php`
- Modify: `inc/class-shortcode.php:140-210`, `wr-ajax-load-more-and-filters.php`
- Modify: `inc/views/filter.php:1-7`, `inc/views/order.php:1-7`

**Interfaces — Produces:**
```php
class WRALM_Filter_Config {
    // one typed property per current $load_more_variables key, plus:
    public bool   $enable_order = false;
    public string $orderby_options = '';   // csv, Phase 8
    public array  $orderby_labels = array();
    public static function from_atts( $atts ): self;
    public function to_legacy_array(): array;   // === old $load_more_variables shape
}
```

- [ ] **Step 1: Create `WRALM_Filter_Config`** — one property per key in the current `$load_more_variables` (`class-shortcode.php:169-188`), same defaults, plus `enable_order`. `from_atts()` runs `shortcode_atts` with the current `render_filters` `$default` array (keep `enable_order => false`, add nothing removed), and **fix** `filter_expand_class` default from `'filter_expand_class'` to `'filter_expand'`. `to_legacy_array()` returns exactly the old associative shape so views keep working.

- [ ] **Step 2: Rewrite `render_filters`**

```php
public function render_filters( $atts ) {
    global $load_more_variables;

    $config = WRALM_Filter_Config::from_atts( $atts );
    $load_more_variables = $config->to_legacy_array(); // back-compat for any custom view override

    $filter_id = $config->filter_id;
    $wrap_attr = ' data-filter-id="' . esc_attr( $filter_id ) . '"';

    $results = '<div class="ajax_filters_wrapper"' . $wrap_attr . '>';

    $need_filter_view = in_array( 'true', array(
        (string) $config->filter_by_category,
        (string) $config->enable_search,
        (string) $config->enable_clear_button,   // FIX: clear-button-only now renders
    ), true );

    if ( $need_filter_view ) {
        ob_start();
        require WRALM_PATH . 'inc/views/filter.php';
        $results .= ob_get_clean();
    }

    if ( 'true' === (string) $config->enable_order ) {
        ob_start();
        require WRALM_PATH . 'inc/views/order.php';
        $results .= ob_get_clean();
    }

    $results .= '</div>';
    return $results;
}
```
`filter_id` default in `WRALM_Filter_Config::from_atts` mirrors `WRALM_Query_Config`: `$filter_id ?: $post_type . '_filter'`.

- [ ] **Step 3: View headers** — `inc/views/filter.php` and `inc/views/order.php`: keep `global $load_more_variables;` (back-compat) but add right after it:
```php
$load_more_variables = isset( $config ) && $config instanceof WRALM_Filter_Config
    ? $config->to_legacy_array()
    : $load_more_variables;
```

- [ ] **Step 4: Verify** — `php -l` on all three files. Manual: an existing `[all_posts_ajax_filters ...]` renders byte-identical markup except `filter_expand` class name; a `[all_posts_ajax_filters enable_clear_button="true"]` (no category/search) now shows the Clear button.

- [ ] **Step 5: Commit**

```bash
git add inc/class-filter-config.php inc/views/filter.php inc/views/order.php inc/class-shortcode.php wr-ajax-load-more-and-filters.php
git commit -m "refactor: WRALM_Filter_Config replaces \$load_more_variables construction; fix clear-button-only render + filter_expand class"
```

---

### Task 1.6: `handle_ajax` delegates to the DTO

**Files:**
- Modify: `inc/class-load-more.php:43-152`

- [ ] **Step 1: Rewrite `handle_ajax`**

```php
public function handle_ajax() {
    $this->maybe_check_nonce(); // no-op until Phase 10

    $config = WRALM_Query_Config::from_request( wp_unslash( $_POST ) );

    $query = new WP_Query( $config->wp_query_args() );

    $is_product = class_exists( 'WRALM_Woo' ) && WRALM_Woo::is_product_query( $config->post_type );

    ob_start();
    if ( $query->have_posts() ) {
        if ( $is_product ) { WRALM_Woo::setup_loop( $query ); }
        while ( $query->have_posts() ) {
            $query->the_post();
            get_template_part( 'all_posts_ajax/' . $config->post_type . '-card' );
        }
        if ( $is_product ) { WRALM_Woo::reset_loop(); }
        wp_reset_postdata();
    }
    $html = ob_get_clean();

    list( $base, $format ) = WRALM_Pagination::resolve_base(
        $config->update_url,
        $config->archive_context,
        $config->base_url
    );

    // preserve non-pagination query args (?filter_search=, custom params) on links
    $add_args = array();
    $parsed   = wp_parse_url( $config->base_url );
    if ( ! empty( $parsed['query'] ) ) {
        wp_parse_str( $parsed['query'], $add_args );
        unset( $add_args['paged'], $add_args['page'] );
    }

    $pagination = WRALM_Pagination::links(
        $config->pagination_args( $base, $format, $add_args ) + array( 'total' => $query->max_num_pages )
    );

    wp_send_json( array(
        'html'       => $html,
        'pagination' => $pagination,
        'max_page'   => $query->max_num_pages,
        'base_url'   => $config->update_url ? preg_replace( '#/(?:page|' . preg_quote( $GLOBALS['wp_rewrite']->pagination_base, '#' ) . ')/\d+/?$#', '/', $config->base_url ) : $config->base_url,
    ) );
}

private function maybe_check_nonce() {} // Phase 10 fills this in
```

- [ ] **Step 2: Verify** — `php -l inc/class-load-more.php`. Manual: trigger a load-more click on an existing page, confirm `html`/`pagination`/`max_page`/`base_url` still returned and the list appends. Confirm `?filter_search=x` in the URL survives into the next-page link.

- [ ] **Step 3: Commit**

```bash
git add inc/class-load-more.php
git commit -m "refactor: handle_ajax delegates to WRALM_Query_Config; preserve query args on pagination links"
```

---

## Phase 2 — Query correctness (covered structurally by Phase 1, verify explicitly)

### Task 2.1: Regression matrix for the Phase 1 query changes

**Files:** none (verification-only task; add findings to `docs/superpowers/plans/notes-phase2.md`).

- [ ] **Step 1:** In a local WP install run Appendix A checklist rows 1–12. Record pass/fail per row.
- [ ] **Step 2:** Specifically confirm the four latent bugs are fixed:
  - empty `page` POST → results are page 1, current page highlighted (was `0`).
  - archive `/category/news/` + click sibling term `sport` (same taxonomy) → shows `sport` posts (was empty).
  - static page host → `?paged=2` links, no 404.
  - logged-in user sees the same count before and after AJAX (both `publish`).
- [ ] **Step 3: Commit**

```bash
git add docs/superpowers/plans/notes-phase2.md
git commit -m "docs: phase 2 regression matrix results"
```

---

## Phase 3 — `update_url` flag end-to-end (PHP done; JS in Phase 5)

### Task 3.1: `update_url` in the filter shortcode + data flow audit

**Files:**
- Modify: `inc/class-filter-config.php` (accept `update_url`, expose on wrapper as `data-update-url`)
- Modify: `inc/class-shortcode.php` `render_filters` (emit `data-update-url` on `.ajax_filters_wrapper`)

- [ ] **Step 1:** Add `update_url` to `WRALM_Filter_Config` (default `true`, same parse rule as `WRALM_Query_Config`). Emit `data-update-url` on `.ajax_filters_wrapper` so the filter panel's own submit path can read it without depending on the list block.
- [ ] **Step 2: Verify** — `php -l`; view source shows `data-update-url` on both `.ajax_row` and `.ajax_filters_wrapper`.
- [ ] **Step 3: Commit**

```bash
git add inc/class-filter-config.php inc/class-shortcode.php
git commit -m "feat: thread update_url onto the filter wrapper"
```

---

## Phase 4 — Filter view bugs (`inc/views/filter.php`)

### Task 4.1: Child buttons get `data-taxonomy`; `get_terms` new signature; queried-object guard

**Files:**
- Modify: `inc/views/filter.php`

- [ ] **Step 1: Queried-object guard** — replace `$term = get_queried_object();` (line ~31) with:
```php
$queried   = get_queried_object();
$exclude_term_id = ( $queried instanceof WP_Term ) ? (int) $queried->term_id : 0;
```
Replace `'exclude' => $term->term_id` (line ~47) with `'exclude' => $exclude_term_id`.

- [ ] **Step 2: `get_terms` new signature** — all 4 call sites:
```php
// was: get_terms( $taxonomy, array( 'parent' => 0, 'exclude' => $exclude_term_id ) )
get_terms( array( 'taxonomy' => $taxonomy, 'parent' => 0, 'exclude' => $exclude_term_id, 'hide_empty' => false ) )
```
and for children:
```php
get_terms( array( 'taxonomy' => $category->taxonomy, 'parent' => $category->term_id, 'hide_empty' => false ) )
```

- [ ] **Step 3: Child buttons carry taxonomy** — in the `foreach ( $childrensCat as $childrenCat )` button (line ~76-82), add `data-taxonomy="<?= esc_attr( $taxonomy ) ?>"`. This is the fix that makes child-category filtering work in `button` mode.

- [ ] **Step 4: Verify** — render a `button`-mode filter with a nested taxonomy; click a child term; confirm the network request has `category[<taxonomy>][]=<child-slug>` (not `category[undefined]`) and the list narrows. `php -l inc/views/filter.php`.

- [ ] **Step 5: Commit**

```bash
git add inc/views/filter.php
git commit -m "fix: child-category buttons send data-taxonomy; modern get_terms signature; queried-object guard"
```

---

### Task 4.2: `filter_item_limit` counting + recursive nesting

**Files:**
- Modify: `inc/views/filter.php`

- [ ] **Step 1: Extract a recursive term renderer** at the top of the view (function-scoped, guard against redeclare):
```php
if ( ! function_exists( 'wralm_render_filter_terms' ) ) {
    /**
     * Recursively render filter buttons for a term tree.
     * Returns the number of buttons emitted (for the item-limit toggle).
     */
    function wralm_render_filter_terms( array $terms, $taxonomy, array $v, $depth, &$printed, $limit ) {
        foreach ( $terms as $term ) {
            $children = get_terms( array(
                'taxonomy'   => $taxonomy,
                'parent'     => $term->term_id,
                'hide_empty' => false,
            ) );
            $hidden = ( $limit > 0 && $printed >= $limit ) ? ' hidden' : '';
            $depth_class = $depth === 0
                ? ( $children ? ' parentCategory' : '' )
                : ' childCategory';
            printf(
                '<button type="submit" class="js-category-filter multiply-%s %s%s%s" data-taxonomy="%s" data-slug="%s"><span class="text">%s</span><span class="postCount">%d</span></button>',
                esc_attr( $v['multiply_filter'] ),
                esc_attr( $v['filter_item_classes'] ),
                esc_attr( $depth_class ),
                esc_attr( $hidden ),
                esc_attr( $taxonomy ),
                esc_attr( $term->slug ),
                esc_html( $term->name ),
                (int) $term->count
            );
            $printed++;
            if ( $children ) {
                wralm_render_filter_terms( $children, $taxonomy, $v, $depth + 1, $printed, $limit );
            }
        }
    }
}
```

- [ ] **Step 2:** In the `button` branch, replace the hand-rolled parent/child double loop with:
```php
$printed = 0;
$limit   = (int) ( $load_more_variables['filter_item_limit'] ?? 0 );
foreach ( $categoriesArray as $taxonomy ) {
    $taxonomy = trim( $taxonomy );
    $name  = get_taxonomy( $taxonomy );
    $roots = get_terms( array(
        'taxonomy'   => $taxonomy,
        'parent'     => 0,
        'exclude'    => $exclude_term_id,
        'hide_empty' => false,
    ) );
    if ( $roots && $load_more_variables['filter_titles'] === 'true' && $name ) {
        echo '<p class="filterHeading">' . esc_html( $name->label ) . '</p>';
    }
    wralm_render_filter_terms( $roots, $taxonomy, $load_more_variables, 0, $printed, $limit );
}
```

- [ ] **Step 3:** Replace the "See all" condition (line ~132) with `if ( $limit > 0 && $printed > $limit ) :` and the `$categoriesCount` variable is gone.

- [ ] **Step 4: Verify** — with `filter_item_limit="3"` and 10 nested terms: exactly 3 visible, rest `.hidden`, "See all" shown; grandchildren now render. `php -l`.

- [ ] **Step 5: Commit**

```bash
git add inc/views/filter.php
git commit -m "fix: correct filter_item_limit counting; recursive term nesting"
```

---

### Task 4.3: `select` mode — fix disabled-option bug + recursion

**Files:**
- Modify: `inc/views/filter.php` (`select` branch)

- [ ] **Step 1:** Line ~100 `<option <?= $load_more_variables['filter_item_classes'] == 'true' ? 'disabled' : 'selected' ?>` — this compares `filter_item_classes` (a CSS string) to `'true'`, clearly a copy-paste of the wrong key. Replace with a plain placeholder option: `<option value=""><?= esc_html( $label ) ?></option>`.
- [ ] **Step 2:** Render nested `<option>`s with an indent prefix via a small recursive helper `wralm_render_filter_options( $terms, $taxonomy, $depth )` mirroring 4.2.
- [ ] **Step 3: Verify** — `select` filter shows the taxonomy label as the empty first option and indented child options; selecting one submits and filters. `php -l`.
- [ ] **Step 4: Commit**

```bash
git add inc/views/filter.php
git commit -m "fix: select-mode placeholder option + nested options"
```

---

### Task 4.4: Scoping hooks for multi-instance (markup only)

**Files:**
- Modify: `inc/views/filter.php`, `inc/views/order.php`

- [ ] **Step 1:** `<form id="all_posts_filter" class="all_posts_form" role="search">` → add `data-filter-id="<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>"`. Keep the `id` (external CSS/back-compat) — it will only be unique when a single instance is on the page, which is the current supported case.
- [ ] **Step 2:** `#all-post-search` → also add class `all-post-search` (already has it) and `data-role="search"`. `#js-post-order` → add class `js-post-order` and `data-role="order"`. Keep the ids.
- [ ] **Step 3:** `order.php` `<select id="js-post-order">` gets the same class + `data-filter-id`.
- [ ] **Step 4: Verify** — `php -l`; view source shows the classes/data attrs; single-instance CSS targeting the old ids still matches.
- [ ] **Step 5: Commit**

```bash
git add inc/views/filter.php inc/views/order.php
git commit -m "feat: add class + data-filter-id scoping hooks to filter/order views"
```

---

## Phase 5 — Public JS refactor (`src/js/load_more_and_filter.js`)

### Task 5.1: Extract a per-instance controller (single instance, behaviour preserved)

**Files:**
- Modify: `src/js/load_more_and_filter.js` (full rewrite of the IIFE body)
- Build: root `yarn build`

**Interfaces — Produces (DOM contract, unchanged):** click on `#pagination_holder .load_page`, submit on `.all_posts_form`, change on `.js-category-filter-select` / `.js-post-order`, click on `.js-category-filter` / `.js-clear-filter`; `document`-level `AjaxPaginationDone` / `AjaxFilterDone` still fire.

- [ ] **Step 1:** Rewrite as:
```js
jQuery(function ($) {
    var params = window.loadmore_params || {};

    function Instance($holder) {
        this.$holder = $holder;
        this.$row = $holder.find('.ajax_row');
        this.filterId = $holder.data('filter-id') || '';
        this.$filters = this.filterId
            ? $('.ajax_filters_wrapper[data-filter-id="' + this.filterId + '"]')
            : $('.ajax_filters_wrapper').first();
        this.$form = this.$filters.find('.all_posts_form');
        this.$search = this.$filters.find('.all-post-search');
        this.$order = this.$filters.find('.js-post-order');
        this.$orderby = this.$filters.find('.js-post-orderby'); // Phase 8
        this.$buttons = this.$filters.find('.js-category-filter');
        this.$selects = this.$filters.find('.js-category-filter-select');
        this.updateUrl = String(this.$row.data('update-url')) !== 'false';
        this.archiveContext = String(this.$row.data('archive-context')) === 'true';
        this.page = parseInt($holder.data('init-page'), 10) || 1;
        this.seq = 0;
        this.xhr = null;
        this.bind();
    }

    Instance.prototype.readRowData = function () { /* one object from this.$row.data() */ };
    Instance.prototype.collectCategories = function () { /* from this.$buttons.filter('.active') + this.$selects */ };
    Instance.prototype.buildUrl = function () { /* returns the ONE url string, or null when !updateUrl */ };
    Instance.prototype.buildData = function (clearRow) { /* the POST body; no `query` key */ };
    Instance.prototype.request = function (opts) {
        var self = this, mySeq = ++this.seq;
        if (this.xhr) { this.xhr.abort(); }
        this.$holder.css('opacity', '0.5');
        this.xhr = $.ajax({
            url: params.ajaxurl, type: 'POST', data: this.buildData(opts.clearRow),
        }).done(function (res) {
            if (mySeq !== self.seq) { return; }   // stale response guard
            self.$holder.find('.load_more_holder').remove();
            if (opts.clearRow) { self.$row.empty(); }
            self.$row.append(res.html || '<div class="no-results-found">no results found</div>');
            self.$holder.append(res.pagination);
            self.$holder.css('opacity', '1');
            if (self.updateUrl && opts.pushUrl) {
                var u = self.buildUrl();
                if (u) { window.history.pushState(null, '', u); }
            }
            $(document).trigger(opts.event);
            self.$holder.trigger(opts.event);      // additive scoped event
        });
    };
    Instance.prototype.bind = function () { /* delegated handlers scoped with this.$holder / this.$filters */ };

    $('.ajax_row_holder').each(function () { new Instance($(this)); });
});
```

- [ ] **Step 2:** Port the existing state-from-URL restore (`filter_search`, `<taxonomy>=slug`) into `Instance` — run once in the constructor, trigger one filtered request if the URL carries filter state. Keep the exact URL param names.

- [ ] **Step 3:** `pushState` is called **exactly once** per action, inside `request().done`, and only when `updateUrl`. Remove the two extra `pushState` calls from the old `all_param`.

- [ ] **Step 4: Build + verify**

Run: `yarn build` (root) → `dist/js/load_more_and_filter.js` regenerated.
Manual (single instance): load-more, list/both pagination click, filter button, select filter, clear, order change, back/forward button (one history entry per action now), and a page with `update_url="false"` (no URL change, links are `#`, still works). Appendix A rows 13–24.

- [ ] **Step 5: Commit**

```bash
git add src/js/load_more_and_filter.js dist/js/load_more_and_filter.js
git commit -m "refactor: per-instance JS controller; single pushState; stale-response guard; honour update_url"
```

---

### Task 5.2: Drop dead payload; keep `loadmore_params` shape

**Files:**
- Modify: `inc/class-load-more.php:33-38` (localize), `src/js/load_more_and_filter.js`
- Build: root `yarn build`

- [ ] **Step 1:** In `buildData()` do **not** include `query`. Keep sending `post_type, posts_per_page, pagination_type, category, category_id, category_taxonomy, search, order, orderby, more_classes, more_label, prev_text, next_text, base_url, filter_id, update_url, archive_context, page, action`.
- [ ] **Step 2:** Leave `wp_localize_script` `loadmore_params` keys as they are (`ajaxurl`, `posts`, `current_page`, `max_page`) for theme back-compat, but the JS no longer reads `posts`/`max_page`.
- [ ] **Step 3: Verify** — network tab: request body no longer carries the multi-KB `query` blob; list still loads.
- [ ] **Step 4: Commit**

```bash
git add src/js/load_more_and_filter.js dist/js/load_more_and_filter.js
git commit -m "perf: stop posting the unused main-query payload"
```

---

## Phase 6 — Multi-instance support

### Task 6.1: Two `[all_posts_ajax]` + two filter panels on one page

**Files:**
- Modify: `src/js/load_more_and_filter.js` (scope every selector via `this.$holder` / `this.$filters`; never bare `$('#id')`)
- Modify: `inc/class-pagination.php` (`id='pagination_holder'` → keep id but add `class='pagination_holder'`; JS scopes by `.closest('.ajax_row_holder')`)
- Build: root `yarn build`

- [ ] **Step 1:** `WRALM_Pagination::links()` — every `id='pagination_holder'` / `id="pagination_holder"` becomes `class='pagination_holder load_more_holder'` and keeps `id='pagination_holder'` only when a `render` flag says single-instance is fine; simpler: drop the `id`, add `class='pagination_holder'`, and update the CSS-hook note in README (the id was never a documented API). Delegated JS handler: `$(document).on('click', '.pagination_holder .load_page', ...)` then `var inst = instances.get($(this).closest('.ajax_row_holder')[0])`.
- [ ] **Step 2:** Maintain a `WeakMap`/array of instances keyed by holder element so delegated handlers find the right controller.
- [ ] **Step 3:** `buildData()` sends `filter_id` (from `this.filterId`); server already parses it (Task 1.2). No server query change needed — `filter_id` is only a client routing key.
- [ ] **Step 4: Verify** — build a page with:
  ```
  [all_posts_ajax post_type="post" filter_id="a"]
  [all_posts_ajax_filters post_type="post" filter_by_category="true" filter_id="a"]
  [all_posts_ajax post_type="page" filter_id="b"]
  [all_posts_ajax_filters post_type="page" enable_search="true" filter_id="b"]
  ```
  Filtering panel `a` updates only list `a`; searching panel `b` updates only list `b`; pagination independent. Appendix A rows 25–28.
- [ ] **Step 5: Commit**

```bash
git add src/js/load_more_and_filter.js dist/js/load_more_and_filter.js inc/class-pagination.php
git commit -m "feat: multi-instance support via filter_id-scoped JS controllers"
```

---

## Phase 7 — Search hardening (`inc/class-search-acf.php`)

### Task 7.1: Scope the `posts_search` filter to our queries

**Files:**
- Modify: `inc/class-search-acf.php:63-70`

- [ ] **Step 1:** At the top of `extend_search_query`:
```php
if ( is_admin() && ! wp_doing_ajax() ) {
    return $where;
}
$is_ours       = ! empty( $wp_query->query_vars['wralm_search'] );
$allow_global  = apply_filters( 'wralm_extend_all_search', false );
if ( ! $is_ours && ! $allow_global ) {
    return $where;
}
```
The `wralm_search` flag is set by `WRALM_Query_Config::wp_query_args()` (Task 1.2). Global opt-in stays available via the filter — nothing removed, just off by default.

- [ ] **Step 2: Verify** — a normal theme search form and the WP admin post search are no longer rewritten (log the `$where` or check `SAVEQUERIES`); the shortcode search still matches ACF values.

- [ ] **Step 3: Commit**

```bash
git add inc/class-search-acf.php
git commit -m "fix: scope ACF search extension to WRALM queries (opt-in global via filter)"
```

---

### Task 7.2: Never full-scan `wp_postmeta`

**Files:**
- Modify: `inc/class-search-acf.php:88-138`

- [ ] **Step 1:** Build `$base_sql` in parts. The postmeta `EXISTS` block is **only** appended when `$has_acf` is true, and always with `meta_key IN (%s,%s,...)`:
```php
$parts = array(
    "({$wpdb->posts}.post_title LIKE %s)",
    "({$wpdb->posts}.post_content LIKE %s)",
);
$per_term_params_shape = array( 'title', 'content' );

if ( $has_acf ) {
    $acf_ph = implode( ',', array_fill( 0, count( $acf_fields ), '%s' ) );
    $parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key IN ({$acf_ph}) AND meta_value LIKE %s )";
    $per_term_params_shape[] = 'acf_keys';
    $per_term_params_shape[] = 'meta_value';
}

$parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->comments} WHERE comment_post_ID = {$wpdb->posts}.ID AND comment_content LIKE %s )";
$per_term_params_shape[] = 'comment';

$parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id = {$wpdb->posts}.ID AND tt.taxonomy IN ({$tax_placeholders}) AND t.name LIKE %s )";
$per_term_params_shape[] = 'taxonomies';
$per_term_params_shape[] = 'term_name';

$base_sql = implode( ' OR ', $parts );
```
Then build `$params` per term by walking `$per_term_params_shape` (`title`/`content`/`meta_value`/`comment`/`term_name` → `$like`; `acf_keys` → spread `$acf_fields`; `taxonomies` → spread `$taxonomies`). This keeps the existing `$wpdb->prepare( " AND ({$base_sql})", $params )` per-term loop.

- [ ] **Step 2: Verify** — with zero ACF field groups: searching a number that equals some `_price` no longer matches products by price; title/content/term/comment search still works. With ACF fields present: ACF value search works and the query has a bounded `meta_key IN (...)`.

- [ ] **Step 3: Commit**

```bash
git add inc/class-search-acf.php
git commit -m "fix: bound ACF meta search to known keys; drop unfiltered postmeta scan"
```

---

### Task 7.3: Bootstrap the ACF field cache; include `post`

**Files:**
- Modify: `inc/class-search-acf.php`, `wr-ajax-load-more-and-filters.php:45`

- [ ] **Step 1:** In `refresh_searchable_fields`, change the post-type source from `get_post_types( ['public' => true, '_builtin' => false] )` to `get_post_types( ['public' => true] )` so `post`/`page` ACF fields are indexed too.
- [ ] **Step 2:** Add:
```php
public function __construct() {
    add_action( 'acf/save_post', array( $this, 'refresh_searchable_fields' ), 20 );
    add_filter( 'posts_search', array( $this, 'extend_search_query' ), 500, 2 );
    add_action( 'admin_init', array( $this, 'maybe_bootstrap' ) );
}

public function maybe_bootstrap() {
    if ( false !== get_option( self::OPTION_NAME, false ) ) {
        return;
    }
    if ( ! function_exists( 'acf_get_field_groups' ) ) {
        return;
    }
    $this->rebuild_all(); // extracted body of refresh_searchable_fields sans the post-type guard
}
```
Extract the field-collection body into `rebuild_all()` and have both `maybe_bootstrap()` and `refresh_searchable_fields()` (after its `acf-field-group` guard) call it.
- [ ] **Step 3:** Activation hook — in `wr-ajax-load-more-and-filters.php` add a second activation callback that calls `( new WRALM_Search_ACF() )->maybe_bootstrap()` is not available pre-admin_init; instead register `register_activation_hook( __FILE__, function () { delete_option( 'wralm_searchable_acf_fields' ); } )` so the next `admin_init` rebuilds. Keep it simple.
- [ ] **Step 4: Verify** — fresh install (delete the option), visit wp-admin once, confirm `wralm_searchable_acf_fields` is populated (and autoloaded).
- [ ] **Step 5: Commit**

```bash
git add inc/class-search-acf.php wr-ajax-load-more-and-filters.php
git commit -m "fix: bootstrap ACF searchable-field cache on admin_init; index post/page fields"
```

---

## Phase 8 — Sorting (`orderby`)

### Task 8.1: `orderby` through the DTO + order view + JS

**Files:**
- Modify: `inc/class-query-config.php` (already whitelisted + mapped in Phase 1 — verify), `inc/class-filter-config.php`, `inc/views/order.php`, `src/js/load_more_and_filter.js`
- Build: root `yarn build`

- [ ] **Step 1:** `WRALM_Filter_Config` accepts `order_by_options` (csv, subset of `WRALM_Query_Config::ORDERBY_WHITELIST`) and `order_by_labels` (csv, parallel). Default empty → the new select is not rendered (existing direction-only behaviour preserved).
- [ ] **Step 2:** `order.php` — after the existing direction `<select>`, add:
```php
<?php $opts = array_filter( array_map( 'trim', explode( ',', $load_more_variables['order_by_options'] ?? '' ) ) ); ?>
<?php if ( $opts ) : ?>
    <div>
        <select class="js-post-orderby" data-filter-id="<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>">
            <?php foreach ( $opts as $i => $key ) : ?>
                <option value="<?= esc_attr( $key ) ?>"><?= esc_html( $labels[ $i ] ?? ucfirst( $key ) ) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
```
- [ ] **Step 3:** JS `Instance` — read `this.$orderby.val()` into `buildData().orderby` (fallback `this.$row.data('orderby') || 'date'`); bind `change` on `.js-post-orderby` to the same submit path as the direction select.
- [ ] **Step 4: Verify** — `[all_posts_ajax_filters enable_order="true" order_by_options="date,title,menu_order"]` renders two selects; changing "Order by" re-queries; `price`/`popularity` only meaningful for products (Phase 9). Direction-only config unchanged.
- [ ] **Step 5: Commit**

```bash
git add inc/class-filter-config.php inc/views/order.php src/js/load_more_and_filter.js dist/js/load_more_and_filter.js
git commit -m "feat: optional orderby select (date/title/menu_order/...) alongside direction"
```

---

## Phase 9 — WooCommerce

### Task 9.1: `WRALM_Woo` helper

**Files:**
- Create: `inc/class-woo.php`
- Modify: `wr-ajax-load-more-and-filters.php` (require)

**Interfaces — Produces:**
```php
class WRALM_Woo {
    public static function active(): bool;                       // class_exists('WooCommerce')
    public static function is_product_query( $post_type ): bool;  // active() && post_type === 'product'
    public static function visibility_tax_query(): array;         // exclude 'exclude-from-catalog' (+ 'outofstock' if store hides it)
    public static function orderby_args( string $orderby, string $order ): array; // price/popularity/rating/menu_order → WP_Query args
    public static function setup_loop( WP_Query $q ): void;       // wc_setup_loop + wc_set_loop_prop
    public static function reset_loop(): void;                    // wc_reset_loop
}
```

- [ ] **Step 1: Write the class**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WRALM_Woo {

    public static function active() {
        return class_exists( 'WooCommerce' );
    }

    public static function is_product_query( $post_type ) {
        return self::active() && 'product' === $post_type;
    }

    public static function visibility_tax_query() {
        if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
            return array();
        }
        $ids  = wc_get_product_visibility_term_ids();
        $excl = array();
        if ( ! empty( $ids['exclude-from-catalog'] ) ) {
            $excl[] = (int) $ids['exclude-from-catalog'];
        }
        if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! empty( $ids['outofstock'] ) ) {
            $excl[] = (int) $ids['outofstock'];
        }
        if ( ! $excl ) {
            return array();
        }
        return array(
            'taxonomy' => 'product_visibility',
            'field'    => 'term_taxonomy_id',
            'terms'    => $excl,
            'operator' => 'NOT IN',
        );
    }

    public static function orderby_args( $orderby, $order ) {
        switch ( $orderby ) {
            case 'price':
                return array( 'orderby' => 'meta_value_num', 'meta_key' => '_price', 'order' => $order );
            case 'popularity':
                return array( 'orderby' => 'meta_value_num', 'meta_key' => 'total_sales', 'order' => 'DESC' );
            case 'rating':
                return array( 'orderby' => 'meta_value_num', 'meta_key' => '_wc_average_rating', 'order' => 'DESC' );
            case 'menu_order':
                return array( 'orderby' => 'menu_order title', 'order' => $order );
            case 'title':
            case 'date':
            case 'rand':
            case 'modified':
            case 'comment_count':
                return array( 'orderby' => $orderby, 'order' => $order );
            default:
                return array( 'orderby' => 'date', 'order' => $order );
        }
    }

    public static function setup_loop( WP_Query $q ) {
        if ( function_exists( 'wc_setup_loop' ) ) {
            wc_setup_loop( array(
                'is_shortcode' => true,
                'is_paginated' => true,
                'total'        => (int) $q->found_posts,
                'total_pages'  => (int) $q->max_num_pages,
                'per_page'     => (int) $q->get( 'posts_per_page' ),
                'current_page' => max( 1, (int) $q->get( 'paged' ) ),
            ) );
        }
    }

    public static function reset_loop() {
        if ( function_exists( 'wc_reset_loop' ) ) {
            wc_reset_loop();
        }
    }
}
```

- [ ] **Step 2:** `require_once WRALM_PATH . 'inc/class-woo.php';` in the bootstrap (before `class-query-config.php` so `class_exists('WRALM_Woo')` guards resolve true).

- [ ] **Step 3: Verify** — `php -l inc/class-woo.php`. On a store: `WRALM_Woo::visibility_tax_query()` returns a `NOT IN` clause; with WooCommerce inactive every method is a safe no-op / false.

- [ ] **Step 4: Commit**

```bash
git add inc/class-woo.php wr-ajax-load-more-and-filters.php
git commit -m "feat: WRALM_Woo helper (visibility tax_query, orderby, loop setup)"
```

---

### Task 9.2: Wire Woo into the query + confirm loop context

**Files:** none new — Phase 1 already added the `class_exists('WRALM_Woo')` call sites in `WRALM_Query_Config` and both render paths. This task is verification + fixes if the wiring is off.

- [ ] **Step 1: Verify** on a WooCommerce install with `[all_posts_ajax post_type="product" posts_per_page="6"]` on a plain page and `all_posts_ajax/product-card.php` calling `wc_get_template_part( 'content', 'product' )`:
  - Catalog-hidden products absent; out-of-stock absent when the store hides them.
  - `$product` global set inside the card; `wc_get_loop_prop( 'total' )` non-null.
  - `[all_posts_ajax_filters post_type="product" filter_by_category="true" filter_taxonomy="product_cat" enable_order="true" order_by_options="price,popularity,rating,date"]` — category filter, price/popularity/rating sort all work through AJAX.
  - Pagination (`default`/`list`/`both`) works; `update_url="false"` variant works.
- [ ] **Step 2:** If `wc_setup_loop` needs `found_posts` before the loop, ensure `WP_Query` ran with `no_found_rows = false` (it does — DTO default).
- [ ] **Step 3:** Record results in `docs/superpowers/plans/notes-phase9.md`.
- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/notes-phase9.md
git commit -m "docs: WooCommerce verification matrix"
```

---

### Task 9.3: `wp_count_posts` / term counts exclude hidden

**Files:**
- Modify: `inc/views/filter.php` ("All" count), add helper to `inc/class-filter-config.php`

- [ ] **Step 1:** Add `WRALM_Filter_Config::visible_count( $post_type )` — a cached (`wp_cache` / 5-min transient keyed by post type + taxonomy filters) `WP_Query` with `fields => 'ids'`, `posts_per_page => 1`, the hide-meta query, and (for products) the visibility tax query; return `found_posts`.
- [ ] **Step 2:** `filter.php` line ~39 `wp_count_posts(...)->publish` → `<?= (int) $config->visible_count( $load_more_variables['post_type'] ) ?>` (fallback to `wp_count_posts` when `$config` absent).
- [ ] **Step 3:** Per-term `$category->count` — leave as WP's count (documented WP behaviour, changing it is expensive); add a README note that term counts are raw taxonomy counts.
- [ ] **Step 4: Verify** — mark a post "Hide from list"; the "All (N)" count drops by one; term counts unchanged (documented).
- [ ] **Step 5: Commit**

```bash
git add inc/views/filter.php inc/class-filter-config.php
git commit -m "fix: 'All' filter count excludes hidden / catalog-invisible posts"
```

---

## Phase 10 — Security

### Task 10.1: Nonce plumbing (soft by default, hard opt-in)

**Files:**
- Modify: `inc/class-load-more.php` (localize + `maybe_check_nonce`), `src/js/load_more_and_filter.js` (`buildData` sends `nonce`)
- Build: root `yarn build`

- [ ] **Step 1:** `wp_localize_script` adds `'nonce' => wp_create_nonce( 'wralm_loadmore' )`.
- [ ] **Step 2:**
```php
private function maybe_check_nonce() {
    $valid = isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'wralm_loadmore' );
    if ( ! $valid && apply_filters( 'wralm_require_nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'bad nonce' ), 403 );
    }
}
```
Soft by default (page-cache plugins serve stale nonces to logged-out users; the endpoint is read-only public data). Sites that want enforcement add one filter.
- [ ] **Step 3:** JS `buildData()` includes `nonce: params.nonce`.
- [ ] **Step 4: Verify** — default: requests succeed with and without a valid nonce. With `add_filter( 'wralm_require_nonce', '__return_true' )`: a forged/absent nonce → 403, real UI still works.
- [ ] **Step 5: Commit**

```bash
git add inc/class-load-more.php src/js/load_more_and_filter.js dist/js/load_more_and_filter.js
git commit -m "feat: nonce on loadmore endpoint (soft; hard via wralm_require_nonce filter)"
```

---

### Task 10.2: Confirm input whitelists

**Files:** none (Phase 1 added `post_type_exists`/`is_post_type_viewable` and the `orderby` whitelist in `from_request`). Verification-only.

- [ ] **Step 1: Verify** — POST `post_type=revision` / `post_type=attachment` / unknown → handler queries `post`. POST `orderby=' OR 1=1` → falls back to `date`. POST `order=RAND` → `DESC`.
- [ ] **Step 2:** Note results in `notes-phase10.md`; commit.

---

## Phase 11 — Admin console (frontend/)

### Task 11.1: `update_url` toggle

**Files:**
- Modify: `frontend/src/types/index.ts`, `frontend/src/hooks/useShortcodeBuilder.ts`, `frontend/src/components/Tabs/MainTab.tsx`
- Build: `cd frontend && yarn build`

- [ ] **Step 1:** `MainSettings` add `updateUrl: boolean`. Default `true` in `defaultMain`.
- [ ] **Step 2:** `useShortcodeBuilder` `postsShortcode` — emit only when off: `if (!main.updateUrl) sc += ' update_url="false"';` (the `attr()` helper skips falsey, and we never want to print `update_url="true"` noise).
- [ ] **Step 3:** `MainTab` — add a `Toggle` (import like `OrderTab`): label "Sync filters to URL", `value={main.updateUrl}`, `onChange={(v) => update('updateUrl', v)}`. Helper text: "Off = no address-bar changes, pagination links are inert anchors."
- [ ] **Step 4: Verify** — `yarn lint`, `yarn build`. Toggle off → shortcode shows `update_url="false"`; on → attribute absent.
- [ ] **Step 5: Commit**

```bash
git add frontend/src dist/app.js dist/style.css
git commit -m "feat(console): update_url toggle in Main tab"
```

---

### Task 11.2: `orderby` options in the Order tab

**Files:**
- Modify: `frontend/src/types/index.ts`, `frontend/src/hooks/useShortcodeBuilder.ts`, `frontend/src/components/Tabs/OrderTab.tsx`
- Build: `cd frontend && yarn build`

- [ ] **Step 1:** `OrderSettings` add `orderByOptions: string[]` (default `[]`) and `orderByLabels: string` (optional, default `''`).
- [ ] **Step 2:** Order-by keys constant: `['date','title','menu_order','rand','price','popularity','rating']`. Render a checkbox group (reuse `Toggle` per key, or a multi `Select` if it supports it). `price`/`popularity`/`rating` labelled "(WooCommerce)".
- [ ] **Step 3:** `useShortcodeBuilder` `filtersShortcode` order block — when `order.orderByOptions.length` add `attr('order_by_options', order.orderByOptions.join(','))` and `attr('order_by_labels', order.orderByLabels)`.
- [ ] **Step 4: Verify** — `yarn lint`, `yarn build`. Selecting keys adds `order_by_options="..."` to the filters shortcode; `enable_order` still governs the direction select.
- [ ] **Step 5: Commit**

```bash
git add frontend/src dist/app.js dist/style.css
git commit -m "feat(console): orderby option picker in Order tab"
```

---

### Task 11.3: WooCommerce hint (no functional toggle needed)

**Files:**
- Modify: `frontend/src/components/Tabs/MainTab.tsx`

- [ ] **Step 1:** When `main.postType === 'product'`, show an info note under the Post Type select: "WooCommerce detected on the server: catalog-hidden / out-of-stock products are excluded automatically; use the Order tab for price / popularity / rating sorting." No new attribute — the PHP side auto-detects.
- [ ] **Step 2: Verify** — `yarn lint`, `yarn build`; note appears only for `product`.
- [ ] **Step 3: Commit**

```bash
git add frontend/src dist/app.js dist/style.css
git commit -m "docs(console): WooCommerce behaviour hint for product post type"
```

---

## Phase 12 — Version, build, docs, release

### Task 12.1: Version bump to 1.5.0 (all 4 locations)

**Files:**
- Modify: `wr-ajax-load-more-and-filters.php:6` (`Version:`), `:20` (`WRALM_VERSION`), `package.json`, `frontend/package.json`

- [ ] **Step 1:** Set all four to `1.5.0`.
- [ ] **Step 2: Verify:**
```bash
grep -n "1\.5\.0" wr-ajax-load-more-and-filters.php package.json frontend/package.json
```
Expected: header line, `WRALM_VERSION` line, both `"version"` lines.
- [ ] **Step 3: Commit**

```bash
git add wr-ajax-load-more-and-filters.php package.json frontend/package.json
git commit -m "chore: bump version to 1.5.0"
```

---

### Task 12.2: Rebuild both bundles + commit artifacts

**Files:**
- Build: root `yarn install && yarn build`; `cd frontend && yarn install && yarn build`
- Modify (generated): `dist/js/load_more_and_filter.js`, `dist/app.js`, `dist/style.css`, `template-parts/sprite.php`, `frontend/src/components/sharedComponents/Icon/sprite-info.ts`

- [ ] **Step 1:** Run both builds clean.
- [ ] **Step 2: Verify:** `git status` shows only regenerated artifacts; `php -l` on every touched PHP file passes; `cd frontend && yarn lint` clean.
- [ ] **Step 3: Commit**

```bash
git add dist template-parts/sprite.php frontend/src/components/sharedComponents/Icon/sprite-info.ts
git commit -m "build: rebuild bundles for 1.5.0"
```

---

### Task 12.3: Docs

**Files:**
- Modify: `README.md`, `CLAUDE.md`

- [ ] **Step 1: README** — add `update_url` and `orderby` / `order_by_options` / `order_by_labels` to the parameter list; add a "Multiple instances" section (`filter_id` links a list + a filter panel; use distinct `filter_id` per pair); add a "WooCommerce" section (auto visibility exclusion, `wc_setup_loop`, price/popularity/rating sort); note pagination uses `?paged=N` off archives and pretty URLs only on archive contexts with `update_url` on.
- [ ] **Step 2: CLAUDE.md** — under "Архітектура PHP" document `WRALM_Query_Config` (single source of truth: `from_atts` / `from_request` / `wp_query_args` / `data_attrs` / `pagination_args`), `WRALM_Filter_Config` (replaces the `$load_more_variables` construction; global still set for back-compat), `WRALM_Woo`. Update the `[all_posts_ajax]` bullet: config now flows through the DTO, `data-filter-id` always present. Note `WRALM_Pagination::resolve_base()`.
- [ ] **Step 3: Verify** — proof-read; `grep -n update_url README.md CLAUDE.md`.
- [ ] **Step 4: Commit**

```bash
git add README.md CLAUDE.md
git commit -m "docs: DTO architecture, update_url, orderby, multi-instance, WooCommerce"
```

---

### Task 12.4: Final full regression + merge

- [ ] **Step 1:** Run the entire Appendix A checklist on a WP + WooCommerce install (classic + block themes if available).
- [ ] **Step 2:** Confirm the CI `.github/workflows/build.yml` still packages (dry-run `yarn package:fast` locally; `<slug>_1.5.0.zip` produced).
- [ ] **Step 3:** Open PR `fix/wralm-1.5.0` → `main`. Body: summary of fixes grouped by area, the new `update_url` attribute, backward-compat statement. On merge, CI tags `v1.5.0`.
- [ ] **Step 4:** Per superpowers:finishing-a-development-branch, decide squash vs merge with the user.

---

## Appendix A — Manual regression checklist

Run in a local WP install. Rows marked (WC) need WooCommerce + a product card template.

| # | Scenario | Expected |
|---|---|---|
| 1 | `[all_posts_ajax]` default, 25 posts, `posts_per_page=10` | 10 cards + "Show more" |
| 2 | Click "Show more" | next 10 appended, button advances |
| 3 | `type_pagination="list"` | numbered links, click loads page (replaces) |
| 4 | `type_pagination="both"` | numbers + load-more |
| 5 | `type_pagination="none"` | no pagination markup |
| 6 | Shortcode on a **static page** | pagination links are `?paged=2`, no 404 |
| 7 | Shortcode on a category archive | pretty `/category/x/page/2/` when `update_url` on + permalinks |
| 8 | Front page static | page 1 correct, load-more works |
| 9 | `posts_per_page="-1"` | all posts, no pagination, no error |
| 10 | Logged-in admin vs logged-out | same visible count (both `publish`) |
| 11 | Post marked "Hide from list" | absent from list; "All (N)" count −1 |
| 12 | Empty `page` in a forged POST | returns page 1, highlighted |
| 13 | Filter button, `multiply_filter="false"` | single active term, list narrows |
| 14 | Filter button, `multiply_filter="true"` | multiple active terms OR-ed |
| 15 | Child-category button | request sends `category[tax][]=child`, list narrows |
| 16 | Grandchild term | rendered and filterable |
| 17 | `filter_type="select"` | placeholder = taxonomy label, nested options indented |
| 18 | `filter_item_limit="3"` with 10 terms | 3 shown, "See all" reveals rest |
| 19 | Clear button | all terms deactivated, search cleared, list resets |
| 20 | Search box | matches title/content/term/comment |
| 21 | Search with ACF field groups present | matches ACF values; bounded `meta_key IN` |
| 22 | Order direction Newest/Old | list re-sorts by date |
| 23 | `order_by_options="date,title"` | second select re-sorts |
| 24 | `update_url="false"` | no address-bar change, links `href="#"`, all still works |
| 25 | Back button after 3 filter actions | 3 history entries, each restores state |
| 26 | Two `[all_posts_ajax]` + two filter panels, distinct `filter_id` | panels drive their own list only |
| 27 | Rapid filter clicks (5 in <1s) | final list matches last click (stale responses dropped) |
| 28 | `?category=news&filter_search=x` deep link | state restored, one request, one history entry |
| 29 | (WC) `[all_posts_ajax post_type="product"]` | catalog-hidden + out-of-stock excluded |
| 30 | (WC) `$product` global inside product card | set; `wc_get_loop_prop('total')` non-null |
| 31 | (WC) `order_by_options="price,popularity,rating"` | each re-sorts correctly via AJAX |
| 32 | (WC) `filter_taxonomy="product_cat"` | product category filter works |
| 33 | Theme search form (not the shortcode) | NOT rewritten by ACF search filter |
| 34 | `wp-admin` product search | NOT rewritten |
| 35 | `add_filter('wralm_require_nonce','__return_true')` + forged nonce | 403; real UI works |

## Self-review

- **Spec coverage:** A→`WRALM_Query_Config`/`WRALM_Filter_Config` (P1); B→P1.4/1.5 (`filter_id`, clear-button, `filter_expand`, `post_status`), P6 (multi-instance); C→P1.2/1.3/2 + `update_url` P3; D→P4; E→P7; F→P8; G→P9; H→P5; I→P10; new `update_url` feature→P1+P3+P5+P11.1. All covered.
- **Placeholder scan:** the recursive-renderer, DTO, `WRALM_Woo`, and search-SQL rebuild are given as full code; view/JS edits give exact old→new. No "TBD"/"handle edge cases".
- **Type consistency:** `WRALM_Query_Config` property + method names identical across P1, P8, P9, P10. `WRALM_Woo` signature identical between P1 call sites (`class_exists` guard) and P9 definition. `data-*` keys identical between `data_attrs()` (P1.1) and JS `Instance` readers (P5.1). `filter_id` default rule (`post_type . '_filter'`) identical in both DTOs.
