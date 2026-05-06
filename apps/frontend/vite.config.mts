/// <reference types='vitest' />
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { nxViteTsPaths } from '@nx/vite/plugins/nx-tsconfig-paths.plugin';
import { nxCopyAssetsPlugin } from '@nx/vite/plugins/nx-copy-assets.plugin';
import {fileURLToPath} from "node:url";

export default defineConfig(() => ({
  root: import.meta.dirname,
  cacheDir: '../../node_modules/.vite/apps/frontend',
  server: {
    port: 4200,
    host: 'localhost',
  },
  preview: {
    port: 4300,
    host: 'localhost',
  },
  resolve: {
    alias: {
      // Maps '@' to the 'src' directory
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  plugins: [vue(), nxViteTsPaths(), nxCopyAssetsPlugin(['*.md']), VitePWA({
    registerType: 'autoUpdate',
    includeAssets: ['logo.png', 'manifest.json'], // These are in your public folder
    manifest: {
      name: 'Bookly',
      short_name: 'Bookly',
      description: 'Book your fitness courses at Bookly',
      theme_color: '#0f172a',
      background_color: '#0f172a',
      display: 'standalone',
      icons: [
        {
          src: 'logo.png',
          sizes: '192x192',
          type: 'image/png'
        },
        {
          src: 'logo.png',
          sizes: '512x512',
          type: 'image/png'
        }
      ]
    },
    workbox: {
      // This is the magic: it tells workbox to cache everything Vite builds
      globPatterns: ['**/*.{js,css,html,png,svg,ico}'],
      // Ensures that navigation (reloads) always fall back to index.html
      navigateFallback: 'index.html'
    }
  })],
  // Uncomment this if you are using workers.
  // worker: {
  //   plugins: () => [ nxViteTsPaths() ],
  // },
  build: {
    outDir: '../../dist/apps/frontend',
    emptyOutDir: true,
    reportCompressedSize: true,
    commonjsOptions: {
      transformMixedEsModules: true,
    },
  },
  test: {
    name: 'frontend',
    watch: false,
    globals: true,
    environment: 'jsdom',
    include: ['{src,tests}/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'],
    reporters: ['default'],
    coverage: {
      reportsDirectory: '../../coverage/apps/frontend',
      provider: 'v8' as const,
    },
  },
}));
