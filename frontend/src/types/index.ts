export type PaginationType = 'default' | 'list' | 'both' | 'none';
export type FilterType = 'button' | 'select';

export interface MainSettings {
  postType: string;
  postsPerPage: number;
  typePagination: PaginationType;
  loadMoreLabel: string;
  prevText: string;
  nextText: string;
  syncFiltersUrl: boolean;
  syncPaginationUrl: boolean;
}

export interface ClassSettings {
  rowClasses: string;
  loadMoreClasses: string;
}

export interface FilterSettings {
  filterByCategory: boolean;
  enableClearButton: boolean;
  filterType: FilterType;
  enableFilterTitles: boolean;
  multiplyFilter: boolean;
  showFilterCount: boolean;
  filterRowClasses: string;
  filterItemClasses: string;
  filterTaxonomy: string[];
  allCategoryButton: string;
}

export interface SearchSettings {
  enableSearch: boolean;
  labelSearchButton: string;
  searchPlaceholder: string;
}

export interface OrderSettings {
  enableOrder: boolean;
  labelNewestOrder: string;
  labelOldOrder: string;
  orderByOptions: string[];
  orderByLabels: string;
}

export interface BuilderState {
  main: MainSettings;
  classes: ClassSettings;
  filters: FilterSettings;
  search: SearchSettings;
  order: OrderSettings;
}

export type TabId = 'main' | 'classes' | 'filters' | 'search' | 'order';
