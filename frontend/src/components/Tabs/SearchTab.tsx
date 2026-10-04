import type { Dispatch, SetStateAction } from 'react';
import type { SearchSettings } from '@/src/types';
import { Toggle } from '@web-revizor/ui-kit/components/Toggle';
import { SlideDown } from '@web-revizor/ui-kit/components/SlideDown';
import { Input } from '@web-revizor/ui-kit/components/Input';

interface Props {
  search: SearchSettings;
  setSearch: Dispatch<SetStateAction<SearchSettings>>;
}

export function SearchTab({ search, setSearch }: Props) {
  function update<K extends keyof SearchSettings>(
    key: K,
    value: SearchSettings[K]
  ) {
    setSearch((prev) => ({ ...prev, [key]: value }));
  }

  return (
    <div className='flex flex-col gap-4'>
      <Toggle
        size={'small'}
        label='Enable Search'
        value={search.enableSearch}
        onChange={(v) => update('enableSearch', v)}
      />

      <SlideDown isOpen={search.enableSearch}>
        {search.enableSearch && (
          <div className='flex flex-col gap-4'>
            <Input
              label='Label search button'
              name={'label_search_button'}
              id='label_search_button'
              value={search.labelSearchButton}
              placeholder='Search'
              onChange={(v) => update('labelSearchButton', v.target.value)}
            />

            <Input
              label='Search Placeholder'
              name={'search_placeholder'}
              id='search_placeholder'
              value={search.searchPlaceholder}
              placeholder='Search'
              onChange={(v) => update('searchPlaceholder', v.target.value)}
            />
          </div>
        )}
      </SlideDown>
    </div>
  );
}
