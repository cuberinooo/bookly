<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { formatDate, formatTime, formatDateWithDay } from '../services/date-utils';
import api from '../services/api';
import { useAuthStore } from '../store/useAuthStore';
import { useCourseStore } from '../store/useCourseStore';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { BookingWindow } from '../app/enums/BookingWindow';
import WeeklyCalendar from '../components/WeeklyCalendar.vue';
import MobileCalendar from '../components/MobileCalendar.vue';
import CourseForm from '../components/CourseForm.vue';
import CourseDetails from '../components/CourseDetails.vue';

const toast = useToast();
const confirm = useConfirm();
const authStore = useAuthStore();
const courseStore = useCourseStore();

const courses = computed(() => courseStore.courseList);
const cycleInfo = computed(() => courseStore.cycleInfo);
const settings = ref<any>(null);
const submitting = ref(false);

const selectedCourse = ref<any>(null);
const detailVisible = ref(false);
const formVisible = ref(false);
const editingCourse = ref<any>(null);
const isCompactView = ref(true);

const baseDate = ref(new Date());

const isMobile = ref(window.innerWidth <= 768);

const isTrainerMode = computed(() => authStore.isTrainer && authStore.viewMode === 'trainer');
const isMemberMode = computed(() => !authStore.isLoggedIn || !authStore.isTrainer || authStore.viewMode === 'member');

const isPastCourse = computed(() => {
    if (!selectedCourse.value?.endTime) return false;
    return new Date(selectedCourse.value.endTime) < new Date();
});

const isOutsideBookingWindow = computed(() => {
    if (!selectedCourse.value || !settings.value || settings.value.bookingWindow === BookingWindow.OFF) {
        return false;
    }

    const start = new Date(selectedCourse.value.startTime);
    const now = new Date();
    const deadline = new Date();

    switch (settings.value.bookingWindow) {
        case BookingWindow.CURRENT_WEEK:
            // End of current week (Sunday)
            const day = now.getDay();
            const daysToSunday = day === 0 ? 0 : 7 - day;
            deadline.setDate(now.getDate() + daysToSunday);
            deadline.setHours(23, 59, 59, 999);
            break;
        case BookingWindow.TWO_WEEKS:
            deadline.setDate(now.getDate() + 14);
            break;
        case BookingWindow.MONTH:
            deadline.setMonth(now.getMonth() + 1);
            break;
    }

    return start > deadline;
});

const isTrialRestricted = computed(() => {
    return authStore.isTrial && selectedCourse.value && selectedCourse.value.allowTrial === false;
});

const bookingWindowMessage = computed(() => {
    if (isTrialRestricted.value) return 'This course is not available for trial members.';
    if (!isOutsideBookingWindow.value) return '';

    switch (settings.value?.bookingWindow) {
        case BookingWindow.CURRENT_WEEK: return 'Only current week bookings are allowed.';
        case BookingWindow.TWO_WEEKS: return 'Bookings only allowed for the next 2 weeks.';
        case BookingWindow.MONTH: return 'Bookings only allowed for the next month.';
        default: return 'Outside allowed booking window.';
    }
});

function handleResize() {
    isMobile.value = window.innerWidth <= 768;
}

watch(() => authStore.viewMode, () => {
    fetchCourses();
});

watch(baseDate, (newDate) => {
    const loadedStart = courseStore.loadedRange.start ? new Date(courseStore.loadedRange.start) : null;
    const loadedEnd = courseStore.loadedRange.end ? new Date(courseStore.loadedRange.end) : null;

    if (!loadedStart || !loadedEnd) {
        fetchCourses();
        return;
    }

    // Current week start (Monday)
    const startOfWeek = new Date(newDate);
    const day = startOfWeek.getDay();
    const diff = (day === 0 ? 6 : day - 1);
    startOfWeek.setDate(startOfWeek.getDate() - diff);
    startOfWeek.setHours(0, 0, 0, 0);

    // Current week end (Sunday)
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(endOfWeek.getDate() + 6);
    endOfWeek.setHours(23, 59, 59, 999);

    if (startOfWeek < loadedStart || endOfWeek > loadedEnd) {
        fetchCourses();
    }
});

