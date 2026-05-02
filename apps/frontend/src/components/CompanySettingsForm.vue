<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { settingsStore } from '../store/settings';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const settings = ref({
    name: ''
});
const loading = ref(true);
const saving = ref(false);

const registrationLink = computed(() => {
    const origin = window.location.origin;
    return `${origin}/register?companyName=${encodeURIComponent(settings.value.name)}`;
});

function copyLink() {
    navigator.clipboard.writeText(registrationLink.value);
    toast.add({ severity: 'success', summary: 'Copied', detail: 'Registration link copied to clipboard', life: 3000 });
}

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
            <InputText id="companyName" v-model="settings.name" placeholder="e.g. Foo GmbH" class="w-full max-w-md" />
          </div>
          <div class="mt-4 flex justify-end">
            <Button severity="primary" label="Save Company Settings" icon="pi pi-save" :loading="saving" @click="updateSettings" />
          </div>
        </div>

        <Divider class="my-8" />

        <div class="registration-link-section">
          <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-2">Member Registration Link</h4>
          <p class="text-sm text-slate-600 mb-4">
            Share this link with new members to make their registration easier. The company name will be pre-filled and locked.
          </p>
          <div class="flex gap-2 p-2 bg-slate-50 border border-slate-200 rounded-lg items-center">
            <code class="text-xs text-slate-700 flex-1 overflow-hidden text-ellipsis whitespace-nowrap px-2">
              {{ registrationLink }}
            </code>
            <Button
                icon="pi pi-copy"
                severity="secondary"
                text
                size="small"
                v-tooltip.top="'Copy Link'"
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
}

.settings-title {
    @apply text-lg font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100;
    font-family: 'Barlow Condensed', sans-serif;
}
</style>
