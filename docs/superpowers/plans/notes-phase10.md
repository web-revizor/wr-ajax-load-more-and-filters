# Phase 10 — Security: input-whitelist verification (static)

Task 10.2 in the plan is verification-only (the whitelists were implemented in Task 1.2's fix round, commit `2344329`). Confirmed by inspection of `inc/class-query-config.php::from_request()`:

| Input | Guard | Behaviour on hostile / off-list value |
|---|---|---|
| `post_type` | `is_string(...)` + `sanitize_key(...)` + `post_type_exists(...)` + `is_post_type_viewable(...)` (`class-query-config.php:130-133`) | `revision`, `attachment`, an unregistered slug, or a non-viewable CPT → falls back to `post`. Cannot enumerate private/internal post types. |
| `orderby` | `sanitize_orderby()` → `sanitize_key` + `in_array(..., ORDERBY_WHITELIST, true)` (`class-query-config.php:87-90, 158`) | `' OR 1=1`, `menu_order); DROP` → `date`. Only the 9 whitelisted keys pass. |
| `order` | `'ASC' === strtoupper($req['order']) ? 'ASC' : 'DESC'` with `is_string` guard (`class-query-config.php:157`) | anything not exactly `ASC` (case-insensitive) → `DESC`. `RAND`, arrays, injection strings → `DESC`. |
| every other scalar request key | `isset(...) && is_string(...)` / `is_scalar(...)` guard, then `sanitize_key` / `sanitize_text_field` / `absint` (`class-query-config.php:130-171`) | array-valued keys neither fatal nor warn (Task 1.2 fix round, verified E_ALL clean). |
| `category` (tax filters) | `is_array` guard + per-taxonomy `sanitize_key` + `taxonomy_exists` + `sanitize_title` on each slug (`class-query-config.php:160-171`) | unknown taxonomy dropped; slugs sanitised. |
| `s` (search) | `sanitize_text_field` (Task 1.2, no double-unslash); the `posts_search` SQL rewrite is `$wpdb->prepare()`-bound (Phase 7) | no SQL injection; ACF meta search bounded to `meta_key IN (...)`. |
| `nonce` | Task 10.1: `is_string` guard + `sanitize_text_field(wp_unslash(...))` + `wp_verify_nonce`; soft by default, hard via `add_filter('wralm_require_nonce','__return_true')` | absent/forged nonce is a no-op unless the site opts into enforcement. |

## Residual notes

- The endpoint remains `wp_ajax_nopriv` and serves published, publicly-viewable content only (`post_status => 'publish'`, `is_post_type_viewable` gate). Soft nonce is a deliberate choice for page-cache compatibility; a site needing CSRF-hardening flips one filter.
- No `current_user_can` checks are needed — nothing is written and only public data is read.

## Result

Input whitelists: **verified present and correct** by static inspection + the Task 1.2 / 10.1 shim harnesses. Runtime penetration testing of the live endpoint is carried to the deployment hand-off (Appendix A row 35).