async function fetchCourses() {
  try {
    // Fetch settings first if not loaded
    if (!settings.value) {
        const sResp = await api.get('/settings');
        settings.value = sResp.data;
    }

    // Fetch current week +/- 2 weeks for smoothness
    const base = new Date(baseDate.value);
    const day = base.getDay();
    const diff = (day === 0 ? 6 : day - 1);

    // Start of the 5-week range (Monday 2 weeks ago)
    const start = new Date(base);
    start.setDate(base.getDate() - diff - 14);
    start.setHours(0, 0, 0, 0);

    // End of the 5-week range (Sunday 2 weeks ahead)
    const end = new Date(start);
    end.setDate(start.getDate() + 34); // +4 weeks (28 days) + 6 days to get to Sunday
    end.setHours(23, 59, 59, 999);

    await courseStore.fetchCourses({
        all: true,
        startDate: start.toISOString(),
        endDate: end.toISOString()
    });
  } catch (err) {
    console.error('Failed to fetch courses', err);
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load courses', life: 5000 });
  }
}

function handleCourseClick(course: any) {
    if (isTrainerMode.value) {
        editingCourse.value = course;
        formVisible.value = true;
    } else {
        selectedCourse.value = course;
        detailVisible.value = true;
    }
}

function handleCellClick(date: Date) {
    if (!isTrainerMode.value) return;
    editingCourse.value = {
        startTime: date,
        title: 'Functional Training',
        capacity: 10,
        durationMinutes: 60,
        description: ''
    };
    formVisible.value = true;
}

async function onSaveCourse(formData: any, transferAll: boolean = false) {
    submitting.value = true;
    try {
        if (editingCourse.value?.id) {
            await courseStore.updateCourse(editingCourse.value.id, formData, transferAll);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Course updated successfully', life: 5000 });
        } else {
            await courseStore.createCourse(formData);
            toast.add({ severity: 'success', summary: 'Created', detail: 'Course created successfully', life: 5000 });
        }
        formVisible.value = false;
    } catch (err: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Failed to save course', life: 5000 });
    } finally {
        submitting.value = false;
    }
}

async function onDeleteCourse(course: any) {
    const isSeries = !!course.seriesId;

    confirm.require({
        message: isSeries
            ? `Do you want to delete only this instance or all upcoming workouts in this series?`
            : `Delete "${course.title}"? This cannot be undone.`,
        header: isSeries ? 'Series Detected' : 'Dangerous Action',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: {
            label: isSeries ? 'Delete Series' : 'Delete',
            severity: 'danger'
        },
        rejectProps: {
          label: isSeries ? 'Delete Only This' : 'Cancel',
          severity: isSeries ? 'warn' : 'primary',
        },
        accept: async () => {
            try {
                await courseStore.deleteCourse(course.id, isSeries);
                toast.add({ severity: 'warn', summary: 'Deleted', detail: isSeries ? 'Series removed' : 'Course removed', life: 5000 });
                formVisible.value = false;
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
            }
        },
        reject: async () => {
            if (isSeries) {
                try {
                    await courseStore.deleteCourse(course.id, false);
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Single course removed', life: 5000 });
                    formVisible.value = false;
                } catch (e) {
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
                }
            }
        }
    });
}

async function onBookingChanged() {
    detailVisible.value = false;
    fetchCourses();
}

function formatDuration(min: number) {
    if (min < 60) return `${min}min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h} hour${h > 1 ? 's' : ''}`;
}

