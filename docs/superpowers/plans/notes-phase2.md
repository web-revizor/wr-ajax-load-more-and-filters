# Phase 2 — Query correctness verification (static)

Appendix A rows 1–12 require a live WordPress install, which this standalone project does not have (preflight ruling in the SDD ledger). Verification here is static: `php -l` on every touched file + the per-task shim harnesses run by the Task 1.1–1.6 implementers and independently reproduced by the task reviewers.

## The four latent bugs Phase 1 was meant to fix

| Bug (spec §C / §B) | Fix location | Static evidence |
|---|---|---|
| AJAX `paged` from empty `page` string → `0` (breaks current-page highlight, off-by-one prev/next) | `WRALM_Query_Config::from_request` — `$c->paged = isset($req['page']) && is_scalar($req['page']) ? max(1, absint($req['page'])) : 1;` (`inc/class-query-config.php`) | Task 1.2 shim Test A + reviewer: `from_request(['page'=>''])->paged === 1`. Task 1.6 shim: empty/missing `page` → `paged === 1`, JSON still returned. |
| Archive term AND-ed with same-taxonomy user filter → zero results | `WRALM_Query_Config::build_tax_query` — archive-term clause added only `! isset($this->tax_filters[$this->archive_taxonomy])` | Task 1.2 shim Test C + reviewer: with a user filter on the archive's taxonomy, the archive `term_id` clause is absent; different-taxonomy filters (Test D) are AND-ed. |
| `/page/N/` links emitted on non-archive (static) pages → 404 / canonical redirect | `WRALM_Pagination::resolve_base` — pretty format only when `update_url && archive_context && using_permalinks()`, else `?paged=%#%` | Task 1.3 shim + reviewer: `resolve_base(false,false,'.../page/3/')` → `['.../page/3/%_%', '?paged=%#%']`; `render_posts` passes `$config->archive_context` (computed from `is_archive()||is_home()||is_front_page()||is_post_type_archive()||is_category()||is_tag()||is_tax()`). |
| Logged-in users see drafts/private in the initial render but only `publish` after AJAX | `WRALM_Query_Config::wp_query_args` sets `'post_status' => 'publish'` unconditionally; both `render_posts` (Task 1.4) and `handle_ajax` (Task 1.6) now build the query through it | Task 1.2 shim: `wp_query_args()` always contains `post_status => 'publish'`. Ledger ruling records the intentional behaviour change + the `wralm_query_args` filter escape hatch. |

## Additional Phase 1 correctness changes (all via the DTO, verified in the 1.1–1.6 reviews)

- Front-page pagination: `current_paged()` falls back from `paged` to `page` (front page stores the number in `page`). Task 1.1 reviewer confirmed.
- Hide-meta clause (`all_posts_ajax_hide` OR / `!=` / `NOT EXISTS`) preserved byte-for-byte from the pre-refactor idiom. Task 1.2 shim Test E.
- `links()` no longer reads WP globals during `wp_ajax_loadmore` when the caller supplies `base`/`format`/`total`/`current` (the `$has_explicit` path). Non-explicit callers get byte-identical output. Task 1.3 reviewer diffed the `else` branch against base — verbatim.
- Non-pagination query args (`?filter_search=`, custom params) are preserved on pagination links via `handle_ajax`'s `$add_args` — currently inert because the JS client sends `base_url` as pathname only; **Task 5.1 must send `base_url` with `window.location.search`** (carried in the ledger).

## Residual runtime-only risk (cannot verify without WP)

- `WP_Query` argument interactions (e.g. `meta_query` + `tax_query` + `s` + `orderby` together on a real DB), `is_*()` context-function results on real templates, and `get_pagenum_link()` output on real permalink structures are not exercised. The shim harnesses assert argument *shape*, not WordPress's interpretation of it.
- `apply_filters('wralm_query_args', ...)` — no third-party consumer exists yet; the hook is new surface area.

## Result

Rows 1–12: **not runnable here.** Static confirmation of the four target fixes and the hide-meta / pagination parity: **pass.** Rows 1–12 are carried to Appendix A as a deployment hand-off checklist (Task 12.3).
