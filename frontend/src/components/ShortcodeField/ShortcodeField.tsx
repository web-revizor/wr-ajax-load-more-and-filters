import { Input } from '@web-revizor/ui-kit/components/Input';
import { Button } from '@web-revizor/ui-kit/components/Button';
import { Icon } from '@/src/components/sharedComponents/Icon';
import { copyText } from '@/src/utils';

interface Props {
  value: string;
}

export function ShortcodeField({ value }: Props) {
  async function handleCopy() {
    copyText(undefined, value);
  }

  return (
    <div className='flex gap-5'>
      <Input
        wrapperClassName={'flex-1 max-w-[500px]'}
        type='text'
        readOnly
        value={value}
        title='Copy'
        onClick={handleCopy}
      />
      <Button
        variant={'clear'}
        size={'clear'}
        type='button'
        title='Copy'
        onClick={handleCopy}
        className={'coloredText'}
      >
        <Icon name={'common/copy'} width={24} height={24} />
      </Button>
    </div>
  );
}
