import React from 'react';
import { ToastContainer as SharedToastContainer } from '@web-revizor/ui-kit/components/ToastContainer';
import { ToastProps } from '@web-revizor/ui-kit/components/Toast';

interface Props {
  toasts: Omit<ToastProps, 'onRemove' | 'closeIconName'>[];
  onRemove: (id: string) => void;
}

const ToastContainer: React.FC<Props> = ({ toasts, onRemove }) => (
  <SharedToastContainer
    toasts={toasts}
    onRemove={onRemove}
    closeIconName={'common/close-toast'}
    closeIconSize={16}
    closeButtonClassName='coloredText absolute right-2 top-1/2 -translate-y-1/2'
  />
);

export default React.memo(ToastContainer);
