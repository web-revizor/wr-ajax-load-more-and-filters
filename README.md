# Web Revizor: Ajax Load More & Filters

WordPress plugin that adds shortcodes for AJAX pagination, taxonomy filtering,
search (with ACF support) and sorting of post lists. No page reload; state is
reflected in the address bar so filtered and paginated views are shareable.

- [Releases](https://github.com/web-revizor/ajax-load-more-and-filters/releases)

## Requirements

- WordPress 5.5+
- PHP 7.4+
- Advanced Custom Fields — optional, only for searching inside ACF field values
- WooCommerce — optional, enables product-aware behaviour (see below)

## Features

- Two shortcodes: a list with pagination, and a filter/search/sort panel that
  drives it.
- Pagination styles: numbered list, "Show more" button, both, or none.
- Taxonomy filters as buttons or dropdowns, single- or multi-select, with
  optional per-term post counts.
- Search that can also match ACF field values, comment text and taxonomy term
  names (configurable).
- Two-way URL sync: filtering, sorting and pagination update the URL; Back /
  Forward and direct links restore the exact view.
- Cacheable REST endpoint (`GET /wp-json/wralm/v1/list`) with a per-IP rate
  limit.
- Multiple independent list + panel pairs on one page.

## Installation

1. Download the plugin zip from the releases page (or build it, see
   [Development](#development)).
2. Upload it in *Plugins → Add New → Upload Plugin*, or extract it into
   `wp-content/plugins/`.
3. Activate it.
4. Add a `{post_type}-card.php` template to your theme (see
   [Theme integration](#theme-integration)).

## Quick start

```
[all_posts_ajax_filters post_type="post" filter_by_category="true" enable_search="true"]
[all_posts_ajax post_type="post" posts_per_page="10" type_pagination="list"]
```

The panel and the list are linked by `filter_id` (default `<post_type>_filter`),
so a bare pair like the one above works without setting it explicitly. The admin
screen *WR Ajax Load More* generates both shortcode strings for you.

## Shortcodes

### `[all_posts_ajax]` — the list and its pagination

| Attribute | Default | Description |
|---|---|---|
| `post_type` | `post` | Post type to list. |
| `posts_per_page` | `10` | Number of posts per page. `-1` for all. Ignored for `product` (see [WooCommerce](#woocommerce)). |
| `type_pagination` | `default` | `list`, `both`, `default` (Show more button), or `none`. |
| `row_classes` | — | Extra classes appended to `.wr-posts__list`. |
| `load_more_label` | `Show more` | "Show more" button text. |
| `load_more_classes` | `load_more_button` | Extra classes on the "Show more" button. |
| `prev_text` / `next_text` | `Previous` / `Next` | Prev/next link text. |
| `sync_filters_url` | `true` | Write filter/search/sort state to the URL (`filter_<taxonomy>=slug`, `filter_search`, `sort`). `false` keeps that state in memory only. |
| `sync_pagination_url` | `true` | Give pagination links real `href`s and push the page into the URL. Pretty `/page/N/` is used only on archive/home/taxonomy contexts with pretty permalinks; elsewhere `?paged=N`. `false` makes hrefs `#`. The "Show more" button never syncs the URL. |
| `filter_id` | `<post_type>_filter` | Links this list to the panel with the same value. |

### `[all_posts_ajax_filters]` — the filter / search / sort panel

| Attribute | Default | Description |
|---|---|---|
| `post_type` | `post` | Must match the list. |
| `filter_by_category` | `false` | Render taxonomy filters. |
| `filter_taxonomy` | `category` | Comma-separated taxonomy names. |
| `filter_type` | `button` | `button` or `select`. |
| `multiply_filter` | `false` | `true` allows selecting several terms at once. |
| `filter_titles` | `false` | Print a heading before each taxonomy's terms. |
| `filter_item_limit` | `0` | Show only the first N term buttons, with an expand toggle. `0` = no limit. |
| `filter_expand_label` | `See all` | Expand toggle text. |
| `filter_expand_class` | — | Extra classes appended to `.wr-filters__expand`. |
| `filter_row_classes` | — | Extra classes appended to `.wr-filters__list`. |
| `filter_item_classes` | — | Extra classes appended to each `.wr-filters__item`. |
| `all_category_button` | `All` | Label of the reset ("All") button. |
| `show_filter_count` | `true` | Show the post count on each button. `false` removes every `.wr-filters__item-count` span and skips the count queries. Button mode only. |
| `enable_clear_button` | `false` | Render a "Clear Filters" button that resets filters, search and selects. |
| `enable_search` | `false` | Render a search field. |
| `label_search_button` | `Search` | Search button text. |
| `search_placeholder` | `Search` | Search field placeholder. |
| `sort_options` | — | Sorting `<select>`. Records separated by `\|`, each `orderby:order:label` (split on the first two colons, so a label may contain a colon). `orderby` is one of `date`, `title`, `menu_order`, `rand`, `modified`, `comment_count`, `price`, `popularity`, `rating`; `order` is `asc` or `desc`. Empty → no select. The **first** record is the default sort; the URL only carries `sort` when the active choice differs from it. Example: `sort_options="date:desc:Newest first\|price:asc:Cheapest first"`. |
| `filter_id` | `<post_type>_filter` | Links this panel to the list with the same value. |

## Multiple instances

To run several independent list + panel pairs on one page, give each pair its
own distinct `filter_id`:

```
[all_posts_ajax_filters post_type="post"    filter_id="blog" filter_by_category="true"]
[all_posts_ajax         post_type="post"    filter_id="blog" posts_per_page="6"]

[all_posts_ajax_filters post_type="product" filter_id="shop" filter_by_category="true"]
[all_posts_ajax         post_type="product" filter_id="shop"]
```

Each pair gets its own controller, scoped by `filter_id`, so the panels never
cross-talk. `data-filter-id` on `.wr-posts` and `.wr-filters` is the only pairing
mechanism; no element carries an `id`.

**Limitation:** two instances that expose the *same* taxonomy share one
`filter_<taxonomy>=` URL parameter, so both restore from it and both fire a
request on load. Use different taxonomies, or `sync_filters_url="false"` on one,
if that matters.

## Search

Post title and content are always searched. Everything else is opt-in from
*WR Ajax Load More → Search*:

- **ACF fields** — pick which fields (by name) the search looks inside. With
  none selected the search never touches `wp_postmeta`.
- **Comment text** and **taxonomy term names** — toggles, on by default.
- **Max words per search** and **minimum word length** — bound how large a
  search query can get.

By default the ACF/comment/term extension applies only to this plugin's
queries. `add_filter( 'wralm_extend_all_search', '__return_true' )` restores it
for every front-end search.

### Hide a post from the list

Every public post type gets a *Hide from list* checkbox. Checked posts are
excluded from both the initial render and AJAX pages.

## WooCommerce

When `post_type="product"` and WooCommerce is active, `[all_posts_ajax]`:

- Excludes catalog-hidden products, and out-of-stock products when the store
  option *Hide out of stock items from the catalog* is on.
- Wraps the card loop in `wc_setup_loop()` / `wc_reset_loop()`, so
  `wc_get_loop_prop()` and the `$product` global work inside
  `all_posts_ajax/product-card.php`.
- Makes `price`, `popularity` and `rating` real sort options that map to the
  correct WooCommerce meta sort.
- Takes posts-per-page from the WooCommerce catalog settings; the
  `posts_per_page` attribute is ignored for products, so the AJAX list
  paginates in the same chunks as the native shop.

WooCommerce's own `woocommerce_product_query` hook does not apply to this
custom query, by design.

## REST endpoint

`GET /wp-json/wralm/v1/list` returns `{ html, pagination, max_page,
canonical_url }` for a given query state. It sends `Cache-Control: public,
max-age=30` so a reverse proxy or CDN can cache it. The route is public (a list
of published posts is not a CSRF target); abuse is bounded by a per-IP rate
limit, 60 requests/minute by default (`429` over the limit).

## Theme integration

Cards are rendered with
`get_template_part( 'all_posts_ajax/' . $post_type . '-card' )`. The theme must
provide `all_posts_ajax/{post_type}-card.php` — for regular posts,
`all_posts_ajax/post-card.php`. Activating the plugin creates the directory and
an empty `post-card.php` for you to fill in.

## Hooks

| Hook | Type | Default | Purpose |
|---|---|---|---|
| `wralm_query_args` | filter | — | `( array $args, WRALM_Query_Config $config )` — last chance to alter the `WP_Query` args. |
| `wralm_extend_all_search` | filter | `false` | `true` applies the ACF/comment/term search extension to every front-end search. |
| `wralm_rate_limit` | filter | `60` | Max REST requests per IP per minute. `0` or less disables the limit. |
| `wralm_max_posts_per_page` | filter | `200` | Upper clamp for `posts_per_page` coming from a request. |

## CSS contract

The plugin ships no front-end CSS; it only emits a stable class structure for a
theme to style. BEM-style, all prefixed `wr-`. State classes: `is-active`,
`is-current`, `is-disabled`, `is-hidden`.

List — `[all_posts_ajax]`:

```
.wr-posts                     controller root, carries data-filter-id
  .wr-posts__list             holds the cards + the data-* attributes
  .wr-posts__pagination
    a.wr-posts__page          + --prev / --next / --more, .is-current, --dots
                              (numbered links also keep page-numbers / current / dots)
  .wr-posts__empty            "no results" state
```

Panel — `[all_posts_ajax_filters]`:

```
.wr-filters                            panel scope, carries data-filter-id
  form.wr-filters__form
  .wr-filters__search
    input.wr-filters__search-input
    button.wr-filters__search-submit
  .wr-filters__list
    button.wr-filters__item            + --all / --parent / --child, data-multiply
      span.wr-filters__item-label
      span.wr-filters__item-count
    p.wr-filters__heading
    .wr-filters__select
      select.wr-filters__select-control
    button.wr-filters__clear
  .wr-filters__expand
  .wr-filters__sort
    select.wr-filters__sort-control
```

`row_classes`, `filter_row_classes`, `filter_item_classes` and
`filter_expand_class` append extra classes to the corresponding structural
element; they do not replace it.

## JavaScript events

`AjaxPaginationDone` and `AjaxFilterDone` fire on `document` and on the
instance's `.wr-posts` element after every request.

```js
$(document).on('AjaxFilterDone', function () {
    // re-init sliders, lazy images, etc.
});
```

## Development

Two independent Vite builds, both run with `yarn build` in their directory
(package manager: yarn).

| Bundle | Directory | Entry | Output |
|---|---|---|---|
| Public load-more / filter script | repo root | `src/js/load_more_and_filter.js` | `dist/js/load_more_and_filter.js` |
| Admin shortcode builder (React + TS) | `frontend/` | `frontend/src/index.tsx` | `dist/app.js`, `dist/style.css` |

```
yarn install && yarn build                 # public script
cd frontend && yarn install && yarn build   # admin console
```

`dist/` is committed — the plugin loads it directly. Rebuild and commit after
changing `src/js/` or `frontend/src/`.

`yarn package` (or `yarn package:fast` to skip install) builds both bundles and
produces `<slug>_<version>.zip` in the repo root, mirroring CI. On push to
`main`, CI builds the same zip and publishes it as a GitHub Release
`v<version>`, where the version is read from the plugin header.

## License

GPL-2.0
