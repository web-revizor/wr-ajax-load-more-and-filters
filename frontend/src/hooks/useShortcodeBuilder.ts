import {useMemo, useState} from 'react';
import type {BuilderState, ClassSettings, FilterSettings, MainSettings, OrderSettings, SearchSettings,} from '../types';

const defaultMain: MainSettings = {
    postType: 'post',
    postsPerPage: 10,
    typePagination: 'default',
    loadMoreLabel: '',
    prevText: '',
    nextText: '',
    syncFiltersUrl: true,
    syncPaginationUrl: true,
};

const defaultClasses: ClassSettings = {
    rowClasses: '',
    loadMoreClasses: '',
};

const defaultFilters: FilterSettings = {
    filterByCategory: false,
    enableClearButton: false,
    filterType: 'button',
    enableFilterTitles: false,
    multiplyFilter: false,
    showFilterCount: true,
    filterRowClasses: '',
    filterItemClasses: '',
    filterTaxonomy: [],
    allCategoryButton: '',
};

const defaultSearch: SearchSettings = {
    enableSearch: false,
    labelSearchButton: '',
    searchPlaceholder: '',
};

const defaultOrder: OrderSettings = {
    enableOrder: false,
    sortRows: [],
};

/** Rows the Order tab seeds the first time sorting is enabled. */
export const SEED_SORT_ROWS = [
    {label: 'Newest first', orderBy: 'date', direction: 'desc' as const},
    {label: 'Oldest first', orderBy: 'date', direction: 'asc' as const},
    {label: 'Title A–Z', orderBy: 'title', direction: 'asc' as const},
    {label: 'Title Z–A', orderBy: 'title', direction: 'desc' as const},
];

/** Wraps a value in a shortcode attribute, skipping empty strings. */
function attr(name: string, value: string | number | boolean | undefined): string {
    if (value === undefined || value === '' || value === false) {
        return '';
    }
    const stringValue = value === true ? 'true' : String(value);
    return ` ${name}="${stringValue.replace(/"/g, '&quot;')}"`;
}

export function useShortcodeBuilder() {
    const [main, setMain] = useState<MainSettings>(defaultMain);
    const [classes, setClasses] = useState<ClassSettings>(defaultClasses);
    const [filters, setFilters] = useState<FilterSettings>(defaultFilters);
    const [search, setSearch] = useState<SearchSettings>(defaultSearch);
    const [order, setOrder] = useState<OrderSettings>(defaultOrder);

    const state: BuilderState = {main, classes, filters, search, order};

    const filterId = `${main.postType}_filter`;
    const sortRows = useMemo(
        () =>
            order.enableOrder
                ? order.sortRows.filter((r) => r.orderBy && r.label.trim())
                : [],
        [order]
    );
    const hasFilters = filters.filterByCategory || search.enableSearch || sortRows.length > 0;

    const postsShortcode = useMemo(() => {
        let sc = '[all_posts_ajax';
        sc += attr('post_type', main.postType);
        sc += attr('posts_per_page', main.postsPerPage);
        sc += attr('type_pagination', main.typePagination);
        sc += attr('row_classes', classes.rowClasses);
        sc += attr('load_more_label', main.loadMoreLabel);
        sc += attr('load_more_classes', classes.loadMoreClasses);
        sc += attr('prev_text', main.prevText);
        sc += attr('next_text', main.nextText);
        if (!main.syncFiltersUrl) {
            sc += ' sync_filters_url="false"';
        }
        if (!main.syncPaginationUrl) {
            sc += ' sync_pagination_url="false"';
        }
        if (hasFilters) {
            sc += attr('filter_id', filterId);
        }
        sc += ']';
        return sc;
    }, [main, classes, hasFilters, filterId]);

    const filtersShortcode = useMemo(() => {
        if (!hasFilters) {
            return '';
        }

        let sc = '[all_posts_ajax_filters';
        if (filters.filterByCategory) {
            sc += attr('post_type', main.postType);
            sc += attr('filter_row_classes', filters.filterRowClasses);
            sc += attr('filter_item_classes', filters.filterItemClasses);
            sc += attr('filter_taxonomy', filters.filterTaxonomy.join(','));
            sc += attr('filter_type', filters.filterType);
            sc += attr('all_category_button', filters.allCategoryButton);
            sc += attr('filter_by_category', true);
            sc += attr('multiply_filter', filters.multiplyFilter);
            if (!filters.showFilterCount) {
                sc += ' show_filter_count="false"';
            }
            sc += attr('enable_clear_button', filters.enableClearButton);
            sc += attr('filter_titles', filters.enableFilterTitles);
        }
        if (search.enableSearch) {
            sc += attr('enable_search', true);
            sc += attr('label_search_button', search.labelSearchButton);
            sc += attr('search_placeholder', search.searchPlaceholder);
        }
        if (sortRows.length) {
            const encoded = sortRows
                .map(
                    (r) =>
                        `${r.orderBy}:${r.direction}:${r.label.replace(/\|/g, '/').trim()}`
                )
                .join('|');
            sc += attr('sort_options', encoded);
        }
        sc += attr('filter_id', filterId);
        sc += ']';
        return sc;
    }, [filters, search, sortRows, hasFilters, filterId]);

    return {
        state,
        setMain,
        setClasses,
        setFilters,
        setSearch,
        setOrder,
        postsShortcode,
        filtersShortcode,
        hasFilters,
    };
}
