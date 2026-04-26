import vue from 'eslint-plugin-vue';
import baseConfig from '../../eslint.config.mjs';
import pluginVue from 'eslint-plugin-vue';

export default [
  ...baseConfig,
  ...vue.configs['flat/recommended'],
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: await import('@typescript-eslint/parser'),
      },
    },
  },
  {
    files: ['**/*.ts', '**/*.tsx', '**/*.js', '**/*.jsx', '**/*.vue'],
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },
  {
    files: ['**/*.vue', '**/main.ts'],
    rules: {
      'vue/no-reserved-component-names': 'off',
    },
  },
];
