<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import { BookingWindow } from '../app/enums/BookingWindow';

const toast = useToast();
const settings = ref({
    showParticipantNames: true,
    isWaitlistVisible: true,
    bookingWindow: BookingWindow.OFF,
    trialBookingLimit: 0,
    courseStartNotificationHours: 0,
    courseStartNotificationMinutes: 0
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
        const userResponse = await api.get('/user/me');
        settings.value = {
            showParticipantNames: response.data.showParticipantNames ?? true,
            isWaitlistVisible: response.data.isWaitlistVisible ?? true,
            bookingWindow: response.data.bookingWindow ?? BookingWindow.OFF,
            trialBookingLimit: response.data.trialBookingLimit ?? 0,
            courseStartNotificationHours: userResponse.data.courseStartNotificationHours ?? 0,
            courseStartNotificationMinutes: userResponse.data.courseStartNotificationMinutes ?? 0
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load settings', life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateGlobalSettings() {
    saving.value = true;
    try {
        await api.patch('/settings', {
            showParticipantNames: settings.value.showParticipantNames,
            isWaitlistVisible: settings.value.isWaitlistVisible,
            bookingWindow: settings.value.bookingWindow,
            trialBookingLimit: settings.value.trialBookingLimit
        });
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Global settings updated', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update global settings', life: 5000 });
    } finally {
        saving.value = false;
    }
}

async function updatePersonalSettings() {
    saving.value = true;
    try {
        await api.patch('/user/me', {
            courseStartNotificationHours: settings.value.courseStartNotificationHours,
            courseStartNotificationMinutes: settings.value.courseStartNotificationMinutes
        });
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Personal notification preferences saved', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update personal settings', life: 5000 });
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
      class="settings-grid flex flex-col gap-12"
    >
      <!-- GLOBAL SETTINGS SECTION -->
      <section class="settings-section">
        <div class="section-header mb-6">
          <h2 class="text-2xl font-black text-slate-900 tracking-tight font-barlow uppercase">Global Operations</h2>
          <p class="text-sm text-slate-500 font-medium">Configure organization-wide rules for bookings and privacy.</p>
        </div>

        <div class="flex flex-col gap-6">
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
                @change="updateGlobalSettings"
              />
            </div>
          </div>

          <div class="settings-card phoenix-card">
            <h3 class="settings-title">
              Waitlist Control
            </h3>
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
                @change="updateGlobalSettings"
              />
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
                @change="updateGlobalSettings"
              />
            </div>
          </div>

          <div class="settings-card phoenix-card">
            <h3 class="settings-title">
              Trial Membership
            </h3>
            <div class="setting-row">
              <div class="setting-info">
                <label class="form-label">Trial Booking Limit</label>
                <p class="text-xs text-slate-500">
                  How many total classes a trial member can book before needing an upgrade.
                </p>
              </div>
              <InputNumber
                v-model="settings.trialBookingLimit"
                show-buttons
                :min="0"
                class="w-32"
                @update:model-value="updateGlobalSettings"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- PERSONAL SETTINGS SECTION -->
      <section v-if="authStore.isTrainer()" class="settings-section">
        <div class="section-header mb-6">
          <h2 class="text-2xl font-black text-slate-900 tracking-tight font-barlow uppercase">Trainer Alerts</h2>
          <p class="text-sm text-slate-500 font-medium">Individual settings specific to your account.</p>
        </div>

        <div class="settings-card phoenix-card">
          <h3 class="settings-title">
            Course Start Notification
          </h3>
          <div class="setting-row mb-8">
            <div class="setting-info">
              <label class="form-label">Notify me before class</label>
              <p class="text-xs text-slate-500">
                Receive an email with the participant list before your class starts.
              </p>
            </div>
            <div class="flex items-center gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Hours</label>
                <InputNumber
                  v-model="settings.courseStartNotificationHours"
                  :min="0"
                  :max="23"
                  show-buttons
                  class="w-24"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Minutes</label>
                <InputNumber
                  v-model="settings.courseStartNotificationMinutes"
                  :min="0"
                  :max="59"
                  show-buttons
                  class="w-24"
                />
              </div>
            </div>
          </div>
          <div class="flex justify-end pt-4 border-t border-slate-100">
            <Button
                label="Save Notification Settings"
                icon="pi pi-check"
                severity="primary"
                :loading="saving"
                @click="updatePersonalSettings"
            />
          </div>
        </div>
      </section>
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
  color: var(--bg-primary-color);
    label { @apply mb-1 block font-bold text-slate-700 uppercase text-sm; font-family: 'Barlow Condensed', sans-serif; }
}
</style>
