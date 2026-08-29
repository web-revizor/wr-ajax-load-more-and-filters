# WRALM — Fixes & Improvements Spec

**Date:** 2026-08-29
**Plugin:** Web Revizor: Ajax Load More & Filters
**Baseline version:** header `1.4.0`, `package.json` `1.3.1` (out of sync — must be fixed)
**Target version:** `1.5.0` (single value in all 4 locations)

## Guiding rule

**Nothing is removed from the public feature set.** Every existing shortcode
attribute, every JS event (`AjaxPaginationDone`, `AjaxFilterDone`), every
pagination mode (`default` / `list` / `both` / `none`), every filter mode
(`button` / `select`), search, ACF search, and order stay working and
backward-compatible. Existing shortcodes on live sites must render and behave
the same unless the site owner opts into new behaviour via a new attribute.

## Problem inventory (from audit)

### A. Config duplication / no DTO
The list-query parameter set is hand-mirrored across 6 places with divergent
key names (`type_pagination` vs `pagination_type`, `load_more_classes` vs
`more_classes`). The `$pagenum_link` + `$format` computation is copy-pasted
verbatim 3×. `render_posts` unpacks every `shortcode_atts` key into a local
variable for no reason.

### B. Shortcode / multi-instance
- `filter_id` is written to `data-filter-id` but **never read by JS**. All JS
  selectors are global (`.ajax_row`, `#all_posts_filter`, `#all-post-search`,
  `#js-post-order`, `#pagination_holder`). Two `[all_posts_ajax]` blocks (or
  two filter panels) on one page collide completely.
- `filter.php` is included only when `filter_by_category` or `enable_search`
  is on — `enable_clear_button` alone renders nothing.
- `filter_expand_class` default is the literal string `filter_expand_class`.
- `render_posts` omits `post_status`; AJAX handler forces `publish` → logged-in
  users see different sets before vs after AJAX.

### C. Pagination
- AJAX `paged` uses `absint($_POST['page'])` → empty string becomes `0`, which
  breaks current-page highlight and prev/next off-by-one.
- Real `/page/N/` URLs are emitted even when the shortcode sits on a static
  page (not an archive), where WordPress treats `/page/N/` as `<!--nextpage-->`
  pagination → 404 / canonical redirect.
- `WRALM_Pagination::links()` calls `get_pagenum_link()`, `get_query_var()`,
  `$GLOBALS['wp_query']->max_num_pages` — all meaningless during
  `wp_ajax_loadmore`; the `add_args` merge (existing query string such as
  `?filter_search=`) is computed from the wrong URL and lost.
- `render_posts` derives `paged` from the page's main query, not the list's
  own state; on the front page WP uses `page`, not `paged`.
- `posts_per_page = -1` is not guarded.

### D. Filtering
- Child-category **buttons** have `data-slug` but **no `data-taxonomy`** →
  JS builds `category["undefined"] = [slug]` → child filtering is dead in
  `button` mode.
- Archive term (`data-cat-id` / `data-cat-taxonomy`) is `AND`-ed with the
  user's tax filters at `relation = AND`; filtering by another term of the
  same taxonomy on an archive page yields zero results.
- `get_terms( $taxonomy, $args )` uses the pre-4.5 signature (`_doing_it_wrong`
  on modern WP). 4 call sites in `filter.php`.
- `get_queried_object()->term_id` is read unconditionally in `filter.php`; on
  non-archive pages the object is a `WP_Post`/`null` → PHP 8 warning.
- `filter_item_limit` counting: the taxonomy total is added once before the
  term loop, children add to the count but never to `$index`, so the
  `hidden` class and the "See all" toggle mis-fire.
- Only one level of category nesting is walked.
- `wp_count_posts()->publish` and `$term->count` include hidden
  ("Hide from list") and Woo-invisible posts → the "All (N)" / per-term
  counters disagree with the actual result set.

### E. Search (`WRALM_Search_ACF`)
- `posts_search` filter is registered globally at priority 500 with the only
  guard being a non-empty `s`; it rewrites the WHERE of **every** search on
  the site (admin product search, menu search, other search forms).
