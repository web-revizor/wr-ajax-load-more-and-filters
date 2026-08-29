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

- post_type: post type name
- posts_per_page: number, can be "-1" for infinity posts on page
- type_pagination: list/both/default/none
- row_classes: string
- load_more_label: string
- load_more_classes: string
- prev_text: string
- next_text: string


- filter_by_category: boolean
- filter_row_classes: string
- filter_item_classes: string
- filter_item_limit: number
- filter_expand_label: string
- filter_expand_class: string
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

## JQuery events

- AjaxPaginationDone
- AjaxFilterDone

### Example

````
$(document).on('AjaxPaginationDone', function() {
    //do something
});

$(document).on('AjaxFilterDone', function() {
    //do something
});
````
