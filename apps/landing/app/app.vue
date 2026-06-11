<script setup lang="ts">
import CookieConsent from './components/CookieConsent.vue';
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 selection:bg-amber-500 selection:text-slate-900 antialiased font-sans">
    <NuxtPage />
    <CookieConsent />
  </div>
</template>

<style>
html, body {
  margin: 0;
  padding: 0;
  background-color: #020617;
}
</style>

