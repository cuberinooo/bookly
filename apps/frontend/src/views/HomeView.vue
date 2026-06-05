<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { formatDate, formatTime, formatDateWithDay } from '../services/date-utils';
import api from '../services/api';
import { useAuthStore } from '../store/useAuthStore';
import { useCourseStore } from '../store/useCourseStore';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { BookingWindow } from '../app/enums/BookingWindow';
import WeeklyCalendar from '../components/WeeklyCalendar.vue';
import MobileCalendar from '../components/MobileCalendar.vue';
import TrainerCourseManageDialog from '../components/TrainerCourseManageDialog.vue';
import CourseForm from '../components/CourseForm.vue';
import CourseDetails from '../components/CourseDetails.vue';

const { t } = useI18n();
const toast = useToast();
const confirm = useConfirm();
const authStore = useAuthStore();
const courseStore = useCourseStore();
const router = useRouter();

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
    if (isTrialRestricted.value) return t('course.trialRestricted');
    if (!isOutsideBookingWindow.value) return '';

    switch (settings.value?.bookingWindow) {
        case BookingWindow.CURRENT_WEEK: return t('course.bookingWindowWeek');
        case BookingWindow.TWO_WEEKS: return t('course.bookingWindowTwoWeeks');
        case BookingWindow.MONTH: return t('course.bookingWindowMonth');
        default: return t('course.bookingWindowOutside');
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
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('course.loadError'), life: 5000 });
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

function handleQuickCreateCourse() {
    const nextHour = new Date();
    nextHour.setHours(nextHour.getHours() + 1, 0, 0, 0);
    editingCourse.value = {
        startTime: nextHour,
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
            toast.add({ severity: 'success', summary: t('app.updated'), detail: t('course.updateSuccess'), life: 5000 });
        } else {
            await courseStore.createCourse(formData);
            toast.add({ severity: 'success', summary: t('app.created'), detail: t('course.createSuccess'), life: 5000 });
        }
        formVisible.value = false;
    } catch (err: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: err.response?.data?.error || t('course.saveError'), life: 5000 });
    } finally {
        submitting.value = false;
    }
}

async function onDeleteCourse(course: any) {
    const isSeries = !!course.seriesId;

    confirm.require({
        message: isSeries
            ? t('course.deleteConfirmSeries')
            : t('course.deleteConfirmSingle', { title: course.title }),
        header: isSeries ? t('course.seriesDetected') : t('course.dangerousAction'),
        icon: 'pi pi-exclamation-triangle',
        acceptProps: {
            label: isSeries ? t('course.deleteSeries') : t('app.delete'),
            severity: 'danger'
        },
        rejectProps: {
          label: isSeries ? t('course.deleteOnlyThis') : t('app.cancel'),
          severity: isSeries ? 'warn' : 'primary',
        },
        accept: async () => {
            try {
                await courseStore.deleteCourse(course.id, isSeries);
                toast.add({ severity: 'warn', summary: t('app.deleted'), detail: isSeries ? t('course.seriesRemoved') : t('course.courseRemoved'), life: 5000 });
                formVisible.value = false;
            } catch (e) {
                toast.add({ severity: 'error', summary: t('app.error'), detail: t('course.deleteError'), life: 5000 });
            }
        },
        reject: async () => {
            if (isSeries) {
                try {
                    await courseStore.deleteCourse(course.id, false);
                    toast.add({ severity: 'warn', summary: t('app.deleted'), detail: t('course.singleRemoved'), life: 5000 });
                    formVisible.value = false;
                } catch (e) {
                    toast.add({ severity: 'error', summary: t('app.error'), detail: t('course.deleteError'), life: 5000 });
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
          <h1>{{ t('home.title') }}</h1>
          <p class="text-muted">
            {{ t('home.subtitle') }}
          </p>
        </div>

        <div class="header-right">
          <div
            v-if="!isMobile"
            class="view-toggle"
          >
            <span :class="{ active: !isCompactView }">{{ t('home.viewStandard') }}</span>
            <ToggleSwitch v-model="isCompactView" />
            <span :class="{ active: isCompactView }">{{ t('home.viewCompact') }}</span>
          </div>
        </div>
      </header>

      <div
        v-if="authStore.isAdmin && courses.length === 0"
        class="onboarding-welcome-banner mb-6 p-6 rounded-2xl border bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-amber-500/20 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
      >
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl bg-amber-500 text-white flex items-center justify-center flex-shrink-0 shadow-md">
            <i class="pi pi-compass text-2xl animate-pulse" />
          </div>
          <div>
            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900 mb-1">
              {{ t('home.welcomeAdminTitle') }}
            </h2>
            <p class="text-sm text-slate-600 max-w-xl">
              {{ t('home.welcomeAdminText') }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
          <Button
            :label="t('home.scheduleFirstCourse')"
            icon="pi pi-calendar-plus"
            severity="primary"
            class="w-full md:w-auto font-bold"
            @click="handleQuickCreateCourse"
          />
          <Button
            label="Settings"
            icon="pi pi-cog"
            severity="secondary"
            variant="outlined"
            class="w-full md:w-auto font-bold bg-white"
            @click="router.push({ name: 'settings' })"
          />
        </div>
      </div>

      <div v-if="isMobile">
        <MobileCalendar
          v-model:base-date="baseDate"
          :courses="courses"
          :cycle-info="cycleInfo"
          :user-id="authStore.user?.id"
          :loading="courseStore.isLoading"
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
          :loading="courseStore.isLoading"
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

    <!-- Create/Edit Dialog (Trainer Mode) -->
    <TrainerCourseManageDialog
      v-if="isTrainerMode"
      v-model:visible="formVisible"
      :course="editingCourse"
      :submitting="submitting"
      @save="onSaveCourse"
      @delete="onDeleteCourse"
      @refresh="fetchCourses"
    />

    <!-- Create/Edit Dialog (Legacy/Member mode - though members usually don't see this) -->
    <Dialog
      v-if="!isTrainerMode"
      v-model:visible="formVisible"
      :header="editingCourse?.id ? t('course.modifyWorkout') : t('course.launchWorkout')"
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
