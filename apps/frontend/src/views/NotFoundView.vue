<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const { t } = useI18n();

function goHome() {
  router.push('/');
}

function goDashboard() {
  router.push('/dashboard');
}
</script>

<template>
  <div class="not-found-container flex flex-col items-center justify-center min-h-[70vh] px-4 text-center select-none py-12">
    <!-- Icon Container with subtle pulse animation -->
    <div class="relative mb-6">
      <div class="absolute inset-0 bg-amber-500/10 blur-xl rounded-full scale-150 animate-pulse" />
      <div class="relative w-24 h-24 bg-gradient-to-b from-slate-800 to-slate-900 border border-slate-700/50 shadow-2xl rounded-2xl flex items-center justify-center text-amber-500">
        <!-- Dumbbell SVG Icon -->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="w-12 h-12"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M6 18H4a2 2 0 01-2-2v-4a2 2 0 012-2h2m12 8h2a2 2 0 002-2v-4a2 2 0 00-2-2h-2M6 12h12M9 8v8m6-8v8"
          />
        </svg>
      </div>
    </div>

    <!-- Error Header -->
    <h1 class="text-8xl font-black tracking-tighter mb-2 bg-gradient-to-r from-amber-500 to-orange-600 bg-clip-text text-transparent">
      404
    </h1>
    <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-4 uppercase tracking-wide">
      {{ t('notFound.title') }}
    </h2>

    <!-- Description -->
    <p class="text-slate-600 dark:text-slate-400 max-w-md mb-8 leading-relaxed text-sm">
      {{ t('notFound.description') }}
    </p>

    <!-- Recovery WOD Checklist -->
    <div class="w-full max-w-sm bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/80 rounded-2xl p-5 mb-8 text-left shadow-sm">
      <h3 class="text-xs primary-text uppercase tracking-wider text-slate-400 mb-3.5">
        {{ t('notFound.recoveryWod') }}
      </h3>
      <ul class="space-y-2.5 text-sm text-slate-700 dark:text-slate-300">
        <li class="flex items-center gap-3">
          <i class="pi pi-check-circle text-emerald-500 text-base" />
          <span>{{ t('notFound.wodStep1') }}</span>
        </li>
        <li class="flex items-center gap-3">
          <i class="pi pi-check-circle text-emerald-500 text-base" />
          <span>{{ t('notFound.wodStep2') }}</span>
        </li>
        <li class="flex items-center gap-3">
          <i class="pi pi-play-circle text-amber-500 text-base animate-pulse" />
          <span>{{ t('notFound.wodStep3') }}</span>
        </li>
      </ul>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap justify-center gap-4">
      <Button
        :label="t('notFound.backHome')"
        icon="pi pi-home"
        severity="primary"
        class="font-bold cursor-pointer transition-transform duration-200 hover:scale-[1.02] active:scale-[0.98]"
        @click="goHome"
      />
      <Button
        :label="t('notFound.goDashboard')"
        icon="pi pi-th-large"
        severity="secondary"
        variant="outlined"
        class="font-bold cursor-pointer bg-white dark:bg-slate-900 transition-transform duration-200 hover:scale-[1.02] active:scale-[0.98]"
        @click="goDashboard"
      />
    </div>
  </div>
</template>

<style scoped>
.not-found-container {
  min-height: calc(100vh - 200px);
}
</style>
