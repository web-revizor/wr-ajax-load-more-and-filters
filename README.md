# Web Revizor: Ajax Load More & Filters

## WordPress plugin

Easy load more, filter and searching

[Download](https://github.com/web-revizor/ajax-load-more-and-filters/releases)

### Admin console

The shortcode-builder screen (**WR Ajax Load More** in the admin menu) is a
small React app — see `frontend/` for its source and `frontend/AGENTS.md`
for how to work on it. Run `yarn install && yarn build` inside
`frontend/` after any change there to regenerate `dist/app.js` and
`dist/style.css`, which is what the plugin actually loads.

The front-end load-more/filter script (`dist/js/load_more_and_filter.js`)
is built by a separate, plain Vite config at the plugin root (`vite.config.js`) —
run `yarn install && yarn build` from the plugin root after changing
`src/js/load_more_and_filter.js`.

### Local package

`yarn package` (or `yarn package:fast` to skip `yarn install`) runs
`build.ps1`: it builds both bundles and produces `<slug>_<version>.zip`
in the repo root, mirroring the CI workflow — the exclude list is parsed
straight out of `.github/workflows/build.yml`, the slug comes from
`gh repo view`, and the version from the plugin header. On push to
`main`, CI does the same and publishes it as a GitHub Release `v<version>`.

### Pagination:

- List
- Button
- Both
- None

### Default Shortcode

````
[all_posts_ajax post_type="post" posts_per_page="10" type_pagination="default"]
````

### Shortcode Parameters (generate in admin panel)

#### `[all_posts_ajax]` (the list + pagination)

- post_type: post type name
- posts_per_page: number, can be "-1" for infinity posts on page
- type_pagination: list/both/default/none
- row_classes: string
- load_more_label: string
- load_more_classes: string
- prev_text: string
- next_text: string
- orderby: initial sort key for the list. Whitelist:
  `date,title,menu_order,rand,modified,comment_count` plus the WooCommerce keys
  `price,popularity,rating` (default `date`). Sanitised by `from_atts`, emitted
  as `data-orderby` and read by the JS `orderbyValue()` as the fallback when the
  filter panel's order `<select>` has no value. The filter panel's
  `order_by_options` still drives the runtime sort dropdown; `orderby` only sets
  the starting value for the list itself.
- sync_filters_url: `"true"` | `"false"` (default `"true"`). When `"true"`,
  filtering / search / sorting write the current filter state to the address bar
  via `history.pushState` (params `<taxonomy>=slug`, `filter_search`). When
  `"false"`, those actions never touch the URL — filter state is in-memory only.
- sync_pagination_url: `"true"` | `"false"` (default `"true"`). When `"true"`,
  pagination `<a>` elements carry real `href`s and clicking a numbered / prev /
  next link pushes that URL into the address bar. Pretty `/page/N/` URLs are
  emitted **only** on archive / home / taxonomy contexts (and only with pretty
  permalinks); everywhere else — static pages, the front page — links are
  `?paged=N`, which fixes the broken `/page/N/` links WordPress used to 404 on
  static pages. When `"false"`, pagination hrefs are `#` (navigation driven purely
  by `data-page`) and no `history.pushState` happens. The "Show more" button
  never syncs the URL regardless (it accumulates pages; a reload would render
  only the last one).
- update_url: **deprecated** alias. When set and neither `sync_filters_url` nor
  `sync_pagination_url` is given, it applies to both (so a pre-1.5.1
  `update_url="false"` keeps its old meaning). An explicit `sync_*` attribute
  always wins.

Note: `orderby` on `[all_posts_ajax]` only sets the **initial** sort of the
list. At runtime the sort is driven by the **filters** shortcode — the Newest /
Oldest direction select and the `order_by_options` select both post their value
into the same list query.

#### `[all_posts_ajax_filters]` (the filter / search / order panel)

- filter_by_category: boolean
- filter_row_classes: string
- filter_item_classes: string
- filter_item_limit: number
- filter_expand_label: string
- filter_expand_class: string (default `filter_expand` — see breaking changes)
- filter_taxonomy: comma separated string with taxonomy name
- multiply_filter: boolean
- enable_clear_button: boolean
- filter_type: button/select
- filter_titles: boolean
- all_category_button: string
- enable_search: boolean
- label_search_button: string
- search_placeholder: string
- enable_order: boolean
- label_newest_order: string
- label_old_order: string
- order_by_options: comma-separated subset of
  `date,title,menu_order,rand,price,popularity,rating`. When empty, only the
  Newest / Oldest direction `<select>` renders. When set, a second `<select>`
  renders offering those sort keys. `price` / `popularity` / `rating` are only
  meaningful for `post_type="product"` with WooCommerce active (see WooCommerce
  section); for other post types they fall back to a sensible default.
- order_by_labels: comma-separated list, positionally parallel to
  `order_by_options`, giving the visible label for each option. A missing entry
  falls back to a prettified version of the key.

## Multiple instances

The two shortcodes are linked by the `filter_id` attribute (default
`<post_type>_filter`). The admin console writes the same `filter_id` into both
shortcodes it generates, so a `[all_posts_ajax_filters]` panel drives the
`[all_posts_ajax]` list that carries the matching `filter_id`.

To run **several independent list + filter pairs on one page**, give each pair
its own **distinct** `filter_id`:

````
[all_posts_ajax_filters post_type="post"    filter_id="blog"  filter_by_category="true"]
[all_posts_ajax          post_type="post"    filter_id="blog"  posts_per_page="6"]

[all_posts_ajax_filters post_type="product" filter_id="shop"  filter_by_category="true"]
[all_posts_ajax          post_type="product" filter_id="shop"  posts_per_page="12"]
````

The public script instantiates one controller per `.ajax_row_holder` and scopes
every selector by `filter_id`, so the panels never cross-talk. All emitted ids
are now instance-unique:

- `all_posts_filter_<filter_id>` (the `<form>`)
- `all-post-search-<filter_id>` (the search `<input>`)
- `js-post-order-<filter_id>` (the direction `<select>`)

The pagination wrapper `<div>` **no longer has an `id`** — target it by the
class `.pagination_holder` (it still also carries `.load_more_holder`).

## WooCommerce

When `post_type="product"` and WooCommerce is active, `[all_posts_ajax]`:

- Excludes catalog-hidden products automatically, and — when the store option
  "Hide out of stock items from the catalog" is on — out-of-stock products too
  (via the `product_visibility` taxonomy).
- Wraps the card loop in `wc_setup_loop()` / `wc_reset_loop()`, so
  `wc_get_loop_prop()` and the `$product` global are available inside
  `all_posts_ajax/product-card.php`.
- Makes `price` / `popularity` / `rating` real sort options — expose them
  through the Order tab (`order_by_options`) and they map to the correct
  WooCommerce meta sort.

WooCommerce's own `woocommerce_product_query` hook and `loop_shop_per_page`
filter do **not** apply to this custom query — by design. `posts_per_page` is
taken from the shortcode attribute only.

## New filters / hooks

- `wralm_query_args` — filter. `apply_filters( 'wralm_query_args', array $args, WRALM_Query_Config $config )`.
  Last chance to alter the `WP_Query` args for both the initial render and the
  AJAX handler.
- `wralm_require_nonce` — filter, default `false`. Return `true` to
  **hard-enforce** the AJAX nonce on `wp_ajax(_nopriv)_loadmore` (a bad / missing
  nonce then returns `403`). Left `false`, the nonce is checked but not required
  (soft), so existing themes keep working.
- `wralm_extend_all_search` — filter, default `false`. Return `true` to restore
  the pre-1.5.0 behaviour where the ACF search extension applied to **every**
  front-end search, not only WRALM shortcode queries.

## Upgrading to 1.5.0 — breaking changes

Existing shortcodes keep rendering and behaving the same. The following affect
themes / custom CSS / custom JS that reached into the plugin's markup:

- **Instance-suffixed ids.** Themes or scripts targeting `#all_posts_filter`,
  `#all-post-search` or `#js-post-order` must switch to the classes
  `.all_posts_form`, `.all-post-search`, `.js-post-order` (or read the
  `[data-filter-id]` attribute). Those ids are now suffixed with the
  `filter_id`.
- **`filter_expand_class` default changed** from the literal `filter_expand_class`
  to `filter_expand`. CSS targeting `.filter_expand_class` must either update to
  `.filter_expand` or pass `filter_expand_class="filter_expand_class"` explicitly
  on the shortcode.
- **Pagination wrapper lost its `id`.** The wrapper `<div>` no longer has
  `id="pagination_holder"` — target `.pagination_holder` (it still also carries
  `.load_more_holder`).
- **"All" button re-activation.** In `multiply_filter="true"` mode, deactivating
  one of several active filter buttons now re-activates the "All" button **only
  when no other filter remains active**. Previously it re-activated "All"
  alongside still-active filters.
- **ACF search is now scoped.** The site-wide `posts_search` ACF extension now
  applies **only** to WRALM shortcode queries. To restore the old global
  behaviour: `add_filter( 'wralm_extend_all_search', '__return_true' );`.
- **Per-term counts are still raw.** The count shown next to each individual
  filter button is still the raw WordPress taxonomy term count and may include
  hidden / catalog-invisible posts. Only the "All (N)" count is corrected to
  match the actual result set.
- **Initial render forces `post_status="publish"`.** The `[all_posts_ajax]`
  first (server-side) render now always queries only published posts. 1.4.0 let
  capable logged-in users see private / draft posts on page 1, while AJAX pages
  always showed only `publish` — this makes the two consistent. Use the
  `wralm_query_args` filter to restore other statuses.
- **"All (N)" count is not language-partitioned.** The count transient
  (`wralm_vcount_<post_type>`) is shared across languages, so on WPML / Polylang
  the "All (N)" number is the same in every language.

## Upgrading to 1.5.1 — `update_url` split

`update_url` is split into two independent attributes on `[all_posts_ajax]`:

- `sync_filters_url` — filtering / search / sorting → address bar.
- `sync_pagination_url` — pagination clicks → address bar, plus real vs `#`
  pagination hrefs and the pretty `/page/N/` format on archives.

Both default to `"true"`. `update_url` still works as a deprecated alias: when
set without either `sync_*` attribute it applies to both, so an existing
`update_url="false"` keeps its old meaning. An explicit `sync_*` attribute wins.

Fixes a bug where pagination never wrote the page number to the URL (the
address bar stayed identical across pages 2, 3, …). It now does when
`sync_pagination_url` is on.

The dead `data-update-url` attribute on `.ajax_filters_wrapper` and the unused
`update_url` field on `WRALM_Filter_Config` are removed.

## Known limitations

Two instances that expose the **same taxonomy** share a single `?<taxonomy>=`
URL query namespace: both restore their state from that one param on load, and
each fires its own request on load. Use different taxonomies (or
`sync_filters_url="false"` on one) if that double request is a problem.

## Deployment / post-deploy QA

After deploying, run the manual regression checklist in
`docs/superpowers/plans/2026-08-29-wralm-fixes-and-improvements.md` **Appendix A**
(35 rows; the `(WC)` rows need WooCommerce + a `product-card.php` template).

## JQuery events

- AjaxPaginationDone
- AjaxFilterDone

Both still fire on `document` after every request (unchanged public API). They
also fire on the instance's `.ajax_row_holder` element for per-instance
listeners.

### Example

````
$(document).on('AjaxPaginationDone', function() {
    //do something
});

$(document).on('AjaxFilterDone', function() {
    //do something
});
````
