<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { settingsStore } from '../store/settings';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const settings = ref({
    name: ''
});
const loading = ref(true);
const saving = ref(false);

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/admin-settings');
        settings.value = {
            name: response.data.name || ''
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load company settings', life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/admin-settings', { name: settings.value.name });
        settingsStore.companyName = settings.value.name;
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Company settings saved successfully', life: 5000 });
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.error || 'Failed to update company settings', life: 5000 });
    } finally {
        saving.value = false;
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="company-settings-form">
    <div v-if="loading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
    </div>

    <div v-else class="settings-grid flex flex-col gap-8">
      <div class="settings-card phoenix-card">
        <h3 class="settings-title">Company Identity</h3>
        <div class="flex flex-col gap-4">
          <p class="text-sm text-slate-600">
            Customize your company name as it appears in the navigation, emails, and throughout the system.
            Note: Company names must be unique.
          </p>
          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs" for="companyName">Company Name</label>
            <InputText id="companyName" v-model="settings.name" placeholder="e.g. Phoenix Athletics" class="w-full max-w-md" />
          </div>
          <div class="mt-4 flex justify-end">
            <Button severity="primary" label="Save Company Settings" icon="pi pi-save" :loading="saving" @click="updateSettings" />
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
}

.settings-title {
    @apply text-lg font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100;
    font-family: 'Barlow Condensed', sans-serif;
}
</style>
