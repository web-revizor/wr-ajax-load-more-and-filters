/**
 * WR Ajax Load More & Filters — public front-end controller.
 *
 * One `Instance` per `.wr-posts`. Every selector is scoped to the instance's
 * holder (`this.$holder`) or to its filter panel (`this.$filters`, matched by
 * `data-filter-id`), so several `[all_posts_ajax]` / `[all_posts_ajax_filters]`
 * pairs can live on the same page without interfering with each other.
 *
 * URL sync is fully two-way: every filter / search / sort / pagination action
 * pushes the server-built `canonical_url`, and Back / Forward (`popstate`) plus
 * the initial load restore the panel + page straight from the address bar.
 *
 * jQuery is a WordPress global; this file is bundled as a plain IIFE.
 */
jQuery(function ($) {
    'use strict';

    var params = window.loadmore_params || {};

    // holder element -> Instance, used by the delegated pagination + popstate handlers.
    var instances = new Map();

    var NO_RESULTS = '<div class="wr-posts__empty">no results found</div>';

    var DEFAULT_SORT = 'date:desc';

    /** { category, search, sort } state -> the filter_<tax>= / filter_search / sort arg map. */
    function urlStateToFilterArgs(state, defaultSort) {
        var args = {};
        Object.keys(state.category).forEach(function (t) {
            args['filter_' + t] = state.category[t].join(',');
        });
        if (state.search) {
            args['filter_search'] = state.search;
        }
        if (state.sort && state.sort !== defaultSort) {
            args['sort'] = state.sort;
        }
        return args;
    }

    /** Same key set and values (order-insensitive on keys). */
    function sameFilterArgs(a, b) {
        b = b || {};
        var ak = Object.keys(a).sort();
        var bk = Object.keys(b).sort();
        if (ak.length !== bk.length) {
            return false;
        }
        for (var i = 0; i < ak.length; i++) {
            if (ak[i] !== bk[i] || String(a[ak[i]]) !== String(b[ak[i]])) {
                return false;
            }
        }
        return true;
    }

    /* ------------------------------------------------------------------ *
     * Filter panel (shared UI state)
     *
     * The panel handlers only mutate DOM state (active classes, cleared
     * inputs) and then let the native `submit` of the `type="submit"`
     * buttons drive the request. They are bound ONCE per panel, so a panel
     * shared by two lists does not toggle the same button twice.
     * ------------------------------------------------------------------ */

    function toggleFilterButton($panel, $btn) {
        var $buttons = $panel.find('.wr-filters__item');
        var $all = $buttons.filter('.wr-filters__item--all');

        if ($btn.hasClass('wr-filters__item--all')) {
            // "All" clears only the category filters (buttons + selects) — never
            // the search field or the sort select. Its own submit re-queries.
            $buttons.removeClass('is-active');
            $btn.addClass('is-active');
            $panel.find('.wr-filters__select-control').prop('selectedIndex', 0);
            return;
        }

        if ($btn.attr('data-multiply') === 'false') {
            // Single-select: clicking the active term turns it off and restores
            // "All"; clicking another term replaces the selection.
            if ($btn.hasClass('is-active')) {
                $btn.removeClass('is-active');
                $all.addClass('is-active');
            } else {
                $buttons.removeClass('is-active');
                $btn.addClass('is-active');
            }
            return;
        }

        if (!$btn.hasClass('is-active')) {
            $btn.addClass('is-active');
            $all.removeClass('is-active');
        } else {
            $btn.removeClass('is-active');
            if (!$buttons.filter('.is-active').not('.wr-filters__item--all').length) {
                $all.addClass('is-active');
            }
        }
    }

    function bindPanels($panels) {
        $panels.each(function () {
            var $panel = $(this);

            if ($panel.data('wralmBound')) {
                return;
            }
            $panel.data('wralmBound', true);

            $panel.on('click', '.wr-filters__item', function () {
                toggleFilterButton($panel, $(this));
            });

            $panel.on('click', '.wr-filters__clear', function () {
                var $buttons = $panel.find('.wr-filters__item');
                $buttons.removeClass('is-active');
                $buttons.filter('.wr-filters__item--all').addClass('is-active');
                $panel.find('.wr-filters__search-input').val('');
                $panel.find('.wr-filters__select-control').prop('selectedIndex', 0);
            });

            $panel.on('change', '.wr-filters__select-control', function () {
                $panel.find('.wr-filters__form').trigger('submit');
            });
        });
    }

    /* ------------------------------------------------------------------ *
     * Instance
     * ------------------------------------------------------------------ */

    function Instance($holder) {
        this.$holder = $holder;
        this.$row = $holder.find('.wr-posts__list').first();

        this.filterId = String($holder.data('filter-id') || '');
        this.$filters = $('.wr-filters[data-filter-id="' + this.filterId + '"]');

        this.$form = this.$filters.find('.wr-filters__form');
        this.$search = this.$filters.find('.wr-filters__search-input');
        this.$sort = this.$filters.find('.wr-filters__sort-control');
        this.$buttons = this.$filters.find('.wr-filters__item');
        this.$selects = this.$filters.find('.wr-filters__select-control');

        this.syncFiltersUrl = String(this.$row.data('sync-filters-url')) !== 'false';
        this.syncPaginationUrl = String(this.$row.data('sync-pagination-url')) !== 'false';
        this.archiveContext = String(this.$row.data('archive-context')) === 'true';
        this.initPage = parseInt($holder.data('init-page'), 10) || 1;
        // Filter args the server already rendered into this holder (jQuery parses
        // the JSON in data-init-filters), used to skip a redundant initial fetch.
        this.initFilters = this.$holder.data('init-filters') || {};

        this.page = this.initPage;
        this.seq = 0;
        this.xhr = null;

        bindPanels(this.$filters);
        this.bind();
        this.restoreFromUrl();
    }

    /** Raw `data-*` string off `.wr-posts__list` (never undefined). */
    Instance.prototype.rowAttr = function (name) {
        var value = this.$row.attr('data-' + name);
        return typeof value === 'undefined' || value === null ? '' : String(value);
    };

    Instance.prototype.searchTerm = function () {
        return this.$search.length ? this.$search.val() || '' : '';
    };

    /** Active sort as "orderby:order". Panel select wins; else what the server rendered. */
    Instance.prototype.sortValue = function () {
        if (this.$sort.length && this.$sort.val()) {
            return this.$sort.val();
        }
        return this.rowAttr('sort') || DEFAULT_SORT;
    };

    /** The panel's first sort option — the sort the URL treats as "clean". */
    Instance.prototype.defaultSort = function () {
        if (this.$sort.length) {
            var first = this.$sort.find('option').first().val();
            if (first) {
                return first;
            }
        }
        return DEFAULT_SORT;
    };

    /** The set of taxonomy slugs this panel knows about (buttons + selects). */
    Instance.prototype.knownTaxonomies = function () {
        var taxonomies = {};
        this.$buttons.add(this.$selects).each(function () {
            var t = $(this).attr('data-taxonomy');
            if (t) {
                taxonomies[t] = true;
            }
        });
        return taxonomies;
    };

    /** { taxonomy: [slug, ...] } from the active buttons + non-empty selects. */
    Instance.prototype.collectCategories = function () {
        var category = {};

        function push(taxonomy, slug) {
            if (!taxonomy || slug === null || typeof slug === 'undefined' || slug === '') {
                return;
            }
            if (!category[taxonomy]) {
                category[taxonomy] = [];
            }
            category[taxonomy] = category[taxonomy].concat(slug);
        }

        this.$buttons.filter('.is-active').each(function () {
            var $btn = $(this);
            if ($btn.hasClass('wr-filters__item--all')) {
                return;
            }
            push($btn.attr('data-taxonomy'), $btn.attr('data-slug'));
        });

        this.$selects.each(function () {
            var value = $(this).val();
            if (!value || (Array.isArray(value) && !value.length)) {
                return;
            }
            push($(this).attr('data-taxonomy'), value);
        });

        return category;
    };

    /* ---------------------------------------------------------------- *
     * URL <-> state
     * ---------------------------------------------------------------- */

    /** Read { category, search, sort, page } out of the current address bar. */
    Instance.prototype.readUrlState = function () {
        var urlParams = new URLSearchParams(window.location.search);
        var state = { category: {}, search: '', sort: '', page: 1 };

        if (urlParams.has('filter_search')) {
            state.search = urlParams.get('filter_search');
        }
        if (urlParams.has('sort')) {
            state.sort = urlParams.get('sort');
        }

        // Taxonomy filters are namespaced `filter_<taxonomy>=` so they never
        // collide with a real WordPress / WooCommerce query var (a bare
        // ?product_cat=a,b gets 301-redirected and re-encoded).
        var taxonomies = this.knownTaxonomies();
        Object.keys(taxonomies).forEach(function (t) {
            var key = 'filter_' + t;
            if (urlParams.has(key)) {
                var slugs = urlParams.get(key).split(',').filter(Boolean);
                if (slugs.length) {
                    state.category[t] = slugs;
                }
            }
        });

        var pretty = window.location.pathname.match(/\/page\/(\d+)\/?$/);
        if (pretty) {
            state.page = parseInt(pretty[1], 10) || 1;
        } else if (urlParams.has('paged')) {
            state.page = parseInt(urlParams.get('paged'), 10) || 1;
        } else if (urlParams.has('page')) {
            state.page = parseInt(urlParams.get('page'), 10) || 1;
        }

        return state;
    };

    /** Force the panel DOM (buttons / selects / search / sort) to match `state`. */
    Instance.prototype.applyUrlState = function (state) {
        if (this.$search.length) {
            this.$search.val(state.search || '');
        }
        if (this.$sort.length) {
            this.$sort.val(state.sort || this.defaultSort());
        }

        this.$buttons.each(function () {
            var $btn = $(this);
            if ($btn.hasClass('wr-filters__item--all')) {
                return;
            }
            var t = $btn.attr('data-taxonomy');
            var slug = $btn.attr('data-slug');
            var on = !!(t && state.category[t] && $.inArray(slug, state.category[t]) !== -1);
            $btn.toggleClass('is-active', on);
        });

        var anyActive = this.$buttons.filter('.is-active').not('.wr-filters__item--all').length > 0;
        this.$buttons.filter('.wr-filters__item--all').toggleClass('is-active', !anyActive);

        this.$selects.each(function () {
            var t = $(this).attr('data-taxonomy');
            var vals = (t && state.category[t]) || [];
            $(this).val(this.multiple ? vals : (vals[0] || ''));
        });
    };

    /* ---------------------------------------------------------------- *
     * Requests
     * ---------------------------------------------------------------- */

    /** Query params for the wralm/v1/list GET request. */
    Instance.prototype.buildData = function (category) {
        return {
            page: this.page,
            posts_per_page: this.rowAttr('posts-per-page'),
            post_type: this.rowAttr('posts-type'),
            pagination_type: this.rowAttr('pagination-type'),
            category: category,
            category_id: this.rowAttr('cat-id'),
            category_taxonomy: this.rowAttr('cat-taxonomy'),
            search: this.searchTerm(),
            sort: this.sortValue(),
            default_sort: this.defaultSort(),
            more_classes: this.rowAttr('more-classes'),
            more_label: this.rowAttr('more-label'),
            prev_text: this.rowAttr('prev-text'),
            next_text: this.rowAttr('next-text'),
            filter_id: this.filterId,
            sync_filters_url: this.syncFiltersUrl ? 'true' : 'false',
            sync_pagination_url: this.syncPaginationUrl ? 'true' : 'false',
            archive_context: this.archiveContext ? 'true' : 'false',
            // Raw current URL; the server strips any /page/N/ or ?paged=N itself.
            base_url: window.location.pathname + window.location.search
        };
    };

    /**
     * opts: { page, clearRow, push, emptyHtml, event }
     *   push — true to write `res.canonical_url` into the address bar.
     */
    Instance.prototype.request = function (opts) {
        var self = this;
        var mySeq = ++this.seq;

        if (this.xhr) {
            this.xhr.abort();
        }

        this.page = parseInt(opts.page, 10) || 1;

        var data = this.buildData(this.collectCategories());
        var push = !!opts.push;

        this.$holder.css('opacity', '0.5');

        this.xhr = $.ajax({
            url: params.resturl,
            type: 'GET',
            data: data,
            // Sent so a logged-in viewer is resolved on the REST request; the
            // route itself is public (no nonce gate).
            headers: params.nonce ? { 'X-WP-Nonce': params.nonce } : {}
        }).done(function (res) {
            if (mySeq !== self.seq) {
                return; // stale response, a newer request already owns the DOM
            }
            self.xhr = null;

            if (res) {
                self.$holder.find('.wr-posts__pagination').remove();
                if (opts.clearRow) {
                    self.$row.empty();
                }
                self.$row.append(res.html ? res.html : opts.emptyHtml || '');
                if (res.pagination) {
                    self.$holder.append(res.pagination);
                }
                if (push && res.canonical_url) {
                    window.history.pushState(null, '', res.canonical_url);
                }
            }

            self.$holder.css('opacity', '1');

            $(document).trigger(opts.event);
            self.$holder.trigger(opts.event);
        }).fail(function (jqXHR, textStatus) {
            if (mySeq !== self.seq || textStatus === 'abort') {
                return;
            }
            self.xhr = null;
            self.$holder.css('opacity', '1');
        });
    };

    Instance.prototype.paginate = function ($link) {
        var isLoadMore = $link.hasClass('wr-posts__page--more');
        this.request({
            page: parseInt($link.attr('data-page'), 10) || 1,
            clearRow: !isLoadMore,
            // "Show more" accumulates pages; syncing ?paged=N there would make a
            // reload render only the last page. Numbered / prev / next sync.
            push: !isLoadMore && this.syncPaginationUrl,
            emptyHtml: '',
            event: 'AjaxPaginationDone'
        });
    };

    /** A filter / search / sort change: always resets to page 1. */
    Instance.prototype.filter = function (push) {
        this.request({
            page: 1,
            clearRow: true,
            push: push && this.syncFiltersUrl,
            emptyHtml: NO_RESULTS,
            event: 'AjaxFilterDone'
        });
    };

    Instance.prototype.bind = function () {
        var self = this;

        this.$form.on('submit', function (e) {
            e.preventDefault();
            self.filter(true);
        });

        // The sort select drives a re-query directly. A sort-only panel renders
        // no <form>, so routing this through the form's submit would be a dead
        // end there.
        this.$sort.on('change', function () {
            self.filter(true);
        });
    };

    /**
     * Initial load: restore the panel + page from the address bar and, when the
     * URL carries filter / search / sort / a different page than the server
     * rendered, fire one request for it (no push — the URL is already right).
     */
    Instance.prototype.restoreFromUrl = function () {
        var state = this.readUrlState();
        this.applyUrlState(state);

        var hasFilter = Object.keys(state.category).length > 0;
        var hasSearch = state.search !== '';
        var wantSort = state.sort || this.defaultSort();
        var sortMatches = wantSort === (this.rowAttr('sort') || DEFAULT_SORT);

        if (!hasFilter && !hasSearch && sortMatches && state.page === this.initPage) {
            return; // URL is the default the server already rendered
        }

        // The server reads filter_<tax>= / filter_search / sort from the URL too
        // (WRALM_Query_Config::from_atts). When it already rendered this exact
        // state + page, only the panel DOM needs syncing — no re-fetch.
        if (state.page === this.initPage && sortMatches &&
            sameFilterArgs(urlStateToFilterArgs(state, this.defaultSort()), this.initFilters)) {
            return;
        }

        this.request({
            page: state.page,
            clearRow: true,
            push: false,
            emptyHtml: NO_RESULTS,
            event: 'AjaxFilterDone'
        });
    };

    /** Back / Forward: re-render to match whatever the URL now says. */
    Instance.prototype.onPopState = function () {
        var state = this.readUrlState();
        this.applyUrlState(state);
        this.request({
            page: state.page,
            clearRow: true,
            push: false,
            emptyHtml: NO_RESULTS,
            event: 'AjaxFilterDone'
        });
    };

    /* ------------------------------------------------------------------ *
     * Boot + delegated routing
     * ------------------------------------------------------------------ */

    $('.wr-posts').each(function () {
        instances.set(this, new Instance($(this)));
    });

    $(document).on('click', '.wr-posts__pagination a.wr-posts__page', function (e) {
        e.preventDefault();

        var holder = $(this).closest('.wr-posts')[0];
        if (!holder) {
            return;
        }

        var inst = instances.get(holder);
        if (inst) {
            inst.paginate($(this));
        }
    });

    $(window).on('popstate', function () {
        instances.forEach(function (inst) {
            inst.onPopState();
        });
    });
});
