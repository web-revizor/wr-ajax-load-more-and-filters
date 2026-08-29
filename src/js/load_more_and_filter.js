/**
 * WR Ajax Load More & Filters — public front-end controller.
 *
 * One `Instance` per `.ajax_row_holder`. Every selector is scoped to the
 * instance's holder (`this.$holder`) or to its filter panel (`this.$filters`,
 * matched by `data-filter-id`), so several `[all_posts_ajax]` /
 * `[all_posts_ajax_filters]` pairs can live on the same page without
 * interfering with each other.
 *
 * jQuery is a WordPress global; this file is bundled as a plain IIFE.
 */
jQuery(function ($) {
    'use strict';

    var params = window.loadmore_params || {};

    // holder element -> Instance, used by the delegated pagination handler.
    var instances = new Map();

    var NO_RESULTS = '<div class="no-results-found">no results found</div>';

    /* ------------------------------------------------------------------ *
     * Filter panel (shared UI state)
     *
     * The panel handlers only mutate DOM state (active classes, cleared
     * inputs) and then let the native `submit` of the `type="submit"`
     * buttons drive the request. They are bound ONCE per panel, so a panel
     * shared by two lists does not toggle the same button twice.
     * ------------------------------------------------------------------ */

    function toggleFilterButton($buttons, $btn) {
        if ($btn.hasClass('allCategories')) {
            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
            }
            return;
        }

        if ($btn.hasClass('multiply-false')) {
            $buttons.removeClass('active');
            $btn.addClass('active');
            return;
        }

        if (!$btn.hasClass('active')) {
            $btn.addClass('active');
            $buttons.filter('.allCategories').removeClass('active');
        } else {
            $btn.removeClass('active');
            if (!$buttons.filter('.active').not('.allCategories').length) {
                $buttons.filter('.allCategories').addClass('active');
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

            $panel.on('click', '.js-category-filter', function () {
                toggleFilterButton($panel.find('.js-category-filter'), $(this));
            });

            $panel.on('click', '.js-clear-filter', function () {
                $panel.find('.js-category-filter').removeClass('active');
                $panel.find('.all-post-search').val('');
                $panel.find('.js-category-filter-select').prop('selectedIndex', 0);
            });

            $panel.on(
                'change',
                '.js-category-filter-select',
                function () {
                    $panel.find('.all_posts_form').trigger('submit');
                }
            );
        });
    }

    /* ------------------------------------------------------------------ *
     * Instance
     * ------------------------------------------------------------------ */

    function Instance($holder) {
        this.$holder = $holder;
        this.$row = $holder.find('.ajax_row').first();

        this.filterId = String($holder.data('filter-id') || '');
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
        this.initPage = parseInt($holder.data('init-page'), 10) || 1;

        this.page = this.initPage;
        this.seq = 0;
        this.xhr = null;

        bindPanels(this.$filters);
        this.bind();
        this.restoreFromUrl();
    }

    /** Raw `data-*` string off `.ajax_row` (never undefined). */
    Instance.prototype.rowAttr = function (name) {
        var value = this.$row.attr('data-' + name);
        return typeof value === 'undefined' || value === null ? '' : String(value);
    };

    Instance.prototype.searchTerm = function () {
        return this.$search.length ? this.$search.val() || '' : '';
    };

    Instance.prototype.orderValue = function () {
        return (this.$order.length && this.$order.val()) || 'DESC';
    };

    Instance.prototype.orderbyValue = function () {
        if (this.$orderby.length && this.$orderby.val()) {
            return this.$orderby.val();
        }
        return this.rowAttr('orderby') || 'date';
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

        this.$buttons.filter('.active').each(function () {
            var $btn = $(this);
            if ($btn.hasClass('allCategories')) {
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

    /**
     * The ONE url pushed for an action: current pathname plus the serialized
     * filter/search state, or the bare pathname when nothing is filtered.
     */
    Instance.prototype.buildUrl = function (category) {
        var parts = [];
        var search = this.searchTerm();

        $.each(category, function (taxonomy, slugs) {
            parts.push(
                encodeURIComponent(taxonomy) + '=' +
                $.map([].concat(slugs), encodeURIComponent).join(',')
            );
        });

        if (search !== '') {
            parts.push('filter_search=' + encodeURIComponent(search));
        }

        return parts.length
            ? window.location.pathname + '?' + parts.join('&')
            : window.location.pathname;
    };

    /** POST body for admin-ajax `loadmore`. No `query` key (Task 5.2). */
    Instance.prototype.buildData = function (category) {
        return {
            action: 'loadmore',
            nonce: params.nonce,
            page: this.page,
            posts_per_page: this.rowAttr('posts-per-page'),
            post_type: this.rowAttr('posts-type'),
            pagination_type: this.rowAttr('pagination-type'),
            category: category,
            category_id: this.rowAttr('cat-id'),
            category_taxonomy: this.rowAttr('cat-taxonomy'),
            search: this.searchTerm(),
            order: this.orderValue(),
            orderby: this.orderbyValue(),
            more_classes: this.rowAttr('more-classes'),
            more_label: this.rowAttr('more-label'),
            prev_text: this.rowAttr('prev-text'),
            next_text: this.rowAttr('next-text'),
            filter_id: this.filterId,
            update_url: this.updateUrl ? 'true' : 'false',
            archive_context: this.archiveContext ? 'true' : 'false',
            base_url: window.location.pathname + window.location.search
        };
    };

    /**
     * opts: { page, clearRow, pushUrl, emptyHtml, event }
     */
    Instance.prototype.request = function (opts) {
        var self = this;
        var mySeq = ++this.seq;

        if (this.xhr) {
            this.xhr.abort();
        }

        this.page = parseInt(opts.page, 10) || 1;

        var category = this.collectCategories();
        var data = this.buildData(category);
        var url = this.updateUrl && opts.pushUrl ? this.buildUrl(category) : null;

        this.$holder.css('opacity', '0.5');

        this.xhr = $.ajax({
            url: params.ajaxurl,
            type: 'POST',
            data: data
        }).done(function (res) {
            if (mySeq !== self.seq) {
                return; // stale response, a newer request already owns the DOM
            }
            self.xhr = null;

            if (res) {
                self.$holder.find('.load_more_holder').remove();
                if (opts.clearRow) {
                    self.$row.empty();
                }
                self.$row.append(res.html ? res.html : opts.emptyHtml || '');
                if (res.pagination) {
                    self.$holder.append(res.pagination);
                }
            }

            self.$holder.css('opacity', '1');

            if (url) {
                window.history.pushState(null, '', url);
            }

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
        this.request({
            page: parseInt($link.attr('data-page'), 10) || 1,
            clearRow: !$link.hasClass('load_more'),
            pushUrl: true,
            emptyHtml: '',
            event: 'AjaxPaginationDone'
        });
    };

    Instance.prototype.filter = function (page, pushUrl) {
        this.request({
            page: parseInt(page, 10) || 1,
            clearRow: true,
            pushUrl: pushUrl,
            emptyHtml: NO_RESULTS,
            event: 'AjaxFilterDone'
        });
    };

    Instance.prototype.bind = function () {
        var self = this;

        this.$form.on('submit', function (e) {
            e.preventDefault();
            self.filter(1, true);
        });

        // Order / order-by selects drive a re-query directly. An
        // `enable_order="true"`-only panel renders no <form>, so routing this
        // through the form's submit would be a dead end there.
        this.$order.add(this.$orderby).on('change', function () {
            self.filter(1, true);
        });
    };

    /**
     * Restore filter/search state from the URL, then fire ONE request for it.
     * Filter state restored from the URL keeps the server-rendered page
     * (`data-init-page`); a search-only URL starts at page 1.
     */
    Instance.prototype.restoreFromUrl = function () {
        var urlParams = new URLSearchParams(window.location.search);
        var restoredFilter = false;
        var restoredSearch = false;

        if (urlParams.has('filter_search') && this.$search.length) {
            this.$search.val(urlParams.get('filter_search'));
            restoredSearch = true;
        }

        this.$selects.each(function () {
            var taxonomy = $(this).attr('data-taxonomy');
            if (!taxonomy || !urlParams.has(taxonomy)) {
                return;
            }
            var values = urlParams.get(taxonomy).split(',');
            $(this).val(this.multiple ? values : values[0]);
            restoredFilter = true;
        });

        this.$buttons.each(function () {
            var $btn = $(this);
            if ($btn.hasClass('allCategories')) {
                return;
            }
            var taxonomy = $btn.attr('data-taxonomy');
            if (!taxonomy || !urlParams.has(taxonomy)) {
                return;
            }
            if ($.inArray($btn.attr('data-slug'), urlParams.get(taxonomy).split(',')) !== -1) {
                $btn.addClass('active');
                restoredFilter = true;
            }
        });

        if (restoredFilter) {
            this.$buttons.filter('.allCategories').removeClass('active');
            this.filter(this.initPage, false);
        } else if (restoredSearch) {
            this.filter(1, false);
        }
    };

    /* ------------------------------------------------------------------ *
     * Boot + delegated pagination routing
     * ------------------------------------------------------------------ */

    $('.ajax_row_holder').each(function () {
        instances.set(this, new Instance($(this)));
    });

    $(document).on('click', '.pagination_holder .load_page', function (e) {
        e.preventDefault();

        var holder = $(this).closest('.ajax_row_holder')[0];
        if (!holder) {
            return;
        }

        var inst = instances.get(holder);
        if (!inst) {
            return;
        }

        inst.paginate($(this));
    });
});
