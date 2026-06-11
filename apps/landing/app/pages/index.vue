<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const { t, locale } = useI18n()
const showLegal = ref(false)

// SEO Meta configuration
useSeoMeta({
  title: 'BooklyFit - Manage Your Fitness Community',
  ogTitle: 'BooklyFit - Manage Your Fitness Community',
  description: 'The modern SaaS platform for boutique gyms, CrossFit boxes, and fitness studios. Simplify class bookings, track attendance, and manage payments effortlessly.',
  ogDescription: 'The modern SaaS platform for boutique gyms, CrossFit boxes, and fitness studios. Simplify class bookings, track attendance, and manage payments effortlessly.',
  ogImage: '/logo.png',
  twitterCard: 'summary_large_image',
})

// Anchor scroll helper
const scrollTo = (id: string) => {
  const el = document.getElementById(id)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
  }
}

// Lightbox state
const activeLightboxImage = ref<string | null>(null)
const activeLightboxAlt = ref<string>('')

const openLightbox = (src: string, alt: string) => {
  activeLightboxImage.value = src
  activeLightboxAlt.value = alt
}

const closeLightbox = () => {
  activeLightboxImage.value = null
  activeLightboxAlt.value = ''
}

// Keyboard listener for Escape key
const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    closeLightbox()
  }
}

