import type { Dispatch, SetStateAction } from 'react';
import type { OrderSettings } from '@/src/types';
import Toggle from '@/src/components/sharedComponents/Toggle/Toggle';
import SlideDown from '@/src/components/sharedComponents/SlideDown/SlideDown';
import Input from '@/src/components/sharedComponents/Input/Input';
import Select from '@/src/components/sharedComponents/Select/Select';

interface Props {
  order: OrderSettings;
  setOrder: Dispatch<SetStateAction<OrderSettings>>;
}

const ORDERBY_KEYS: { value: string; label: string }[] = [
  { value: 'date', label: 'Date' },
  { value: 'title', label: 'Title' },
  { value: 'menu_order', label: 'Menu order' },
  { value: 'rand', label: 'Random' },
  { value: 'price', label: 'Price (WooCommerce)' },
  { value: 'popularity', label: 'Popularity (WooCommerce)' },
  { value: 'rating', label: 'Rating (WooCommerce)' },
];

export function OrderTab({ order, setOrder }: Props) {
  function update<K extends keyof OrderSettings>(
    key: K,
    value: OrderSettings[K]
  ) {
    setOrder((prev) => ({ ...prev, [key]: value }));
  }

  return (
    <div className='flex flex-col gap-4'>
      <Toggle
        size={'small'}
        label='Enable Order'
        value={order.enableOrder}
        onChange={(v) => update('enableOrder', v)}
      />
      <SlideDown isOpen={order.enableOrder}>
        {order.enableOrder && (
          <div className='flex flex-col gap-4'>
            <Input
              label='Label Newest'
              name={'label_newest_order'}
              id='label_newest_order'
              value={order.labelNewestOrder}
              placeholder='Newest First'
              onChange={(v) => update('labelNewestOrder', v.target.value)}
            />

            <Input
              label='Label Old'
              name={'label_old_order'}
              id='label_old_order'
              value={order.labelOldOrder}
              placeholder='Old First'
              onChange={(v) => update('labelOldOrder', v.target.value)}
            />

            <Select
              label='Order-by options (optional)'
              multiple
              value={order.orderByOptions}
              onChange={(v) =>
                update('orderByOptions', Array.isArray(v) ? v : [v])
              }
              options={ORDERBY_KEYS}
            />

            <Input
              label='Order-by labels (csv, parallel to options)'
              name='order_by_labels'
              id='order_by_labels'
              value={order.orderByLabels}
              placeholder='Newest,By title'
              onChange={(v) => update('orderByLabels', v.target.value)}
            />
          </div>
        )}
      </SlideDown>
    </div>
  );
}
