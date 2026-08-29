import type { Dispatch, SetStateAction } from 'react';
import type { MainSettings, PaginationType } from '@/src/types';
import Input from '@/src/components/sharedComponents/Input/Input';
import Select from '@/src/components/sharedComponents/Select/Select';
import Toggle from '@/src/components/sharedComponents/Toggle/Toggle';

interface Props {
  postTypes: string[];
  main: MainSettings;
  setMain: Dispatch<SetStateAction<MainSettings>>;
}

const PAGINATION_OPTIONS: { value: PaginationType; label: string }[] = [
  { value: 'default', label: 'Only button' },
  { value: 'list', label: 'List' },
  { value: 'both', label: 'List + button' },
  { value: 'none', label: 'None' },
];

export function MainTab({ postTypes, main, setMain }: Props) {
  function update<K extends keyof MainSettings>(
    key: K,
    value: MainSettings[K]
  ) {
    setMain((prev) => ({ ...prev, [key]: value }));
  }

  return (
    <div className='flex flex-col gap-4'>
      <Select
        label='Post Type'
        name={'post_type_load_more'}
        id='post_type_load_more'
        value={main.postType}
        onChange={(v) => update('postType', v as string)}
        options={postTypes.map((pt) => ({
          value: pt,
          label: pt.charAt(0).toUpperCase() + pt.slice(1),
        }))}
        searchable={true}
      />

      {main.postType === 'product' && (
        <p className='text-[12px] text-white/60'>
          WooCommerce detected on the server: catalog-hidden / out-of-stock
          products are excluded automatically; use the Order tab for price /
          popularity / rating sorting.
        </p>
      )}

      <Select
        label='Type Pagination'
        name={'type_pagination'}
        id='type_pagination'
        value={main.typePagination}
        onChange={(v) => update('typePagination', v as PaginationType)}
        options={PAGINATION_OPTIONS}
      />
      <Input
        label={'Count Post Per page'}
        name={'count_post_per_page'}
        type={'number'}
        id='count_post_per_page'
        min={-1}
        value={main.postsPerPage.toString()}
        onChange={(v) => update('postsPerPage', parseFloat(v.target.value))}
      />

      <Input
        label={'Load more label'}
        name={'load_more_label'}
        id='load_more_label'
        placeholder='Load more'
        value={main.loadMoreLabel}
        onChange={(v) => update('loadMoreLabel', v.target.value)}
      />

      <Input
        label={'Previous label'}
        name={'prev_text'}
        id='prev_text'
        placeholder='Previous'
        value={main.prevText}
        onChange={(v) => update('prevText', v.target.value)}
      />

      <Input
        label={'Previous label'}
        name={'next_text'}
        id='next_text'
        placeholder='Next'
        value={main.nextText}
        onChange={(v) => update('nextText', v.target.value)}
      />

      <Toggle
        size='small'
        label='Sync filters to URL'
        value={main.syncFiltersUrl}
        onChange={(v) => update('syncFiltersUrl', v)}
      />
      <p className='text-[12px] text-white/60'>
        Off = filtering / search / sorting never touch the address bar.
      </p>

      <Toggle
        size='small'
        label='Sync pagination to URL'
        value={main.syncPaginationUrl}
        onChange={(v) => update('syncPaginationUrl', v)}
      />
      <p className='text-[12px] text-white/60'>
        Off = pagination links become inert anchors and the address bar keeps
        no page number.
      </p>
    </div>
  );
}
