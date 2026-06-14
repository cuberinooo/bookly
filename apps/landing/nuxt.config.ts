import { nxViteTsPaths } from '@nx/vite/plugins/nx-tsconfig-paths.plugin';
import { defineNuxtConfig } from 'nuxt/config';
import tailwindcss from '@tailwindcss/vite';
import Aura from '@primeuix/themes/aura';

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  workspaceDir: '../../',
  devtools: { enabled: true },
  modules: ['@primevue/nuxt-module'],
  nitro: {
    externals: {
      inline: ['vue', '@vue/server-renderer']
    }
  },
  primevue: {
    options: {
      theme: {
        preset: Aura,
        options: {
          prefix: 'p',
          darkModeSelector: 'system',
          cssLayer: false
        }
      }
    }
  },
  devServer: {
    host: '0.0.0.0',
    port: 4300,
  },
  typescript: {
    typeCheck: true,
    tsConfig: {
      extends: '../../../tsconfig.base.json', // Nuxt copies this string as-is to the `./.nuxt/tsconfig.json`, therefore it needs to be relative to that directory
    },
  },
  imports: {
    autoImport: true,
  },
  css: [
    '~/assets/css/styles.css',
    'primeicons/primeicons.css'
  ],
  vite: {
    plugins: [nxViteTsPaths(), tailwindcss()],
  },
  app: {
    head: {
      title: 'BooklyFit',
      link: [
        { rel: 'icon', type: 'image/png', href: '/logo.png' }
      ],
      meta: [
        { name: 'description', content: 'BooklyFit - Your ultimate gym and workout booking platform.' },
        { property: 'og:title', content: 'BooklyFit' },
        { property: 'og:description', content: 'BooklyFit - Your ultimate gym and workout booking platform.' },
        { property: 'og:image', content: '/logo.png' },
        { property: 'og:type', content: 'website' }
      ],
      script: [
        {
          src: '//gc.zgo.at/count.js',
          async: true,
          'data-goatcounter': 'https://codigncube.goatcounter.com/count'
        }
      ]
    }
  }
});


