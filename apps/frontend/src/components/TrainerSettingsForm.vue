<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { useAuthStore } from '../store/useAuthStore';
import { useToast } from 'primevue/usetoast';
import { BookingWindow } from '../app/enums/BookingWindow';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();
const authStore = useAuthStore();
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

const notificationError = computed(() => {
    const totalMinutes = (settings.value.courseStartNotificationHours * 60) + settings.value.courseStartNotificationMinutes;
    if (totalMinutes === 0) return null;
    if (totalMinutes < 5) return t('settings.notificationMinError');
    if (totalMinutes % 5 !== 0) return t('settings.notificationStepError');
    return null;
});

const windowOptions = computed(() => [
    { label: t('settings.windowOptions.off'), value: BookingWindow.OFF },
    { label: t('settings.windowOptions.currentWeek'), value: BookingWindow.CURRENT_WEEK },
    { label: t('settings.windowOptions.twoWeeks'), value: BookingWindow.TWO_WEEKS },
    { label: t('settings.windowOptions.month'), value: BookingWindow.MONTH }
]);

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
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.loadFailed'), life: 5000 });
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
        toast.add({ severity: 'success', summary: t('app.updated'), detail: t('profile.updateSuccess'), life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.updateError'), life: 5000 });
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
        toast.add({ severity: 'success', summary: t('app.updated'), detail: t('profile.updateSuccess'), life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.updateError'), life: 5000 });
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
          <h2 class="text-2xl font-black text-slate-900 tracking-tight font-barlow uppercase">
            {{ $t('settings.operations') }}
          </h2>
          <p class="text-sm text-slate-500 font-medium">
            {{ $t('settings.operationsNote') }}
          </p>
        </div>

        <div class="flex flex-col gap-6">
          <div class="settings-card phoenix-card">
            <h3 class="settings-title">
              {{ $t('settings.squadPrivacy') }}
            </h3>
            <div class="setting-row">
              <div class="setting-info">
                <label
                  for="showNames"
                  class="form-label"
                >{{ $t('settings.showNames') }}</label>
                <p class="text-xs text-slate-500">
                  {{ $t('settings.showNamesNote') }}
                </p>
              </div>
              <ToggleSwitch
                v-model="settings.showParticipantNames"
                input-id="showNames"
                :disabled="saving"
                @change="updateGlobalSettings"
              />
            </div>
          </div>

          <div class="settings-card phoenix-card">
            <h3 class="settings-title">
              {{ $t('settings.waitlistControl') }}
            </h3>
            <div class="setting-row">
              <div class="setting-info">
                <label
                  for="waitlistVisible"
                  class="form-label"
                >{{ $t('settings.showWaitlist') }}</label>
                <p class="text-xs text-slate-500">
                  {{ $t('settings.showWaitlistNote') }}
                </p>
              </div>
              <ToggleSwitch
                v-model="settings.isWaitlistVisible"
                input-id="waitlistVisible"
                :disabled="saving"
                @change="updateGlobalSettings"
              />
            </div>
          </div>

          <div class="settings-card phoenix-card">
            <h3 class="settings-title">
              {{ $t('settings.bookingRestriction') }}
            </h3>
            <div class="setting-row">
              <div class="setting-info">
                <label
                  for="bookingWindow"
                  class="form-label"
                >{{ $t('settings.memberWindow') }}</label>
                <p class="text-xs text-slate-500">
                  {{ $t('settings.memberWindowNote') }}
                </p>
              </div>
              <Select
                v-model="settings.bookingWindow"
                input-id="bookingWindow"
                :options="windowOptions"
                option-label="label"
                option-value="value"
                :placeholder="$t('settings.selectWindow')"
                class="w-64"
                @change="updateGlobalSettings"
              />
            </div>
          </div>

          <div class="settings-card phoenix-card">
            <h3 class="settings-title">
              {{ $t('settings.trialMembership') }}
            </h3>
            <div class="setting-row">
              <div class="setting-info">
                <label
                  for="trialLimit"
                  class="form-label"
                >{{ $t('settings.trialLimit') }}</label>
                <p class="text-xs text-slate-500">
                  {{ $t('settings.trialLimitNote') }}
                </p>
              </div>
              <InputNumber
                v-model="settings.trialBookingLimit"
                input-id="trialLimit"
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
      <section
        v-if="authStore.isTrainer"
        class="settings-section"
      >
        <div class="section-header mb-6">
          <h2 class="text-2xl font-black text-slate-900 tracking-tight font-barlow uppercase">
            {{ $t('settings.trainerAlerts') }}
          </h2>
          <p class="text-sm text-slate-500 font-medium">
            {{ $t('settings.trainerAlertsNote') }}
          </p>
        </div>

        <div class="settings-card phoenix-card">
          <h3 class="settings-title">
            {{ $t('settings.startNotification') }}
          </h3>
          <div class="setting-row mb-8">
            <div class="setting-info">
              <label class="form-label">{{ $t('settings.notifyBefore') }}</label>
              <p class="text-xs text-slate-500">
                {{ $t('settings.notifyBeforeNote') }}
              </p>
            </div>
            <div class="flex flex-col items-end gap-2">
              <div class="flex items-center gap-4">
                <div class="flex flex-col gap-1">
                  <label
                    for="notifyHours"
                    class="text-[10px] font-bold text-slate-400 uppercase"
                  >{{ $t('settings.hours') }}</label>
                  <InputNumber
                    v-model="settings.courseStartNotificationHours"
                    input-id="notifyHours"
                    :min="0"
                    :max="23"
                    show-buttons
                    class="w-24"
                  />
                </div>
                <div class="flex flex-col gap-1">
                  <label
                    for="notifyMinutes"
                    class="text-[10px] font-bold text-slate-400 uppercase"
                  >{{ $t('settings.minutes') }}</label>
                  <InputNumber
                    v-model="settings.courseStartNotificationMinutes"
                    input-id="notifyMinutes"
                    :min="0"
                    :max="55"
                    :step="5"
                    show-buttons
                    class="w-24"
                  />
                </div>
              </div>
              <div class="w-full text-right min-h-[1rem]">
                <p
                  v-if="notificationError"
                  class="text-[10px] text-red-500 font-bold uppercase tracking-tight"
                >
                  {{ notificationError }}
                </p>
                <p
                  v-else
                  class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"
                >
                  {{ $t('settings.minNotificationNote') }}
                </p>
              </div>
            </div>
          </div>
          <div class="flex justify-end pt-4 border-t border-slate-100">
            <Button
              :label="$t('settings.saveNotification')"
              icon="pi pi-check"
              severity="primary"
              :loading="saving"
              :disabled="!!notificationError"
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

    @media (max-width: 640px) {
      padding: 1rem;
    }
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

    @media (max-width: 640px) {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;

      :deep(.p-select), :deep(.p-inputnumber) {
        width: 100% !important;
      }

      :deep(.p-toggleswitch) {
        align-self: flex-end;
      }
    }
}

.setting-info {
  color: var(--bg-primary-color);
    label { @apply mb-1 block font-bold text-slate-700 uppercase text-sm; font-family: 'Barlow Condensed', sans-serif; }
}
</style>
