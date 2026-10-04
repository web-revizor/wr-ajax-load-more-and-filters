import type {TabId} from '@/src/types';
import { Button } from "@web-revizor/ui-kit/components/Button";

interface TabDef {
    id: TabId;
    label: string;
}

const TABS: TabDef[] = [
    {id: 'main', label: 'Main'},
    {id: 'classes', label: 'Main classes'},
    {id: 'filters', label: 'Filters'},
    {id: 'search', label: 'Search'},
    {id: 'order', label: 'Order'},
];

interface Props {
    active: TabId;
    onChange: (tab: TabId) => void;
}

export function Tabs({active, onChange}: Props) {
    return (
        <div className="flex flex-col gap-4">
            {TABS.map((tab) => (
                <Button
                    key={tab.id}
                    variant={`${active === tab.id ? 'primary' : 'secondary'}`}
                    onClick={() => onChange(tab.id)}
                >
                    {tab.label}
                </Button>
            ))}
        </div>
    );
}
