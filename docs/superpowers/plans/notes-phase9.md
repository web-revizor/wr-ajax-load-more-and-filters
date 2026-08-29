# Phase 9 — WooCommerce wiring verification (static)

Task 9.2 in the plan is a live-WooCommerce verification matrix. No WooCommerce install is available here (standalone project), so this is a static confirmation that the Phase-1 `class_exists('WRALM_Woo')` call sites are correctly shaped now that `WRALM_Woo` exists (Task 9.1, commit `95c94d4`).

## Call-site inventory (grep-verified)

| Site | Code | Effect |
|---|---|---|
| `inc/class-query-config.php:212-217` | `build_tax_query()`: `if (class_exists('WRALM_Woo') && WRALM_Woo::is_product_query($this->post_type)) { $vis = WRALM_Woo::visibility_tax_query(); if ($vis) $clauses[] = $vis; }` | Appends a `product_visibility NOT IN (exclude-from-catalog[, outofstock])` clause to the `tax_query` for `post_type=product`. `count($clauses) > 1` guard means the clause is applied even when there are no user/archive filters. |
| `inc/class-query-config.php:243-244` | `wp_query_args()`: `if (class_exists('WRALM_Woo') && WRALM_Woo::is_product_query(...)) { $args = array_merge($args, WRALM_Woo::orderby_args($this->orderby, $this->order)); }` | For products, `price`/`popularity`/`rating`/`menu_order` map to real `meta_value_num` + `meta_key` (or `menu_order title`); `array_merge` overrides the generic `orderby`/`order` and adds `meta_key`. Non-product queries keep the generic `$map` fallback path. |
| `inc/class-shortcode.php:30,37,46` | `render_posts()`: `$is_product = class_exists('WRALM_Woo') && WRALM_Woo::is_product_query($config->post_type);` then `WRALM_Woo::setup_loop($wp_query)` before the card loop and `WRALM_Woo::reset_loop()` after, inside `if ($wp_query->have_posts())`. | `wc_setup_loop()` / `wc_reset_loop()` bracket the `get_template_part()` loop so `wc_get_loop_prop()`, the loop counter, and `$GLOBALS['product']` behave inside `all_posts_ajax/product-card.php`. `setup_loop` reads `$q->found_posts` / `max_num_pages` — populated after `new WP_Query`, so ordering is correct. |
| `inc/class-load-more.php:51,56,63` | `handle_ajax()`: identical `setup_loop`/`reset_loop` bracket around the AJAX card loop. | Same, for the AJAX-appended pages. |

`WRALM_Woo` methods themselves (Task 9.1) are shim-verified for both WC-inactive (every method a safe no-op / `false` / `[]`) and WC-active (visibility clause built, out-of-stock gated on `woocommerce_hide_out_of_stock_items`).

## What is NOT verified here (runtime-only, for the deployment hand-off — Appendix A rows 29-32)

- Real `product_visibility` term-taxonomy-ids on a live store and whether the `NOT IN term_taxonomy_id` clause actually filters the result set as expected.
- `wc_setup_loop()` interaction with a real theme's `content-product.php` / `wc_get_template_part('content','product')` and the `$GLOBALS['product']` global inside the card.
- WooCommerce's own `woocommerce_product_query` / catalog-ordering hooks do NOT run on this custom `WP_Query` (they're bound to the main shop query) — expected; price/popularity/rating sorting is handled explicitly via `WRALM_Woo::orderby_args()`.
- `loop_shop_per_page` is intentionally NOT applied — `posts_per_page` comes from the shortcode.

## Result

Wiring: **correct and complete** by static inspection. All four call sites are guarded, correctly ordered, and pass the right arguments. Runtime behaviour on a live WooCommerce store is carried to Appendix A (Task 12.3 hand-off checklist).
