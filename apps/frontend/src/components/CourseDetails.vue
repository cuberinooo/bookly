<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatTime, formatDateWithDay } from '../services/date-utils';
import { useAuthStore } from '../store/useAuthStore';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

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
const toast = useToast();
const submitting = ref(false);

const activeCategoryDescription = computed(() => props.course?.cycleCategory?.description);

async function bookCourse() {
  if (!authStore.isLoggedIn) {
    toast.add({ severity: 'info', summary: 'Login Required', detail: 'Please login to book a course', life: 5000 });
    return;
  }
  submitting.value = true;
  try {
    await api.post(`/courses/${props.course.id}/book`);
    toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Booking confirmed!', life: 5000 });
    emit('booked');
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Booking failed', life: 5000 });
  } finally {
    submitting.value = false;
  }
}

async function unbookCourse() {
  submitting.value = true;
  try {
    await api.delete(`/courses/${props.course.id}/book`);
    toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Booking cancelled', life: 5000 });
    emit('unbooked');
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to cancel booking', life: 5000 });
  } finally {
    submitting.value = false;
  }
}

function formatDuration(min: number) {
  if (min < 60) return `${min}min`;
  const h = Math.floor(min / 60);
  const m = min % 60;
  return m > 0 ? `${h}h ${m}min` : `${h} hour${h > 1 ? 's' : ''}`;
}

const spotsLeft = computed(() => {
  const registered = props.course.bookings.filter((b: any) => !b.isWaitlist).length;
  if (registered < props.course.capacity) {
    return (props.course.capacity - registered) + ' SPOTS LEFT';
  }
  return 'WAITLIST ACTIVE';
});

const registeredCount = computed(() => {
  return props.course.bookings.filter((b: any) => !b.isWaitlist).length + ' / ' + props.course.capacity;
});

const isBookedByUser = computed(() => {
  return props.course.bookings.some((b: any) => b.user?.id === authStore.user?.id);
});

const bookingLabel = computed(() => {
  const registered = props.course.bookings.filter((b: any) => !b.isWaitlist).length;
  return registered < props.course.capacity ? 'RESERVE SPOT' : 'JOIN WAITLIST';
});
</script>

<template>
  <div class="workout-details">
    <div class="trainer-info">
      <div class="avatar-placeholder">
        <i class="pi pi-user" />
      </div>
      <div>
        <small>HEAD COACH</small>
        <span class="trainer-name">{{ course.user?.name }}</span>
      </div>
    </div>

    <div class="schedule-detail-box">
      <div class="detail-row">
        <div class="detail-item">
          <small>DATE & TIME</small>
          <span class="value">{{ formatDateWithDay(course.startTime) }} at {{ formatTime(course.startTime) }}</span>
        </div>
        <div class="detail-item duration-item">
          <small>DURATION</small>
          <span class="value">{{ formatDuration(course.durationMinutes) }}</span>
        </div>
      </div>
      <div class="detail-accent-line" />
    </div>

    <div class="field">
      <label>Workout Brief</label>
      <div
        v-if="activeCategoryDescription"
        class="mb-3 p-4 bg-amber-50 border-2 border-amber-200 rounded-xl animate-fadein"
      >
        <div class="flex items-center gap-2 mb-2">
          <i class="pi pi-info-circle text-amber-600" />
          <span class="text-[10px] font-black uppercase text-amber-700 tracking-widest">Training Cycle Focus Override</span>
        </div>
        <p class="text-sm font-bold text-slate-800 leading-relaxed italic">
          "{{ activeCategoryDescription }}"
        </p>
        <div class="mt-2 text-[9px] font-bold text-amber-600 uppercase">
          Category: {{ course.cycleCategory.name }}
        </div>
      </div>
      <Textarea
        v-else
        disabled
        class="w-full"
        :model-value="course.description || 'No description provided.'"
      />
    </div>

    <div class="specs-grid">
      <div class="field">
        <label>CAPACITY</label>
        <InputText
          disabled
          class="w-full"
          :model-value="spotsLeft"
        />
      </div>
      <div class="field">
        <label>REGISTERED</label>
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
        <template v-if="!isOutsideBookingWindow && !isTrialRestricted">
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
            label="CANCEL RESERVATION"
            severity="primary"
            variant="text"
            class="w-full p-4 cancel-btn"
            :loading="submitting"
            @click="unbookCourse"
          />
        </template>
        <div
          v-else
          class="text-center p-4 bg-slate-50 rounded-lg border border-slate-200"
        >
          <p class="font-bold text-slate-600 uppercase tracking-widest text-xs mb-2">
            <i class="pi pi-lock" /> Booking Restricted
          </p>
          <p class="text-xs text-slate-500 font-medium">
            {{ bookingWindowMessage }}
          </p>
        </div>
      </template>
      <div
        v-else
        class="text-center font-bold text-slate-500 uppercase tracking-widest text-xs"
      >
        <i class="pi pi-info-circle" /> You are the coach for this session.
      </div>
    </div>

    <div
      v-if="isPastCourse"
      class="action-footer past-course-info"
    >
      <p class="text-center font-bold text-slate-500 uppercase tracking-widest text-xs">
        <i class="pi pi-lock" /> This session has already finished.
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
