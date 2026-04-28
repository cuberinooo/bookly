<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { BookingWindow } from '../app/enums/BookingWindow';

const toast = useToast();
const settings = ref({
    showParticipantNames: true,
    isWaitlistVisible: true,
    bookingWindow: BookingWindow.OFF
});
const loading = ref(true);
const saving = ref(false);

const windowOptions = [
    { label: 'Anytime (No Restriction)', value: BookingWindow.OFF },
    { label: 'Current Week Only', value: BookingWindow.CURRENT_WEEK },
    { label: 'Next 2 Weeks', value: BookingWindow.TWO_WEEKS },
    { label: 'Next Month', value: BookingWindow.MONTH }
];

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/settings');
        settings.value = {
            showParticipantNames: response.data.showParticipantNames ?? true,
            isWaitlistVisible: response.data.isWaitlistVisible ?? true,
            bookingWindow: response.data.bookingWindow ?? BookingWindow.OFF
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load settings', life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/settings', settings.value);
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Global privacy preferences saved', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update settings', life: 5000 });
    } finally {
        saving.value = false;
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="trainer-settings-form">
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
          Squad Privacy
        </h3>
        <div class="setting-row">
          <div class="setting-info">
            <label class="form-label">Show Confirmed Participant Names</label>
            <p class="text-xs text-slate-500">
              If disabled, members see "Athlete #ID" instead of names.
            </p>
          </div>
          <ToggleSwitch
            v-model="settings.showParticipantNames"
            :disabled="saving"
            @change="updateSettings"
          />
        </div>
      </div>

      <div class="settings-card phoenix-card">
        <h3 class="settings-title">
          Waitlist Control
        </h3>
        <div class="flex flex-col gap-8">
          <div class="setting-row">
            <div class="setting-info">
              <label class="form-label">Make Waitlist Visible to Members</label>
              <p class="text-xs text-slate-500">
                Enable if you want members to see how many people are waiting.
              </p>
            </div>
            <ToggleSwitch
              v-model="settings.isWaitlistVisible"
              :disabled="saving"
              @change="updateSettings"
            />
          </div>
        </div>
      </div>

      <div class="settings-card phoenix-card">
        <h3 class="settings-title">
          Booking Restriction
        </h3>
        <div class="setting-row">
          <div class="setting-info">
            <label class="form-label">Member Booking Window</label>
            <p class="text-xs text-slate-500">
              Restrict how far in advance members can book their workouts.
            </p>
          </div>
          <Select
            v-model="settings.bookingWindow"
            :options="windowOptions"
            option-label="label"
            option-value="value"
            placeholder="Select Window"
            class="w-64"
            @change="updateSettings"
          />
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
