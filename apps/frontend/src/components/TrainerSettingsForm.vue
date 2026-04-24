<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const settings = ref({
    showParticipantNames: true,
    showWaitlistNames: true,
    isWaitlistVisible: true
});
const loading = ref(true);
const saving = ref(false);

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/user/me');
        const s = response.data.trainerSettings || {};
        settings.value = {
            showParticipantNames: s.showParticipantNames ?? true,
            showWaitlistNames: s.showWaitlistNames ?? true,
            isWaitlistVisible: s.isWaitlistVisible ?? true
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load settings' });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/user/me', settings.value);
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Privacy preferences saved', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update settings' });
    } finally {
        saving.value = false;
    }
}

onMounted(fetchSettings);
</script>

<template>
    <div class="trainer-settings-form">
        <div v-if="loading" class="flex justify-center py-8">
            <i class="pi pi-spin pi-spinner text-3xl text-amber-400"></i>
        </div>
        
        <div v-else class="settings-grid flex flex-col gap-8">
            <div class="settings-card phoenix-card">
                <h3 class="settings-title">Squad Privacy</h3>
                <div class="setting-row">
                    <div class="setting-info">
                        <label class="form-label">Show Confirmed Participant Names</label>
                        <p class="text-xs text-slate-500">If disabled, members see "Athlete #ID" instead of names.</p>
                    </div>
                    <ToggleSwitch v-model="settings.showParticipantNames" @change="updateSettings" :disabled="saving" />
                </div>
            </div>

            <div class="settings-card phoenix-card">
                <h3 class="settings-title">Waitlist Control</h3>
                <div class="flex flex-col gap-8">
                    <div class="setting-row">
                        <div class="setting-info">
                            <label class="form-label">Make Waitlist Visible to Members</label>
                            <p class="text-xs text-slate-500">Enable if you want members to see how many people are waiting.</p>
                        </div>
                        <ToggleSwitch v-model="settings.isWaitlistVisible" @change="updateSettings" :disabled="saving" />
                    </div>
                    
                    <div class="setting-row" v-if="settings.isWaitlistVisible">
                        <div class="setting-info">
                            <label class="form-label">Show Waitlist Names</label>
                            <p class="text-xs text-slate-500">If disabled, waitlisted athletes remain anonymous to others.</p>
                        </div>
                        <ToggleSwitch v-model="settings.showWaitlistNames" @change="updateSettings" :disabled="saving" />
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

.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.setting-info {
    label { @apply mb-1 block font-bold text-slate-700 uppercase text-sm; font-family: 'Barlow Condensed', sans-serif; }
}
</style>
