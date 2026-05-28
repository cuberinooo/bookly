<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatTime, formatDateWithDay } from '../services/date-utils';
import { useAuthStore } from '../store/useAuthStore';
import { useOnboarding, ONBOARDING_TASKS } from '../composables/useOnboarding';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const props = defineProps<{
  course: any;
  settings: any;
  isPastCourse: boolean;
  isOutsideBookingWindow: boolean;
  isTrialRestricted: boolean;
  bookingWindowMessage: string;
  isMemberMode: boolean;
}>();

const emit = defineEmits(['booked', 'unbooked']);

const authStore = useAuthStore();
const { markTaskComplete } = useOnboarding();
const toast = useToast();
const submitting = ref(false);

const activeCategoryDescription = computed(() => props.course?.cycleCategory?.description);

async function bookCourse() {
  if (!authStore.isLoggedIn) {
    toast.add({ severity: 'info', summary: t('course.loginRequired'), detail: t('course.loginToBook'), life: 5000 });
    return;
  }
  submitting.value = true;
  try {
    await api.post(`/courses/${props.course.id}/book`);
    toast.add({ severity: 'success', summary: t('app.success'), detail: t('course.bookingConfirmed'), life: 5000 });
    markTaskComplete(ONBOARDING_TASKS.FIRST_BOOKING);
    emit('booked');
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: err.response?.data?.error || t('course.bookingFailed'), life: 5000 });
  } finally {
    submitting.value = false;
  }
}

async function unbookCourse() {
  submitting.value = true;
  try {
    await api.delete(`/courses/${props.course.id}/book`);
    toast.add({ severity: 'success', summary: t('course.bookingCancelled'), detail: t('course.bookingCancelled'), life: 5000 });
    emit('unbooked');
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('course.cancelBookingFailed'), life: 5000 });
  } finally {
    submitting.value = false;
  }
}

function formatDuration(min: number) {
  if (min < 60) return `${min}min`;
  const h = Math.floor(min / 60);
  const m = min % 60;
  return m > 0 ? `${h}h ${m}min` : `${h} ${t('course.hour')}${h > 1 ? 's' : ''}`;
}

const spotsLeft = computed(() => {
  const registered = props.course.bookings.filter((b: any) => !b.isWaitlist).length;
  if (registered < props.course.capacity) {
    return t('course.spotsLeft', { count: props.course.capacity - registered }).toUpperCase();
  }
  return t('course.waitlistActive').toUpperCase();
});

const registeredCount = computed(() => {
  return props.course.bookings.filter((b: any) => !b.isWaitlist).length + ' / ' + props.course.capacity;
});

const isBookedByUser = computed(() => {
  return props.course.bookings.some((b: any) => b.user?.id === authStore.user?.id);
});

const isInactive = computed(() => authStore.user?.isActive === false);

const bookingLabel = computed(() => {
  const registered = props.course.bookings.filter((b: any) => !b.isWaitlist).length;
  return registered < props.course.capacity ? t('course.reserveSpot').toUpperCase() : t('course.joinWaitlist').toUpperCase();
});
</script>

