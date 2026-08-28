jQuery(function ($) {
    let posts_per_page = "",
        posts_type = "",
        page = 1,
        pagination_type = "",
        more_classes = "",
        more_label = "",
        prev_text = "",
        next_text = "",
        category = {},
        category_id = "",
        category_taxonomy = "",
        search = "",
        order = "",
        this_load_more = "",
        data = [],
        urlParams = new URLSearchParams(window.location.search),
        first = true,
        URLArray = "",
        filterCheck = false,
        searchCheck,
        initLoad = false;

    const ajax_row_holder = $(".ajax_row_holder");
    const row_items = $(".ajax_row");
    const $select = $(".js-category-filter-select");
    const $button = $(".js-category-filter");
    const $form = $("#all_posts_filter");
    const $search = $("#all-post-search");
    const $order = $("#js-post-order");
    const totalSelect = $select.length;
    const totalButton = $button.length;

    function posts_per_page_fn() {
        posts_per_page = row_items.attr("data-posts-per-page");
    }

    function posts_type_fn() {
        posts_type = row_items.attr("data-posts-type");
    }

    function pagination_type_fn() {
        pagination_type = row_items.attr("data-pagination-type");
    }

    function more_classes_fn() {
        more_classes = row_items.attr("data-more-classes");
    }

    function more_label_fn() {
        more_label = row_items.attr("data-more-label");
    }

    function prev_text_fn() {
        prev_text = row_items.attr("data-prev-text");
    }

    function next_text_fn() {
        next_text = row_items.attr("data-next-text");
    }

    function category_id_fn() {
        category_id = row_items.attr("data-cat-id");
    }

    function category_taxonomy_fn() {
        category_taxonomy = row_items.attr("data-cat-taxonomy");
    }

    function init_page_fn() {
        return ajax_row_holder.attr("data-init-page");
    }

    function category_fn() {
        category = {};
        URLArray = "";
        first = true;
        if ($("button").hasClass("js-category-filter")) {
            $(".js-category-filter.active").each(function () {
                if (!$(this).hasClass("allCategories")) {
                    let taxonomy = $(this).attr("data-taxonomy");
                    let slug = $(this).attr("data-slug");
                    category[taxonomy] =
                        category[taxonomy] && Array.isArray(category[taxonomy])
                            ? [...category[taxonomy], slug]
                            : [slug];
                }
            });
        }
        if ($("select").hasClass("js-category-filter-select")) {
            $select.each(function () {
                if ($(this).val()) {
                    let taxonomy = $(this).attr("data-taxonomy");
                    let slug = $(this).val();
                    category[taxonomy] =
                        category[taxonomy] && Array.isArray(category[taxonomy])
                            ? [...category[taxonomy], slug]
                            : [slug];
                }
            });
        }
        $.each(category, function (i, v) {
            if (first) {
                URLArray += i + "=" + v;
                first = false;
            } else {
                URLArray += "&" + i + "=" + v;
            }
        });
    }

    function all_param(url) {
        posts_per_page_fn();
        posts_type_fn();
        pagination_type_fn();
        more_classes_fn();
        more_label_fn();
        prev_text_fn();
        next_text_fn();
        category_fn();
        category_id_fn();
        category_taxonomy_fn();
        if (initLoad) {
            page = init_page_fn();
        } else {
            page = loadmore_params.current_page;
        }

        search = $search.val();
        order = $order.val();
        this_load_more = $(".load_more_holder");

        window.history.pushState(null, null, url);

        if (search && search !== "") {
            if (first) {
                URLArray += "filter_search=" + search;
                first = false;
            } else {
                URLArray += "&filter_search=" + search;
            }
        }
        if (URLArray !== "") {
            window.history.pushState(null, null, "?" + URLArray);
        } else {
            var clean_uri =
                location.protocol + "//" + location.host + location.pathname;
            window.history.pushState(null, null, clean_uri);
        }

        data = {
            action: "loadmore",
            query: loadmore_params.posts,
            page: page,
            posts_per_page: posts_per_page,
            post_type: posts_type,
            pagination_type: pagination_type,
            category: category,
            search: search,
            order: order,
            more_classes: more_classes,
            more_label: more_label,
            prev_text: prev_text,
            next_text: next_text,
            category_id: category_id,
            category_taxonomy: category_taxonomy,
            base_url: window.location.pathname,
        };
    }

    if (urlParams.has("filter_search")) {
        $search.val(urlParams.get("filter_search"));
        searchCheck = true;
    } else {
        searchCheck = false;
    }

    $(document).on("click", "#pagination_holder .load_page", function (e) {
        e.preventDefault();
        loadmore_params.current_page = $(this).data("page");
        const url = $(this).attr("href");
        all_param(url);

        let clearRow = true;

        if ($(this).hasClass("load_more")) {
            clearRow = false;
        }

        $.ajax({
            url: loadmore_params.ajaxurl,
            data: data,
            type: "POST",
            beforeSend: function (xhr) {
                ajax_row_holder.css("opacity", "0.5");
            },
            success: function (data) {
                if (data) {
                    this_load_more.remove();
                    if (clearRow === true) {
                        row_items.empty();
                    }
                    row_items.append(data.html);
                    ajax_row_holder.append(data.pagination);
                    ajax_row_holder.css("opacity", "1");
                }
            },
        }).done(function () {
            $(document).trigger("AjaxPaginationDone");
        });
    });

    $button.on("click", function () {
        if ($(this).hasClass("allCategories")) {
            if ($(this).hasClass("active")) {
                $(this).removeClass("active");
            }
            return;
        }
        if ($(this).hasClass("multiply-false")) {
            $button.removeClass("active");
            $(this).addClass("active");
        } else {
            if (!$(this).hasClass("active")) {
                $(this).addClass("active");
                $(".allCategories").removeClass("active");
            } else {
                $(this).removeClass("active");
                if ($button.not(".active").filter(".allCategories").length) {
                    $(".allCategories").addClass("active");
                }
            }
        }
    });

    $(".js-clear-filter").on("click", function () {
        $button.removeClass("active");
        $search.val("");
        $select.prop("selectedIndex", 0);
    });

    $order.on("change", function () {
        $form.trigger("submit");
    });

    $select.on("change", function () {
        $form.trigger("submit");
    });

    $form.on("submit", function (e) {
        e.preventDefault();

        loadmore_params.current_page = 1;

        all_param();

        $.ajax({
            url: loadmore_params.ajaxurl,
            data: data,
            type: "POST",
            beforeSend: function (xhr) {
                ajax_row_holder.css("opacity", "0.5");
            },
            success: function (data) {
                if (data) {
                    this_load_more.remove();
                    row_items.empty();
                    if (data.html !== "") {
                        row_items.append(data.html);
                    } else {
                        row_items.append(
                            '<div class="no-results-found">no results found</div>',
                        );
                    }
                    ajax_row_holder.append(data.pagination);
                    ajax_row_holder.css("opacity", "1");
                    if (!initLoad) {
                        const url = new URL(window.location.href);
                        url.pathname = data.base_url;
                        url.search = URLArray;
                        window.history.pushState(null, null, url);
                    }
                    initLoad = false;
                }
            },
        }).done(function () {
            $(document).trigger("AjaxFilterDone");
        });
    });

    $select.each(function (index) {
        let taxonomy = $(this).attr("data-taxonomy");
        if (urlParams.has(taxonomy)) {
            $(this).val(urlParams.get(taxonomy));
            filterCheck = true;
        }
        if (index === totalSelect - 1 && filterCheck) {
            $form.trigger("submit");
        }
    });

    $button.each(function (index) {
        if (!$(this).hasClass("allCategories")) {
            let $this = $(this);
            let taxonomy = $this.attr("data-taxonomy");
            let slug = $this.attr("data-slug");

            if (urlParams.has(taxonomy)) {
                let array = urlParams.get(taxonomy).split(",");

                $.each(array, function (i, v) {
                    if (v === slug) {
                        $this.addClass("active");
                        $(".allCategories").removeClass("active");
                        filterCheck = true;
                    }
                });
            }
        }
        if (index === totalButton - 1 && filterCheck) {
            initLoad = true;
            $form.trigger("submit");
        }
    });

    if (!filterCheck && searchCheck) {
        $form.trigger("submit");
    }
});
