import { createI18n } from 'vue-i18n';
import en from '../locales/en.json';
import de from '../locales/de.json';

export default defineNuxtPlugin((nuxtApp) => {
  // Use a constant default locale on both server and client during initial render to prevent hydration mismatches
  const defaultLocale = 'de';

  const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: defaultLocale,
    fallbackLocale: 'en',
    messages: {
      en,
      de,
    },
  });

  nuxtApp.vueApp.use(i18n as any);

  // Safely detect and apply user language preferences after client-side hydration is complete
  nuxtApp.hook('app:mounted', () => {
    const savedLocale = localStorage.getItem('app_locale');
    if (savedLocale === 'de' || savedLocale === 'en') {
      i18n.global.locale.value = savedLocale as any;
    } else {
      const navLang = navigator.language || '';
      if (navLang.startsWith('en')) {
        i18n.global.locale.value = 'en';
      }
    }
  });

  return {
    provide: {
      i18n,
    },
  };
});

