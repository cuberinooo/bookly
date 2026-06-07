<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '../store/useSettingsStore';

const { t } = useI18n();
const toast = useToast();
const settingsStore = useSettingsStore();

const settings = ref({
    name: '',
    homepageUrl: ''
});
const loading = ref(true);
const saving = ref(false);

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
            name: response.data.name || '',
            homepageUrl: response.data.homepageUrl || ''
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('settings.loadFailed'), life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    if (settings.value.homepageUrl) {
        const url = settings.value.homepageUrl.trim();
        if (!/^https?:\/\//i.test(url)) {
            toast.add({ severity: 'error', summary: t('app.error'), detail: t('settings.homepageUrlInvalid'), life: 5000 });
            return;
        }
    }

    saving.value = true;
    try {
        await api.patch('/admin-settings', {
            homepageUrl: settings.value.homepageUrl ? settings.value.homepageUrl.trim() : null
        });
        settingsStore.homepageUrl = settings.value.homepageUrl ? settings.value.homepageUrl.trim() : '';
        toast.add({ severity: 'success', summary: t('app.updated'), detail: t('profile.updateSuccess'), life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.updateError'), life: 5000 });
    } finally {
        saving.value = false;
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
          <div class="field flex flex-col gap-2 mt-2">
            <label
              class="font-bold uppercase text-xs"
              for="homepageUrl"
            >{{ $t('settings.homepageUrl') }}</label>
            <div class="relative w-full max-w-md flex items-center">
              <InputText
                id="homepageUrl"
                v-model="settings.homepageUrl"
                :placeholder="$t('settings.homepageUrlPlaceholder')"
                class="w-full pl-10 pr-10"
                :class="{ 'p-invalid': settings.homepageUrl && !/^https?:\/\//i.test(settings.homepageUrl) }"
              />
              <span
                v-if="settings.homepageUrl"
                class="absolute right-3 cursor-pointer text-slate-400 hover:text-red-500 transition-colors flex items-center"
                @click="settings.homepageUrl = ''"
              >
                <i class="pi pi-times-circle" />
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
              {{ $t('settings.homepageUrlNote') }}
            </p>
            <small
              v-if="settings.homepageUrl && !/^https?:\/\//i.test(settings.homepageUrl)"
              class="text-red-500 font-bold text-xs"
            >
              {{ $t('settings.homepageUrlInvalid') }}
            </small>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <Button
            severity="primary"
            :label="$t('settings.saveIdentity')"
            icon="pi pi-save"
            :loading="saving"
            :disabled="!!settings.homepageUrl && !/^https?:\/\//i.test(settings.homepageUrl)"
            @click="updateSettings"
          />
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