watch(locale, (newLocale) => {
  if (import.meta.client) {
    localStorage.setItem('app_locale', newLocale)
  }
})

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <div class="relative overflow-hidden">
    <!-- Background Gradient Grids -->
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(245,158,11,0.15),rgba(255,255,255,0))]"></div>
    <div class="absolute top-[800px] left-1/2 -translate-x-1/2 w-[1000px] h-[300px] -z-10 bg-amber-500/5 blur-[120px] rounded-full"></div>
    <div class="absolute top-[1800px] left-1/2 -translate-x-1/2 w-[1000px] h-[300px] -z-10 bg-amber-500/5 blur-[120px] rounded-full"></div>

    <!-- Navigation -->
    <header class="sticky top-0 z-50 backdrop-blur-md bg-slate-950/80 border-b border-slate-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <img src="/logo.png" alt="BooklyFit Logo" class="w-15 h-15 object-contain rounded-xl shadow-[0_0_20px_rgba(245,158,11,0.3)]" />
          <span class="text-xl font-black uppercase tracking-wider text-white">
            Bookly<span class="text-amber-500">Fit</span>
          </span>
        </div>

        <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-300">
          <button @click="scrollTo('how-it-works')" class="hover:text-amber-500 transition cursor-pointer">{{ t('nav.howItWorks') }}</button>
          <button @click="scrollTo('admin-tools')" class="hover:text-amber-500 transition cursor-pointer">{{ t('nav.features') }}</button>
        </nav>

        <div class="flex items-center gap-4">
          <div class="lang-switcher">
            <Select
              v-model="locale"
              :options="[
                { label: 'EN', value: 'en' },
                { label: 'DE', value: 'de' },
              ]"
              option-label="label"
              option-value="value"
              class="lang-select"
            />
          </div>

          <a
            href="https://app.booklyfit.de"
            target="_blank"
            class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-amber-500 text-slate-950 font-bold text-sm tracking-wide shadow-[0_0_20px_rgba(245,158,11,0.2)] hover:bg-amber-400 hover:shadow-[0_0_25px_rgba(245,158,11,0.4)] transition duration-200"
          >
            {{ t('nav.goToApp') }} <i class="pi pi-arrow-up-right ml-2 text-xs"></i>
          </a>
        </div>
      </div>
    </header>

    <!-- Hero Section -->
    <section class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-24 md:pt-28 md:pb-32 text-center">
      <div class="max-w-3xl mx-auto space-y-6">
        <h1 class="text-5xl sm:text-6xl md:text-7xl font-extrabold tracking-tight text-white leading-tight">
          {{ t('hero.title1') }} <br class="hidden sm:inline" />
          <span class="bg-clip-text text-transparent bg-gradient-to-r bg-amber-500">
            {{ t('hero.title2') }}
          </span>
        </h1>
        <p class="justify-self-center text-lg sm:text-xl text-slate-400 font-medium max-w-2xl mx-auto leading-relaxed">
          {{ t('hero.subtitle') }}
        </p>
        <div class="pt-6 flex flex-col sm:flex-row items-center justify-center gap-4">
          <a
            href="https://app.booklyfit.de"
            target="_blank"
            class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 rounded-xl bg-gradient-to-r bg-amber-500 text-slate-950 font-black tracking-wide shadow-lg shadow-amber-500/10 hover:shadow-amber-500/20 hover:scale-[1.02] transition duration-200"
          >
            {{ t('hero.launchApp') }}
          </a>
          <button
            @click="scrollTo('how-it-works')"
            class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 font-bold hover:bg-slate-800/80 hover:text-white transition duration-200 cursor-pointer"
          >
            {{ t('hero.seeHow') }}
          </button>
        </div>
      </div>
    </section>

    <!-- How It Works Section (B2C / Member Journey) -->
    <section id="how-it-works" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 border-t border-slate-900">
      <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
        <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">{{ t('memberExperience.badge') }}</h2>
        <p class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">{{ t('memberExperience.title') }}</p>
        <p class="justify-self-center text-slate-400 max-w-xl mx-auto">
          {{ t('memberExperience.subtitle') }}
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <!-- Step 1 -->
        <div class="group flex flex-col justify-between p-6 rounded-2xl bg-slate-900/50 border border-slate-800 hover:border-amber-500/30 transition duration-300 shadow-xl">
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-amber-500 uppercase tracking-wider bg-amber-500/5 px-2.5 py-1 rounded-md border border-amber-500/10">{{ t('memberExperience.step1.badge') }}</span>
              <i class="pi pi-calendar text-slate-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-white">{{ t('memberExperience.step1.title') }}</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              {{ t('memberExperience.step1.desc') }}
            </p>
          </div>
          <!-- Browser + Phone Mockup -->
          <div class="mt-8 relative h-56 sm:h-64 transition duration-300">
            <!-- Desktop Browser Mockup -->
            <div
              class="absolute top-0 left-0 w-[80%] rounded-xl border border-slate-800 bg-slate-950 p-1.5 shadow-2xl cursor-zoom-in hover:scale-[1.04] hover:z-20 hover:border-amber-500/30 transition-all duration-300"
              @click="openLightbox('/screenshots/schedule.png', t('memberExperience.step1.desktopAlt'))"
            >
              <div class="flex items-center gap-1.5 pb-2 px-1 border-b border-slate-900 mb-2">
                <div class="w-1.5 h-1.5 rounded-full bg-red-500/60"></div>
                <div class="w-1.5 h-1.5 rounded-full bg-yellow-500/60"></div>
                <div class="w-1.5 h-1.5 rounded-full bg-green-500/60"></div>
              </div>
              <div class="aspect-[16/10] relative overflow-hidden rounded bg-slate-900">
                <img
                  src="/screenshots/schedule.png"
                  :alt="t('memberExperience.step1.desktopAlt')"
                  class="w-full h-full object-cover object-top select-none"
                  @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=600&auto=format&fit=crop'"
                />
              </div>
            </div>
            <!-- Mobile Phone Mockup -->
            <div
              class="absolute bottom-0 right-2 w-[35%] rounded-2xl border-[3px] border-slate-800 bg-slate-950 p-1 shadow-2xl z-10 cursor-zoom-in hover:scale-[1.06] hover:z-20 hover:border-amber-500/50 transition-all duration-300"
              @click="openLightbox('/screenshots/mobile_schedule.png', t('memberExperience.step1.mobileAlt'))"
            >
              <div class="w-8 h-2 bg-slate-800 rounded-full mx-auto mb-1"></div>
              <div class="aspect-[9/16] relative overflow-hidden rounded-lg bg-slate-900">
                <img
                  src="/screenshots/mobile_schedule.png"
                  :alt="t('memberExperience.step1.mobileAlt')"
                  class="w-full h-full object-cover object-top select-none"
                  @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=300&auto=format&fit=crop'"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="group flex flex-col justify-between p-6 rounded-2xl bg-slate-900/50 border border-slate-800 hover:border-amber-500/30 transition duration-300 shadow-xl">
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-amber-500 uppercase tracking-wider bg-amber-500/5 px-2.5 py-1 rounded-md border border-amber-500/10">{{ t('memberExperience.step2.badge') }}</span>
              <i class="pi pi-check-circle text-slate-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-white">{{ t('memberExperience.step2.title') }}</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              {{ t('memberExperience.step2.desc') }}
            </p>
          </div>
          <!-- Browser Mockup -->
          <div
            class="mt-8 rounded-xl border border-slate-800 bg-slate-950 p-1.5 shadow-2xl cursor-zoom-in hover:scale-[1.04] hover:border-amber-500/30 transition-all duration-300"
            @click="openLightbox('/screenshots/booking.png', t('memberExperience.step2.alt'))"
          >
            <div class="flex items-center gap-1.5 pb-2 px-1 border-b border-slate-900 mb-2">
              <div class="w-2 h-2 rounded-full bg-red-500/60"></div>
              <div class="w-2 h-2 rounded-full bg-yellow-500/60"></div>
              <div class="w-2 h-2 rounded-full bg-green-500/60"></div>
            </div>
            <div class="aspect-video relative overflow-hidden rounded bg-slate-900">
              <img
                src="/screenshots/booking.png"
                :alt="t('memberExperience.step2.alt')"
                class="w-full h-full object-cover object-top select-none"
                @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=600&auto=format&fit=crop'"
              />
            </div>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="group flex flex-col justify-between p-6 rounded-2xl bg-slate-900/50 border border-slate-800 hover:border-amber-500/30 transition duration-300 shadow-xl">
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-amber-500 uppercase tracking-wider bg-amber-500/5 px-2.5 py-1 rounded-md border border-amber-500/10">{{ t('memberExperience.step3.badge') }}</span>
              <i class="pi pi-trophy text-slate-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-white">{{ t('memberExperience.step3.title') }}</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              {{ t('memberExperience.step3.desc') }}
            </p>
          </div>
          <!-- Browser Mockup -->
          <div
            class="mt-8 rounded-xl border border-slate-800 bg-slate-950 p-1.5 shadow-2xl cursor-zoom-in hover:scale-[1.04] hover:border-amber-500/30 transition-all duration-300"
            @click="openLightbox('/screenshots/leaderboard.png', t('memberExperience.step3.alt'))"
          >
            <div class="flex items-center gap-1.5 pb-2 px-1 border-b border-slate-900 mb-2">
              <div class="w-2 h-2 rounded-full bg-red-500/60"></div>
              <div class="w-2 h-2 rounded-full bg-yellow-500/60"></div>
              <div class="w-2 h-2 rounded-full bg-green-500/60"></div>
            </div>
            <div class="aspect-video relative overflow-hidden rounded bg-slate-900">
              <img
                src="/screenshots/leaderboard.png"
                :alt="t('memberExperience.step3.alt')"
                class="w-full h-full object-cover object-top select-none"
                @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=600&auto=format&fit=crop'"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Built for Gym Owners & Admins Section -->
    <section id="admin-tools" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 border-t border-slate-900 bg-slate-950">
      <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
        <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">{{ t('businessDashboard.badge') }}</h2>
        <p class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">{{ t('businessDashboard.title') }}</p>
        <p class="justify-self-center text-slate-400 max-w-xl mx-auto">
          {{ t('businessDashboard.subtitle') }}
        </p>
      </div>

      <!-- Feature 1: Payments -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center mb-20">
        <div class="lg:col-span-5 space-y-5">
          <div class="w-12 h-12 rounded-xl bg-green-500/10 border border-green-500/20 text-green-500 flex items-center justify-center">
            <i class="pi pi-credit-card text-xl"></i>
          </div>
          <h3 class="text-2xl font-black text-white">{{ t('businessDashboard.payments.title') }}</h3>
          <p class="text-slate-400 leading-relaxed">
            {{ t('businessDashboard.payments.desc') }}
          </p>
          <ul class="space-y-3 text-sm font-semibold text-slate-300">
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-green-500 text-xs"></i> {{ t('businessDashboard.payments.b1') }}
            </li>
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-green-500 text-xs"></i> {{ t('businessDashboard.payments.b2') }}
            </li>
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-green-500 text-xs"></i> {{ t('businessDashboard.payments.b3') }}
            </li>
          </ul>
        </div>
        <div
          class="lg:col-span-7 rounded-2xl border border-slate-800 bg-slate-900/40 p-3 shadow-2xl cursor-zoom-in hover:scale-[1.03] hover:border-amber-500/30 transition-all duration-300"
          @click="openLightbox('/screenshots/payments.png', t('businessDashboard.payments.alt'))"
        >
          <div class="flex items-center gap-1.5 pb-2.5 px-2 border-b border-slate-800/80 mb-3">
            <div class="w-3 h-3 rounded-full bg-red-500/60"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-500/60"></div>
            <div class="w-3 h-3 rounded-full bg-green-500/60"></div>
            <span class="text-xs text-slate-500 font-mono ml-4">admin@booklyfit.de - Payments Overview</span>
          </div>
          <div class="relative overflow-hidden rounded bg-slate-950 border border-slate-800">
            <img
              src="/screenshots/payments.png"
              :alt="t('businessDashboard.payments.alt')"
              class="w-full object-cover select-none"
              @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?q=80&w=800&auto=format&fit=crop'"
            />
          </div>
        </div>
      </div>

      <!-- Feature 2: User Management -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center mb-20">
        <div class="lg:col-span-7 lg:order-2 space-y-5">
          <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center">
            <i class="pi pi-users text-xl"></i>
          </div>
          <h3 class="text-2xl font-black text-white">{{ t('businessDashboard.users.title') }}</h3>
          <p class="text-slate-400 leading-relaxed">
            {{ t('businessDashboard.users.desc') }}
          </p>
          <ul class="space-y-3 text-sm font-semibold text-slate-300">
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-amber-500 text-xs"></i> {{ t('businessDashboard.users.b1') }}
            </li>
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-amber-500 text-xs"></i> {{ t('businessDashboard.users.b2') }}
            </li>
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-amber-500 text-xs"></i> {{ t('businessDashboard.users.b3') }}
            </li>
          </ul>
        </div>
        <div
          class="lg:col-span-5 lg:order-1 rounded-2xl border border-slate-800 bg-slate-900/40 p-3 shadow-2xl cursor-zoom-in hover:scale-[1.03] hover:border-amber-500/30 transition-all duration-300"
          @click="openLightbox('/screenshots/users.png', t('businessDashboard.users.alt'))"
        >
          <div class="flex items-center gap-1.5 pb-2.5 px-2 border-b border-slate-800/80 mb-3">
            <div class="w-3 h-3 rounded-full bg-red-500/60"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-500/60"></div>
            <div class="w-3 h-3 rounded-full bg-green-500/60"></div>
            <span class="text-xs text-slate-500 font-mono ml-4">admin@booklyfit.de - User Management</span>
          </div>
          <div class="relative overflow-hidden rounded bg-slate-950 border border-slate-800">
            <img
              src="/screenshots/users.png"
              :alt="t('businessDashboard.users.alt')"
              class="w-full object-cover select-none"
              @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=800&auto=format&fit=crop'"
            />
          </div>
        </div>
      </div>

      <!-- Feature 3: Settings -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-5 space-y-5">
          <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-500 flex items-center justify-center">
            <i class="pi pi-cog text-xl"></i>
          </div>
          <h3 class="text-2xl font-black text-white">{{ t('businessDashboard.settings.title') }}</h3>
          <p class="text-slate-400 leading-relaxed">
            {{ t('businessDashboard.settings.desc') }}
          </p>
          <ul class="space-y-3 text-sm font-semibold text-slate-300">
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-blue-500 text-xs"></i> {{ t('businessDashboard.settings.b1') }}
            </li>
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-blue-500 text-xs"></i> {{ t('businessDashboard.settings.b2') }}
            </li>
            <li class="flex items-center gap-2">
              <i class="pi pi-check text-blue-500 text-xs"></i> {{ t('businessDashboard.settings.b3') }}
            </li>
          </ul>
        </div>
        <div
          class="lg:col-span-7 rounded-2xl border border-slate-800 bg-slate-900/40 p-3 shadow-2xl cursor-zoom-in hover:scale-[1.03] hover:border-amber-500/30 transition-all duration-300"
          @click="openLightbox('/screenshots/settings.png', t('businessDashboard.settings.alt'))"
        >
          <div class="flex items-center gap-1.5 pb-2.5 px-2 border-b border-slate-800/80 mb-3">
            <div class="w-3 h-3 rounded-full bg-red-500/60"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-500/60"></div>
            <div class="w-3 h-3 rounded-full bg-green-500/60"></div>
            <span class="text-xs text-slate-500 font-mono ml-4">admin@booklyfit.de - System Settings</span>
          </div>
          <div class="relative overflow-hidden rounded bg-slate-950 border border-slate-800">
            <img
              src="/screenshots/settings.png"
              :alt="t('businessDashboard.settings.alt')"
              class="w-full object-cover select-none"
              @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?q=80&w=800&auto=format&fit=crop'"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- Call to Action Banner -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
      <div class="rounded-3xl overflow-hidden bg-amber-500 px-4 py-12 shadow-2xl text-center">
        <h2 class="text-3xl sm:text-5xl font-black text-slate-950 relative z-10 leading-tight">
          {{ t('cta.title') }}
        </h2>
        <p class="justify-self-center text-slate-900 max-w-xl mx-auto font-medium text-base sm:text-lg relative z-10 mt-4">
          {{ t('cta.desc') }}
        </p>
        <div class="relative z-10 pt-8">
          <a
            href="https://app.booklyfit.de"
            target="_blank"
            class="inline-flex items-center justify-center px-8 py-4 rounded-xl bg-slate-950 text-white font-black hover:bg-slate-900 hover:scale-105 transition duration-200 shadow-xl"
          >
            {{ t('cta.btn') }}
          </a>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950 py-12 text-slate-500 text-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-3">
          <img src="/logo.png" alt="BooklyFit Logo" class="w-8 h-8 object-contain rounded-lg bg-white p-0.5" />
          <span class="font-black uppercase tracking-wider text-slate-300">
            Bookly<span class="text-amber-500">Fit</span>
          </span>
        </div>
        <div class="flex items-center gap-8 font-semibold text-slate-400">
          <a href="javascript:void(0)" @click="showLegal = true" class="hover:text-amber-500 transition">{{ t('footer.legalNotice') }}</a>
          <a href="/datenschutzerklaerung.pdf" target="_blank" rel="noopener" class="hover:text-amber-500 transition">{{ t('footer.privacy') }}</a>
          <a href="mailto:kubilay.anil@codingcube.de" class="hover:text-amber-500 transition">{{ t('footer.contact') }}</a>
        </div>
        <div>
          &copy; {{ new Date().getFullYear() }} BooklyFit. {{ t('footer.rights') }}
        </div>
      </div>
    </footer>

    <!-- Lightbox Overlay -->
    <transition name="fade">
      <div
        v-if="activeLightboxImage"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/90 backdrop-blur-md p-4 sm:p-6 md:p-10 cursor-zoom-out"
        @click="closeLightbox"
      >
        <!-- Close Button "X" -->
        <button
          class="absolute top-6 right-6 w-12 h-12 flex items-center justify-center rounded-full bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition duration-200 cursor-pointer z-10 shadow-lg"
          @click.stop="closeLightbox"
          aria-label="Close image"
        >
          <i class="pi pi-times text-xl"></i>
        </button>

        <!-- Image Wrapper -->
        <div class="relative max-w-full max-h-full flex flex-col items-center justify-center animate-zoom-in" @click.stop>
          <img
            :src="activeLightboxImage"
            :alt="activeLightboxAlt"
            class="max-w-full max-h-[85vh] object-contain rounded-lg border border-slate-800 shadow-2xl select-none"
          />
          <p class="mt-4 text-sm font-semibold text-slate-400 tracking-wide text-center">
            {{ activeLightboxAlt }}
          </p>
        </div>
      </div>
    </transition>

    <!-- Impressum / Legal Notice Dialog -->
    <Dialog
      v-model:visible="showLegal"
      :header="t('footer.legalNotice')"
      :modal="true"
      :dismissable-mask="true"
      :breakpoints="{ '960px': '75vw', '641px': '95vw' }"
      :style="{ width: '50vw' }"
      class="legal-notice-dialog"
    >
      <div class="flex flex-col gap-6 py-4 text-slate-300">
        <p class="leading-relaxed">
          Kubilay Anil<br />
          IT-Dienstleistungen Kubilay Anil<br />
          Entwicklung, Vertrieb und Betrieb von Software, Web- und mobilen Applikationen (SaaS), Erbringung von IT-Dienstleistungen, IT-Beratung sowie der Betrieb von Webportalen.<br />
          Kreuzstr. 19<br />
          89160 Dornstadt
        </p>

        <div>
          <h3 class="font-extrabold text-white text-lg mb-2">Kontakt</h3>
          <p class="leading-relaxed">
            Telefon: 01627895106<br />
            E-Mail: <a href="mailto:kubilay.anil@codingcube.de" class="text-amber-500 hover:underline">kubilay.anil@codingcube.de</a>
          </p>
        </div>

        <div>
          <h3 class="font-extrabold text-white text-lg mb-2">Berufsbezeichnung und berufsrechtliche Regelungen</h3>
          <p class="leading-relaxed">
            Berufsbezeichnung:<br />
            Softwareentwickler
          </p>
          <p class="leading-relaxed mt-2">
            Verliehen in:<br />
            Deutschland
          </p>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end pt-4 border-t border-slate-800">
          <Button
            :label="locale === 'de' ? 'Schließen' : 'Close'"
            severity="primary"
            class="px-6 py-2.5 !bg-amber-500 !text-slate-950 hover:!bg-amber-400 !border-transparent font-black rounded-xl transition duration-200 shadow-md shadow-amber-500/10"
            @click="showLegal = false"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
