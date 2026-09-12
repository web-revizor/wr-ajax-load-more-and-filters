import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react';
import ToastContainer from '@/src/components/sharedComponents/ToastContainer/ToastContainer';
import {
  ToastProps,
  ToastType,
} from '@/src/components/sharedComponents/Toast/Toast';
import { onToast } from '@/src/utils/toastEmitter';

type AddToastParams = [message: string, type?: ToastType, duration?: number];

interface ToastContextType {
  addToast: (...args: AddToastParams) => void;
  removeToast: (id: string) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export const ToastProvider = () => {
  const [toasts, setToasts] = useState<
    Omit<ToastProps, 'onRemove' | 'closeIconName'>[]
  >([]);
  const counterRef = useRef(0);

  const addToast = useCallback(
    (...[message, type = 'success', duration = 3000]: AddToastParams) => {
      const id = `toast-${++counterRef.current}`;
      setToasts((prev) => [...prev, { id, message, type, duration }]);
    },
    []
  );

  useEffect(
    () =>
      onToast(({ message, type, duration }) => {
        addToast(message, type, duration);
      }),
    [addToast]
  );

  const removeToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((toast) => toast.id !== id));
  }, []);

  return (
    <ToastContext.Provider value={{ addToast, removeToast }}>
      <ToastContainer toasts={toasts} onRemove={removeToast} />
    </ToastContext.Provider>
  );
};

export const useToast = (): ToastContextType => {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
};
