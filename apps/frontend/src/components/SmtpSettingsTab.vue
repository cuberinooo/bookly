<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();

const settings = ref({
    host: '',
    port: 587,
    username: '',
    password: '',
    encryption: 'tls',
    useCustomSmtp: false
});

const loading = ref(true);
const saving = ref(false);

const encryptionOptions = [
    { label: 'None', value: null },
    { label: 'TLS', value: 'tls' },
    { label: 'SSL', value: 'ssl' }
];

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/smtp-settings');
        if (response.data) {
            settings.value = {
                host: response.data.host || '',
                port: response.data.port || 587,
                username: response.data.username || '',
                password: response.data.password || '',
                encryption: response.data.encryption || 'tls',
                useCustomSmtp: response.data.useCustomSmtp || false
            };
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('settings.loadFailed'), life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/smtp-settings', settings.value);
        toast.add({ severity: 'success', summary: t('app.updated'), detail: t('settings.smtpSaved'), life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('app.error'), life: 5000 });
    } finally {
        saving.value = false;
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="smtp-settings-tab">
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
        <div class="flex flex-col gap-1 mb-6">
          <h3 class="settings-title mb-0 border-b-0 pb-0">
            {{ $t('settings.smtp.title') }}
          </h3>
          <p class="text-sm text-slate-500">
            {{ $t('settings.smtp.subtitle') }}
          </p>
        </div>

        <div class="flex items-center gap-4 mb-8 p-1 bg-amber-50 rounded-xl border border-amber-100">
          <ToggleSwitch
            v-model="settings.useCustomSmtp"
            binary
          />
          <div>
            <span class="font-bold text-slate-900 block">{{ $t('settings.smtp.enableCustom') }}</span>
            <span class="text-xs text-slate-600">{{ $t('settings.smtp.enableCustomNote') }}</span>
          </div>
        </div>

        <div
          class="grid grid-cols-1 md:grid-cols-2 gap-6"
          :class="{ 'opacity-50 pointer-events-none': !settings.useCustomSmtp }"
        >
          <div class="field flex flex-col gap-2">
            <label
              class="font-bold uppercase text-xs text-slate-400"
              for="host"
            >{{ $t('settings.smtp.host') }}</label>
            <InputText
              id="host"
              v-model="settings.host"
              placeholder="smtp.example.com"
              class="w-full"
            />
          </div>

          <div class="field flex flex-col gap-2">
            <label
              class="font-bold uppercase text-xs text-slate-400"
              for="port"
            >{{ $t('settings.smtp.port') }}</label>
            <InputNumber
              id="port"
              v-model="settings.port"
              :use-grouping="false"
              placeholder="587"
              class="w-full"
            />
          </div>

          <div class="field flex flex-col gap-2">
            <label
              class="font-bold uppercase text-xs text-slate-400"
              for="username"
            >{{ $t('settings.smtp.username') }}</label>
            <InputText
              id="username"
              v-model="settings.username"
              placeholder="user@example.com"
              class="w-full"
            />
          </div>

          <div class="field flex flex-col gap-2">
            <label
              class="font-bold uppercase text-xs text-slate-400"
              for="password"
            >{{ $t('settings.smtp.password') }}</label>
            <Password
              id="password"
              v-model="settings.password"
              :feedback="false"
              toggle-mask
              class="w-full"
              input-class="w-full"
            />
          </div>

          <div class="field flex flex-col gap-2">
            <label
              class="font-bold uppercase text-xs text-slate-400"
              for="encryption"
            >{{ $t('settings.smtp.encryption') }}</label>
            <Select
              id="encryption"
              v-model="settings.encryption"
              :options="encryptionOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
        </div>

        <div class="flex justify-end mt-10">
          <Button
            :label="$t('app.save')"
            severity="primary"
            icon="pi pi-save"
            :loading="saving"
            class="px-8 rounded-full"
            @click="updateSettings"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>

.settings-title {
    @apply text-xl font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100;
    font-family: 'Barlow Condensed', sans-serif;
}

.field {
    label {
        font-family: 'Barlow Condensed', sans-serif;
    }
}
</style>
