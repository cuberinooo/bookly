<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import Divider from 'primevue/divider';

const { t } = useI18n();

const STORAGE_KEY = 'cookie-consent-choice';
const isVisible = ref(false);
const showModal = ref(false);

interface ConsentSettings {
  essential: boolean;
  functional: boolean;
  analytical: boolean;
  marketing: boolean;
}

const consent = ref<ConsentSettings>({
  essential: true,
  functional: true,
  analytical: false,
  marketing: false,
});

onMounted(() => {
  const savedChoice = localStorage.getItem(STORAGE_KEY);
  if (!savedChoice) {
    isVisible.value = true;
  } else {
    try {
      const parsed = JSON.parse(savedChoice);
      consent.value = { ...consent.value, ...parsed };
    } catch (e) {
      isVisible.value = true;
    }
  }
});

const acceptAll = () => {
  consent.value = {
    essential: true,
    functional: true,
    analytical: true,
    marketing: true,
  };
  saveChoice();
};

const rejectNonEssential = () => {
  consent.value = {
    essential: true,
    functional: false,
    analytical: false,
    marketing: false,
  };
  saveChoice();
};

const saveCustom = () => {
  saveChoice();
  showModal.value = false;
};

const saveChoice = () => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(consent.value));
  isVisible.value = false;
  // Trigger event for other components if needed (e.g., GTM load)
  window.dispatchEvent(new CustomEvent('cookie-consent-updated', { detail: consent.value }));
};
</script>

<template>
  <transition name="fade-slide">
    <div
      v-if="isVisible"
      role="dialog"
      aria-live="polite"
      aria-labelledby="cookie-title"
      aria-describedby="cookie-desc"
      class="cookie-banner-wrapper"
    >
      <div class="cookie-banner-content">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
          <div class="text-content">
            <h2
              id="cookie-title"
              class="text-lg font-bold primary-text mb-2 flex items-center gap-2"
            >
              <i class="pi pi-info-circle primary-text" />
              {{ t('cookies.title') }}
            </h2>
            <p
              id="cookie-desc"
              class="text-sm text-slate-300 leading-relaxed"
            >
              {{ t('cookies.description') }}
            </p>
          </div>

          <div class="action-buttons flex flex-wrap items-center gap-3 shrink-0">
            <button
              type="button"
              class="text-sm font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-slate-900 rounded px-2 py-1"
              @click="showModal = true"
            >
              {{ t('cookies.manage') }}
            </button>
            <Button
              :label="t('cookies.decline')"
              severity="secondary"
              outlined
              size="small"
              class="min-w-[140px]"
              @click="rejectNonEssential"
            />
            <Button
              :label="t('cookies.accept')"
              severity="primary"
              size="small"
              class="min-w-[140px]"
              @click="acceptAll"
            />
          </div>
        </div>
      </div>
    </div>
  </transition>

  <Dialog
    v-model:visible="showModal"
    :header="t('cookies.preferencesTitle')"
    :modal="true"
    :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
    class="cookie-preferences-dialog"
  >
    <div class="flex flex-col gap-6 py-4">
      <p class="text-sm text-slate-600">
        {{ t('cookies.preferencesDesc') }}
      </p>

      <div class="preference-item">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="font-bold primary-text">
              {{ t('cookies.essentialTitle') }}
            </h3>
            <p class="text-xs text-slate-500">
              {{ t('cookies.essentialDesc') }}
            </p>
          </div>
          <ToggleSwitch
            v-model="consent.essential"
            disabled
          />
        </div>
        <Divider />
      </div>

      <div class="preference-item">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="font-bold primary-text">
              {{ t('cookies.functionalTitle') }}
            </h3>
            <p class="text-xs text-slate-500">
              {{ t('cookies.functionalDesc') }}
            </p>
          </div>
          <ToggleSwitch v-model="consent.functional" />
        </div>
        <Divider />
      </div>

      <div class="preference-item">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="font-bold primary-text">
              {{ t('cookies.analyticalTitle') }}
            </h3>
            <p class="text-xs text-slate-500">
              {{ t('cookies.analyticalDesc') }}
            </p>
          </div>
          <ToggleSwitch v-model="consent.analytical" />
        </div>
        <Divider />
      </div>

      <div class="preference-item">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="font-bold primary-text">
              {{ t('cookies.marketingTitle') }}
            </h3>
            <p class="text-xs text-slate-500">
              {{ t('cookies.marketingDesc') }}
            </p>
          </div>
          <ToggleSwitch v-model="consent.marketing" />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <Button
          :label="t('cookies.declineAll')"
          severity="secondary"
          text
          @click="rejectNonEssential"
        />
        <Button
          :label="t('cookies.savePreferences')"
          severity="primary"
          @click="saveCustom"
        />
      </div>
    </template>
  </Dialog>
</template>

<style scoped lang="scss">
.cookie-banner-wrapper {
  position: fixed;
  bottom: 1.5rem;
  left: 1.5rem;
  right: 1.5rem;
  z-index: 2000;
  display: flex;
  justify-content: center;
}

.cookie-banner-content {
  width: 100%;
  max-width: 1200px;
  padding: 1.5rem 2rem;
  background: rgba(15, 23, 42, 0.85); // Dark slate with opacity
  backdrop-filter: blur(16px) saturate(180%);
  -webkit-backdrop-filter: blur(16px) saturate(180%);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 1.25rem;
  box-shadow:
    0 10px 15px -3px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05),
    0 20px 25px -5px rgba(0, 0, 0, 0.1),
    inset 0 0 0 1px rgba(255, 255, 255, 0.05);
  color: white;

  @media (max-width: 768px) {
    padding: 1.25rem;
    bottom: 1rem;
    left: 1rem;
    right: 1rem;
  }
}

.text-primary {
  color: var(--p-primary-color);
}

.preference-item {
  &:last-child {
    :deep(.p-divider) {
      display: none;
    }
  }
}

/* Transitions */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(50px) scale(0.95);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.98);
}
</style>
