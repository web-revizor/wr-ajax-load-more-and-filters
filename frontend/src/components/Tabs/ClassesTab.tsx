import type {Dispatch, SetStateAction} from 'react';
import {ClassSettings} from "@/src/types";
import Input from "@/src/components/sharedComponents/Input/Input";

interface Props {
    classes: ClassSettings;
    setClasses: Dispatch<SetStateAction<ClassSettings>>;
}

export function ClassesTab({classes, setClasses}: Props) {
    function update<K extends keyof ClassSettings>(key: K, value: ClassSettings[K]) {
        setClasses((prev) => ({...prev, [key]: value}));
    }

    return (
        <div className="flex flex-col gap-4">
            <Input label={'Row classes (extra, added to .wr-posts__list)'}
                   id="row_classes"
                   name={'row_classes'}
                   value={classes.rowClasses}
                   placeholder="my-list-class"
                   onChange={(v) => update('rowClasses', v.target.value)}/>

            <Input label={'Load more button classes (extra)'}
                   id="load_more_classes"
                   name={'load_more_classes'}
                   value={classes.loadMoreClasses}
                   placeholder="my-button-class"
                   onChange={(v) => update('loadMoreClasses', v.target.value)}/>
        </div>
    );
}