- When no ACF fields are cached, the postmeta `EXISTS` sub-query runs with
  **no `meta_key` filter** → full `wp_postmeta` scan, matching internal keys
  (`_edit_lock`, `_price`, `_sku`, `total_sales`, …).
- The cache option `wralm_searchable_acf_fields` is only ever built on
  `acf/save_post` of a field group — empty on a fresh install until someone
  re-saves every group. No activation / bootstrap build.
- Only `_builtin => false` post types are scanned → ACF fields on `post` are
  never searchable.
- Each search word becomes its own `AND (...)` sub-query — N words = N × the
  full title/content/meta/comment/term scan.

### F. Sorting
- Only `order` = `ASC` / `DESC` on date. No `orderby` (title, `menu_order`,
  `rand`, and — for Woo — `price` / `popularity` / `rating`).

### G. WooCommerce
- No query respects the `product_visibility` taxonomy → catalog-hidden and
  (when the store hides them) out-of-stock products appear in
  `[all_posts_ajax post_type="product"]`.
- Loops are not wrapped in `wc_setup_loop()` / `wc_reset_loop()`, so loop
  props / counters / `wc_get_loop_prop()` are undefined inside
  `all_posts_ajax/product-card.php`.
- `loop_shop_per_page` and the catalog ordering args are ignored.

### H. JS
- `$form.submit` calls `all_param()` with no `url` → `pushState(null,null,undefined)`.
- 2–3 `history.pushState` calls per filter action → back button needs many presses.
- No debounce, no in-flight abort → out-of-order AJAX responses; last response
  to land wins, not the last requested.
- `loadmore_params.posts` (the page main query's `query_vars`, 5–20 KB) is
  posted as `query` on every request and **never read** by the handler.
  `loadmore_params.max_page` is unused.

### I. Security
- `wp_ajax_nopriv_loadmore` has no nonce.
- `post_type` from `$_POST` is only `sanitize_key`-ed → any registered post
  type can be queried (limited to `post_status = publish`).
- `order` is passed through from `$_POST` unchecked (WP ignores invalid, but
  `orderby` will need a whitelist once added).

## New feature: optional real URLs

New shortcode attribute on `[all_posts_ajax]`:

```
update_url = "true" | "false"   (default "true")
```

- `"true"` (default): current behaviour preserved — pagination links carry
  real `href`s and the script syncs filter/search/page state into the address
  bar via `history.pushState`. Additionally, when the shortcode is not on an
  archive/home context, the paginated link **format** switches to
  `?paged=%#%` so it stops producing broken `/page/N/` URLs on static pages.
- `"false"`: pagination links render with `href="#"` (navigation driven only
  by `data-page`), and the script performs **no** `history.pushState` /
  URL rewriting. State is in-memory only.

The attribute is threaded through the DTO → `data-update-url` → JS, and through
the AJAX request → `WRALM_Pagination::links()`.

## Global constraints

- PHP 8.3 (OpenServer local); code must be warning-clean on 8.1+.
- WordPress: no version bump assumed beyond 5.9 (keep `get_terms` new signature).
- Package manager: **yarn**. Two builds: root (`yarn build` →
  `dist/js/load_more_and_filter.js`) and `frontend/` (`yarn build` →
  `dist/app.js`, `dist/style.css`, `template-parts/sprite.php`,
  `frontend/src/components/sharedComponents/Icon/sprite-info.ts`).
- All generated artifacts under `dist/` and the two sprite files are
  committed.
- Version string identical in: `wr-ajax-load-more-and-filters.php` header
  `Version:`, `WRALM_VERSION` constant, `package.json`, `frontend/package.json`.
  Target: `1.5.0`.
- No automated test harness exists. Verification = `php -l`, `cd frontend &&
  yarn lint`, both `yarn build`s, and a manual checklist in a local WP +
  WooCommerce install.
- jQuery stays a WordPress global (not bundled). Admin React stays external.
- Keep the public JS events and all existing CSS class / id hooks; new
  behaviour is additive.
