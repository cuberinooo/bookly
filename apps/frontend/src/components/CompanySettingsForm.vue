<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();
const settings = ref({
    name: ''
});
const loading = ref(true);
const registrationLink = computed(() => {
    const origin = window.location.origin;
    return `${origin}/register?companyName=${encodeURIComponent(settings.value.name)}`;
});

function copyLink() {
    navigator.clipboard.writeText(registrationLink.value);
    toast.add({ severity: 'success', summary: t('app.copied'), detail: t('settings.registrationLinkCopied'), life: 3000 });
}

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/admin-settings');
        settings.value = {
            name: response.data.name || ''
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('settings.loadFailed'), life: 5000 });
    } finally {
        loading.value = false;
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="company-settings-form">
    <div
      v-if="loading"
      class="flex justify-center py-8"
    >
      <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
    </div>

    <div
      v-else
      class="settings-grid flex flex-col gap-8"
    >
      <div class="settings-card phoenix-card">
        <h3 class="settings-title">
          {{ $t('settings.identityTitle') }}
        </h3>
        <div class="flex flex-col gap-4">
          <p class="text-sm text-slate-600">
            {{ $t('settings.identityNote') }}
            <span class="block mt-1 font-bold text-amber-600">{{ $t('settings.noteCompanyNameLocked') }}</span>
          </p>
          <div class="field flex flex-col gap-2">
            <label
              class="font-bold uppercase text-xs"
              for="companyName"
            >{{ $t('settings.companyName') }}</label>
            <InputText
              id="companyName"
              :model-value="settings.name"
              disabled
              class="w-full max-w-md bg-slate-50"
            />
          </div>
        </div>

        <Divider class="my-8" />

        <div class="registration-link-section">
          <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-2">
            {{ $t('settings.registrationLinkTitle') }}
          </h4>
          <p class="text-sm text-slate-600 mb-4">
            {{ $t('settings.registrationLinkNote') }}
          </p>
          <div class="flex gap-2 p-2 bg-slate-50 border border-slate-200 rounded-lg items-center">
            <code class="text-xs text-slate-700 flex-1 overflow-hidden text-ellipsis whitespace-nowrap px-2">
              {{ registrationLink }}
            </code>
            <Button
              v-tooltip.top="$t('settings.copyLink')"
              icon="pi pi-copy"
              severity="secondary"
              text
              size="small"
              @click="copyLink"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.settings-card {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color);

    @media (max-width: 640px) {
      padding: 1rem;
    }
}

.settings-title {
    @apply text-lg font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100;
    font-family: 'Barlow Condensed', sans-serif;
}

.field {
  :deep(.p-inputtext) {
    @media (max-width: 640px) {
      max-width: 100% !important;
    }
  }
}

.registration-link-section {
  .flex {
    @media (max-width: 640px) {
      flex-direction: column;
      align-items: stretch;

      code {
        padding: 0.75rem;
        border-bottom: 1px solid var(--border-color);
      }

      :deep(.p-button) {
        width: 100%;
        justify-content: center;
        padding: 0.75rem;
      }
    }
  }
}
</style>
