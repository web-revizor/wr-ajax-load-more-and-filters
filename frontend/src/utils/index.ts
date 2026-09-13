import { showToast } from '@/src/utils/toastEmitter';
import { copyToClipboard } from '@web-revizor/ui-kit/utils/copyToClipboard';

type CopyTarget = (MouseEvent & { currentTarget: HTMLElement }) | string;
export function copyText(target?: CopyTarget, testToCopy?: string) {
  let text: string | undefined;

  if (target) {
    if (typeof target === 'string') {
      const element = document.querySelector(target) as HTMLElement | null;
      text = element?.innerText?.trim();
    } else {
      const el = target.currentTarget;
      text = el?.innerText?.trim();
    }
  } else {
    text = testToCopy;
  }

  if (!text) return;

  copyToClipboard(text).then((result) => {
    if (result === 'copied') {
      showToast({ message: 'Copied!', type: 'success' });
    } else if (result === 'fallback') {
      showToast({ message: 'Copied via fallback!', type: 'success' });
    } else {
      showToast({ message: 'Copy fallback failed!', type: 'error' });
    }
  });
}
