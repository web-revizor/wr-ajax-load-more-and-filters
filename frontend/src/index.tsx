import { createRoot } from 'react-dom/client';
import './styles.scss';
import { App } from '@/src/components/App';
import { ToastProvider } from '@/src/context/ToastContext';
import { SettingsForm } from '@web-revizor/ui-kit/components/SettingsForm';
import type { ISettingsSchema } from '@web-revizor/ui-kit/components/SettingsForm';

const settings = window.wralmSettings;

if (settings) {
  const container = document.getElementById('wralm-console');
  if (container) {
    createRoot(container).render(<App settings={settings} />);
  }
}

const settingsRoot = document.querySelector<HTMLElement>('[data-wr-settings]');
if (settingsRoot?.dataset.wrSettings) {
  const schema = JSON.parse(settingsRoot.dataset.wrSettings) as ISettingsSchema;
  createRoot(settingsRoot).render(<SettingsForm schema={schema} />);
}

const toastRoot = document.createElement('div');
toastRoot.className = 'web-revizor-container';
document.body.appendChild(toastRoot);
createRoot(toastRoot).render(<ToastProvider />);