<template>
  <div class="workout-details">
    <div class="trainer-info">
      <div class="avatar-placeholder">
        <i class="pi pi-user" />
      </div>
      <div>
        <small>{{ t('course.headCoach') }}</small>
        <span class="trainer-name">{{ course.user?.name }}</span>
      </div>
    </div>

    <div
      v-if="isInactive && !isPastCourse"
      class="mb-6 p-4 bg-red-50 border-2 border-red-200 rounded-xl animate-fadein"
    >
      <div class="flex items-center gap-2 mb-2">
        <i class="pi pi-exclamation-circle text-red-600" />
        <span class="text-[10px] font-black uppercase text-red-700 tracking-widest">{{ t('course.accountInactive') }}</span>
      </div>
      <p class="text-sm font-bold text-slate-800 leading-relaxed">
        {{ t('course.inactiveAccountRestricted') }}
      </p>
    </div>

    <div class="schedule-detail-box">
      <div class="detail-row">
        <div class="detail-item">
          <small>{{ t('course.dateTime') }}</small>
          <span class="value">{{ formatDateWithDay(course.startTime) }} {{ t('course.at') }} {{ formatTime(course.startTime) }}</span>
        </div>
        <div class="detail-item duration-item">
          <small>{{ t('course.duration') }}</small>
          <span class="value">{{ formatDuration(course.durationMinutes) }}</span>
        </div>
      </div>
      <div class="detail-accent-line" />
    </div>

    <div class="field">
      <label>{{ t('course.workoutBrief') }}</label>
      <div
        v-if="activeCategoryDescription"
        class="mb-3 p-4 bg-amber-50 border-2 border-amber-200 rounded-xl animate-fadein"
      >
        <div class="flex items-center gap-2 mb-2">
          <i class="pi pi-info-circle text-amber-600" />
          <span class="text-[10px] font-black uppercase text-amber-700 tracking-widest">{{ t('course.trainingCycleOverride') }}</span>
        </div>
        <p class="text-sm font-bold text-slate-800 leading-relaxed italic">
          "{{ activeCategoryDescription }}"
        </p>
        <div class="mt-2 text-[9px] font-bold text-amber-600 uppercase">
          {{ t('admin.cycle.matrixTitle') }}: {{ course.cycleCategory.name }}
        </div>
      </div>
      <Textarea
        v-else
        disabled
        class="w-full"
        :model-value="course.description || t('course.noDescription')"
      />
    </div>

    <div class="specs-grid">
      <div class="field">
        <label>{{ t('course.capacity') }}</label>
        <InputText
          disabled
          class="w-full"
          :model-value="spotsLeft"
        />
      </div>
      <div class="field">
        <label>{{ t('course.registered') }}</label>
        <InputText
          disabled
          class="w-full"
          :model-value="registeredCount"
        />
      </div>
    </div>

    <div
      v-if="isMemberMode && !isPastCourse"
      class="action-footer"
    >
      <template v-if="course.user?.id !== authStore.user?.id">
        <template v-if="!isOutsideBookingWindow && !isTrialRestricted && course.status !== 'postponed' && !isInactive">
          <Button
            v-if="!isBookedByUser"
            :label="bookingLabel"
            severity="primary"
            class="w-full p-4"
            :loading="submitting"
            @click="bookCourse"
          />
          <Button
            v-else
            :label="t('course.cancelReservation')"
            severity="primary"
            variant="text"
            class="w-full p-4 cancel-btn"
            :loading="submitting"
            @click="unbookCourse"
          />
        </template>
        <div
          v-else-if="isInactive && !isPastCourse"
          class="text-center p-4 bg-slate-50 rounded-lg border border-slate-200"
        >
          <p class="font-bold text-slate-600 uppercase tracking-widest text-xs mb-2">
            <i class="pi pi-info-circle" /> {{ t('course.bookingRestricted') }}
          </p>
          <p class="text-xs text-slate-500 font-medium">
            {{ t('course.inactiveAccountRestricted') }}
          </p>
        </div>
        <div
          v-else
          class="text-center p-4 bg-slate-50 rounded-lg border border-slate-200"
        >
          <p class="font-bold text-slate-600 uppercase tracking-widest text-xs mb-2">
            <i class="pi pi-lock" /> {{ course.status === 'postponed' ? t('course.sessionCancelled') : t('course.bookingRestricted') }}
          </p>
          <p class="text-xs text-slate-500 font-medium">
            {{ course.status === 'postponed' ? t('course.cancelledNotBookable') : bookingWindowMessage }}
          </p>
        </div>
      </template>
      <div
        v-else
        class="text-center font-bold text-slate-500 uppercase tracking-widest text-xs"
      >
        <i class="pi pi-info-circle" /> {{ t('course.youAreCoach') }}
      </div>
    </div>

    <div
      v-if="isPastCourse"
      class="action-footer past-course-info"
    >
      <p class="text-center font-bold text-slate-500 uppercase tracking-widest text-xs">
        <i class="pi pi-lock" /> {{ t('course.sessionFinished') }}
      </p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.trainer-info {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  margin-bottom: 2rem;
  border-left: 6px solid var(--primary-color);

  .avatar-placeholder {
    width: 50px;
    height: 50px;
    background: #e2e8f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 1.5rem;
  }

  small {
    display: block;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800;
    color: var(--text-muted);
    letter-spacing: 0.1em;
  }

  .trainer-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-header);
    text-transform: uppercase;
    font-family: 'Barlow Condensed', sans-serif;
  }
}

.schedule-detail-box {
  background: #f1f5f9;
  padding: 1.5rem;
  border-radius: 12px;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;

  .detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
  }

  .detail-item {
    display: flex;
    flex-direction: column;

    small {
      font-family: 'Barlow Condensed', sans-serif;
      font-weight: 800;
      color: var(--text-muted);
      letter-spacing: 0.1em;
      font-size: 0.7rem;
      margin-bottom: 0.25rem;
    }

    .value {
      font-weight: 800;
      color: var(--text-header);
      font-size: 1.1rem;
    }

    &.duration-item {
      text-align: right;
      border-left: 2px solid var(--border-color);
      padding-left: 1.5rem;
    }
  }

  .detail-accent-line {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-color);
  }
}

.specs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 3rem;
}

.action-footer {
  padding-top: 2rem;
  border-top: 1px solid var(--border-color);

  :deep(.p-button) {
    font-size: 1.1rem !important;
    letter-spacing: 0.1em !important;
  }
}

.cancel-btn {
  &:hover {
    background: #fef2f2 !important;
    color: #ef4444 !important;
  }
}

:deep(.p-inputtext:disabled),
:deep(.p-textarea:disabled) {
  background-color: white !important;
  opacity: 1;
  color: var(--text-header);
}

label {
  display: block;
  font-family: 'Barlow Condensed', sans-serif;
  font-weight: 800;
  color: var(--text-muted);
  letter-spacing: 0.1em;
  font-size: 0.75rem;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
}
</style>
