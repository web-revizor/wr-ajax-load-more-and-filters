import type { Dispatch, SetStateAction } from 'react';
import { FilterSettings, FilterType } from '@/src/types';

import Toggle from '@/src/components/sharedComponents/Toggle/Toggle';
import Select from '@/src/components/sharedComponents/Select/Select';
import Input from '@/src/components/sharedComponents/Input/Input';
import SlideDown from '@/src/components/sharedComponents/SlideDown/SlideDown';

interface Props {
  taxonomies: string[];
  filters: FilterSettings;
  setFilters: Dispatch<SetStateAction<FilterSettings>>;
}

const FILTER_TYPE_OPTIONS: { value: FilterType; label: string }[] = [
  { value: 'button', label: 'Button' },
  { value: 'select', label: 'Select' },
];

export function FiltersTab({ taxonomies, filters, setFilters }: Props) {
  function update<K extends keyof FilterSettings>(
    key: K,
    value: FilterSettings[K]
  ) {
    setFilters((prev) => ({ ...prev, [key]: value }));
  }

  function handleTaxonomyChange(value: string | string[]) {
    update('filterTaxonomy', Array.isArray(value) ? value : [value]);
  }

  const allTaxonomies = [
    'category',
    'post_tag',
    ...taxonomies.filter((t) => t !== 'category' && t !== 'post_tag'),
  ];

  return (
    <div className='flex flex-col gap-4'>
      <Toggle
        size={'small'}
        label={'Filter by category'}
        value={filters.filterByCategory}
        onChange={(v) => update('filterByCategory', v)}
      />

      <SlideDown isOpen={filters.filterByCategory}>
        {filters.filterByCategory && (
          <div className='flex flex-col gap-4'>
            <Select
              id='filter_taxonomy'
              className='wralm-input'
              multiple
              value={filters.filterTaxonomy}
              options={allTaxonomies.map((tax) => ({
                value: tax,
                label:
                  tax.charAt(0).toUpperCase() +
                  tax.slice(1).replace(/[_-]/g, ' '),
              }))}
              onChange={handleTaxonomyChange}
            />

            <Toggle
              size={'small'}
              label={'Enable Clear Button'}
              value={filters.enableClearButton}
              onChange={(v) => update('enableClearButton', v)}
            />

            <Input
              label='Filter row classes (extra, added to .wr-filters__list)'
              name={'filter_row_classes'}
              id='filter_row_classes'
              value={filters.filterRowClasses}
              placeholder='my-row-class'
              onChange={(v) => update('filterRowClasses', v.target.value)}
            />

            <Select
              label='Filter Type'
              name={'filter_type'}
              id='filter_type'
              value={filters.filterType}
              onChange={(v) => update('filterType', v as FilterType)}
              options={FILTER_TYPE_OPTIONS}
            />

            <Toggle
              size={'small'}
              label='Enable Filter Titles'
              value={filters.enableFilterTitles}
              onChange={(v) => update('enableFilterTitles', v)}
            />

            <Toggle
              size={'small'}
              label={'Enable Multiply Filter'}
              value={filters.multiplyFilter}
              onChange={(v) => update('multiplyFilter', v)}
            />

            <Toggle
              size={'small'}
              label={'Show post count on buttons'}
              value={filters.showFilterCount}
              onChange={(v) => update('showFilterCount', v)}
            />

            <Input
              label='Filter item classes (extra, added to each .wr-filters__item)'
              name={'filter_item_classes'}
              id='filter_item_classes'
              value={filters.filterItemClasses}
              placeholder='my-item-class'
              onChange={(v) => update('filterItemClasses', v.target.value)}
            />

            <Input
              label='Label all category button'
              name={'all_category_button'}
              id='all_category_button'
              value={filters.allCategoryButton}
              placeholder='All'
              onChange={(v) => update('allCategoryButton', v.target.value)}
            />
          </div>
        )}
      </SlideDown>
    </div>
  );
}
