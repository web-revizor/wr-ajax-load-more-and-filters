import type {Dispatch, SetStateAction} from 'react';
import type {OrderSettings, SortDirection} from '@/src/types';
import Toggle from '@/src/components/sharedComponents/Toggle/Toggle';
import SlideDown from '@/src/components/sharedComponents/SlideDown/SlideDown';
import Input from '@/src/components/sharedComponents/Input/Input';
import Select from '@/src/components/sharedComponents/Select/Select';
import Button from '@/src/components/sharedComponents/Button/Button';
import {SEED_SORT_ROWS} from '@/src/hooks/useShortcodeBuilder';

interface Props {
    order: OrderSettings;
    setOrder: Dispatch<SetStateAction<OrderSettings>>;
}

const SORT_KEYS: { value: string; label: string }[] = [
    {value: 'date', label: 'Date'},
    {value: 'title', label: 'Title'},
    {value: 'menu_order', label: 'Menu order'},
    {value: 'rand', label: 'Random'},
    {value: 'modified', label: 'Last modified'},
    {value: 'comment_count', label: 'Comment count'},
    {value: 'price', label: 'Price (WooCommerce)'},
    {value: 'popularity', label: 'Popularity (WooCommerce)'},
    {value: 'rating', label: 'Rating (WooCommerce)'},
];

const DIRECTIONS: { value: SortDirection; label: string }[] = [
    {value: 'desc', label: 'Descending'},
    {value: 'asc', label: 'Ascending'},
];

export function OrderTab({order, setOrder}: Props) {
    function toggleEnable(on: boolean) {
        setOrder((prev) => ({
            enableOrder: on,
            sortRows:
                on && prev.sortRows.length === 0
                    ? SEED_SORT_ROWS.map((r) => ({...r}))
                    : prev.sortRows,
        }));
    }

    function updateRow(index: number, patch: Partial<OrderSettings['sortRows'][number]>) {
        setOrder((prev) => ({
            ...prev,
            sortRows: prev.sortRows.map((row, i) =>
                i === index ? {...row, ...patch} : row
            ),
        }));
    }

    function removeRow(index: number) {
        setOrder((prev) => ({
            ...prev,
            sortRows: prev.sortRows.filter((_, i) => i !== index),
        }));
    }

    function addRow() {
        setOrder((prev) => ({
            ...prev,
            sortRows: [...prev.sortRows, {label: '', orderBy: 'date', direction: 'desc'}],
        }));
    }

    return (
        <div className='flex flex-col gap-4'>
            <Toggle
                size={'small'}
                label='Enable sorting'
                value={order.enableOrder}
                onChange={toggleEnable}
            />

            <SlideDown isOpen={order.enableOrder}>
                {order.enableOrder && (
                    <div className='flex flex-col gap-3'>
                        {order.sortRows.map((row, i) => (
                            <div key={i}
                                 className='flex flex-wrap items-end gap-2'>
                                <Input
                                    label={i === 0 ? 'Label' : undefined}
                                    value={row.label}
                                    placeholder='Newest first'
                                    onChange={(e) => updateRow(i, {label: e.target.value})}
                                    className={'min-w-52'}
                                />
                                <Select
                                    label={i === 0 ? 'Sort by' : undefined}
                                    value={row.orderBy}
                                    options={SORT_KEYS}
                                    onChange={(v) => updateRow(i, {orderBy: String(v)})}
                                    className={'w-56'}
                                />
                                <Select
                                    label={i === 0 ? 'Direction' : undefined}
                                    value={row.direction}
                                    options={DIRECTIONS}
                                    onChange={(v) => updateRow(i, {direction: v as SortDirection})}
                                    className={'w-36'}
                                />
                                <Button
                                    variant='secondary'
                                    size='smaller'
                                    onClick={() => removeRow(i)}
                                >
                                    Remove
                                </Button>
                            </div>
                        ))}

                        <div>
                            <Button variant='secondary'
                                    size='smaller'
                                    onClick={addRow}>
                                Add option
                            </Button>
                        </div>
                    </div>
                )}
            </SlideDown>
        </div>
    );
}
