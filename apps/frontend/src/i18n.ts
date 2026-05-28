import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import de from './locales/de.json';

const savedLocale = localStorage.getItem('app_locale') || 'de';

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: {
    en,
    de
  }
});

export default i18n;