onMounted(() => {
    fetchCourses();
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

</script>

<template>
  <div
    class="home-view"
    :class="{ 'is-mobile-view': isMobile }"
  >
    <div class="container">
      <header class="home-header">
        <div
          v-if="!isMobile"
          class="header-left"
        >
          <h1>Athletic Schedule</h1>
          <p class="text-muted">
            Master your discipline. Book your next session.
          </p>
        </div>

        <div class="header-right">
          <div
            v-if="!isMobile"
            class="view-toggle"
          >
            <span :class="{ active: !isCompactView }">STANDARD</span>
            <ToggleSwitch v-model="isCompactView" />
            <span :class="{ active: isCompactView }">COMPACT</span>
          </div>
        </div>
      </header>

      <div v-if="isMobile">
        <MobileCalendar
          v-model:base-date="baseDate"
          :courses="courses"
          :cycle-info="cycleInfo"
          :user-id="authStore.user?.id"
          :loading="courseStore.loading"
          @course-click="handleCourseClick"
        />
      </div>
      <div v-else>
        <WeeklyCalendar
          v-model:base-date="baseDate"
          :courses="courses"
          :cycle-info="cycleInfo"
          :is-compact-view="isCompactView"
          :user-id="authStore.user?.id"
          :loading="courseStore.loading"
          @course-click="handleCourseClick"
          @cell-click="handleCellClick"
        />
      </div>
    </div>

    <!-- Details Dialog -->
    <Dialog
      v-model:visible="detailVisible"
      :header="selectedCourse?.title"
      :modal="true"
      :position="isMobile ? 'bottom' : 'center'"
      class="w-full max-w-md athletic-dialog"
      :class="{ 'mobile-full-width': isMobile }"
    >
      <CourseDetails
        v-if="selectedCourse"
        :course="selectedCourse"
        :settings="settings"
        :is-past-course="isPastCourse"
        :is-outside-booking-window="isOutsideBookingWindow"
        :is-trial-restricted="isTrialRestricted"
        :booking-window-message="bookingWindowMessage"
        :is-member-mode="isMemberMode"
        @booked="onBookingChanged"
        @unbooked="onBookingChanged"
      />
    </Dialog>

    <!-- Create/Edit Dialog -->
    <Dialog
      v-model:visible="formVisible"
      :header="editingCourse?.id ? 'Modify Workout' : 'Launch New Workout'"
      :modal="true"
      :position="isMobile ? 'bottom' : 'center'"
      class="w-full max-w-lg"
      :class="{ 'mobile-full-width': isMobile }"
    >
      <CourseForm
        :course="editingCourse"
        :loading="submitting"
        @save="onSaveCourse"
        @cancel="formVisible = false"
        @delete="onDeleteCourse"
      />
    </Dialog>
  </div>
</template>

<style scoped lang="scss">
.home-view {
    padding: 2rem 0;

    &.is-mobile-view {
        padding: 0;
        .container { padding: 0; max-width: none; }
        .home-header {
            padding: 2rem 1.5rem 1rem;
            margin-bottom: 1rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 1.5rem;
            h1 { font-size: 2.25rem; }

            .header-right {
                width: 100%;
                align-items: flex-start;
            }

            .header-badge {
                padding: 0.5rem 1rem;
                font-size: 0.75rem;
            }
        }
    }
}

.home-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 3rem;

    h1 { margin: 0; font-size: 3.5rem; letter-spacing: -0.02em; }
    p { font-size: 1.1rem; font-weight: 500; }
}

.header-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 1.5rem;
}

.view-toggle {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800;
    font-size: 0.85rem;
    color: var(--text-muted);

    span.active {
        color: var(--text-header);
    }
}

.header-badge {
    background: #0f172a;
    color: var(--primary-color);
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid var(--primary-color);

    .pulse {
        width: 8px;
        height: 8px;
        background: var(--primary-color);
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
        animation: pulse 2s infinite;
    }
}

@keyframes pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
}

.mobile-full-width {
    :deep(.p-dialog-content) { padding: 1.5rem; }
    :deep(.p-dialog-header) { padding: 1.5rem; border-top-left-radius: 20px; border-top-right-radius: 20px; }
}

</style>
