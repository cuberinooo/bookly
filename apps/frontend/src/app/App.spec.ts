import { describe, it, expect, vi } from 'vitest';
import router from '../router';
import { mount } from '@vue/test-utils';
import App from './App.vue';
import { createPinia, setActivePinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';

// Mock i18n
vi.mock('vue-i18n', async (importOriginal) => {
  const actual = await importOriginal() as any;
  return {
    ...actual,
    useI18n: () => ({
      t: (key: string) => key,
      locale: { value: 'en' }
    })
  };
});

describe('App', () => {
  it('renders properly', async () => {
    const pinia = createPinia();
    setActivePinia(pinia);

    const wrapper = mount(App, {
      global: {
        plugins: [
          router,
          pinia,
          PrimeVue,
          ToastService,
          ConfirmationService
        ],
        mocks: {
          t: (key: string) => key,
          $t: (key: string) => key
        },
        stubs: {
          Toast: true,
          ConfirmDialog: true,
          Select: true,
          Button: true,
          ToggleButton: true,
          Menu: true,
          Divider: true,
          Password: true,
          InputText: true,
          Dialog: true,
          RouterView: true
        }
      }
    });
    await router.isReady();
    expect(wrapper.exists()).toBe(true);
  });
});