/* Scoped fallback animations/styles if needed */
.bg-pb-card {
  transition: transform 0.2s ease, border-color 0.2s ease;
}

/* Lightbox Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes zoomIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.animate-zoom-in {
  animation: zoomIn 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

/* Lang switcher styles */
.lang-switcher :deep(.p-select) {
  background: rgba(255, 255, 255, 0.05) !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 12px !important;
  height: 38px !important;
  width: 80px !important;
  color: white !important;
  font-size: 0.85rem !important;
  transition: all 0.2s ease;
  align-items: center;
}

.lang-switcher :deep(.p-select):hover {
  background: rgba(255, 255, 255, 0.1) !important;
  border-color: #f59e0b !important;
}

.lang-switcher :deep(.p-select-label) {
  padding: 0 0 0 10px !important;
  display: flex !important;
  align-items: center !important;
  color: white !important;
}

.lang-switcher :deep(.p-select-dropdown) {
  color: rgba(255, 255, 255, 0.5) !important;
}

/* Legal notice dialog overrides */
:deep(.p-dialog.legal-notice-dialog) {
  background: #0f172a !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  color: #f8fafc !important;
  border-radius: 1.5rem !important;
}

:deep(.p-dialog.legal-notice-dialog .p-dialog-header) {
  background: #0f172a !important;
  color: #ffffff !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
  padding: 1.5rem 1.5rem 1rem 1.5rem !important;
}

:deep(.p-dialog.legal-notice-dialog .p-dialog-content) {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  padding: 1.5rem !important;
}

:deep(.p-dialog.legal-notice-dialog .p-dialog-footer) {
  background: #0f172a !important;
  padding: 1rem 1.5rem 1.5rem 1.5rem !important;
}

:deep(.p-dialog.legal-notice-dialog .p-dialog-close-button) {
  color: #94a3b8 !important;
  border-radius: 9999px !important;
  transition: all 0.2s ease !important;
}

:deep(.p-dialog.legal-notice-dialog .p-dialog-close-button:hover) {
  background: rgba(255, 255, 255, 0.05) !important;
  color: #ffffff !important;
}
</style>
