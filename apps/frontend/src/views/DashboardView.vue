<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import api from '../services/api';
import { useAuthStore } from '../store/useAuthStore';
import { useCourseStore } from '../store/useCourseStore';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { BookingWindow } from '../app/enums/BookingWindow';
import CourseForm from '../components/CourseForm.vue';
import ParticipantsDialog from '../components/ParticipantsDialog.vue';
import TrialStatusCard from '../components/TrialStatusCard.vue';
import TrainerDashboard from '../components/TrainerDashboard.vue';
import InactiveAccountAlert from '../components/InactiveAccountAlert.vue';
import { useRoute } from 'vue-router';
import { useCourseDeletion } from '../composables/useCourseDeletion';

import { formatDateWithDay, formatTime } from '../services/date-utils';
import {useI18n} from "vue-i18n";

const toast = useToast();
const { t, locale } = useI18n();
const confirm = useConfirm();
const route = useRoute();
const authStore = useAuthStore();
const courseStore = useCourseStore();
const { confirmDeleteCourse } = useCourseDeletion();
const courses = computed(() => courseStore.courseList);
const notifications = ref<any[]>([]);
const isTrainerMode = computed(() => authStore.isTrainer && authStore.viewMode === 'trainer');
const dashboardLabel = computed(() => isTrainerMode.value ? (authStore.isAdmin ? t('dashboard.admin') : t('dashboard.trainerDashboard')) : t('app.myBookings'));

const trainerDashboard = ref<any>(null);
const courseDialog = ref(false);
const editingCourse = ref<any>(null);
const submitting = ref(false);

const participantsDialog = ref(false);
const selectedCourse = ref<any>(null);

const localLoading = ref(false);
const isLoading = computed(() => courseStore.isLoading || localLoading.value);

const settings = ref<any>({
  showParticipantNames: true,
  isWaitlistVisible: true,
  bookingWindow: BookingWindow.OFF,
  trialBookingLimit: 0
});

const trialInfo = ref({
    count: 0,
    limit: 0
});

const isTrialMember = computed(() => authStore.user?.roles.includes('ROLE_TRIAL'));

async function fetchData() {
    localLoading.value = true;
    try {
        let params: any = { all: true, futureOnly: true };
        if (isTrainerMode.value) {
            params.trainerId = authStore.user?.id;
        } else {
            params.memberId = authStore.user?.id;
        }

        await courseStore.fetchCourses(params);

        if (isTrainerMode.value) {
            trainerDashboard.value?.refreshTable();
        }

        const [responseSettings, responseMe] = await Promise.all([
            api.get('/settings'),
            api.get('/user/me')
        ]);

        settings.value = {
          showParticipantNames: responseSettings.data.showParticipantNames ?? true,
          isWaitlistVisible: responseSettings.data.isWaitlistVisible ?? true,
          bookingWindow: responseSettings.data.bookingWindow ?? BookingWindow.OFF,
          trialBookingLimit: responseSettings.data.trialBookingLimit ?? 0
        };

        if (isTrialMember.value) {
            trialInfo.value = {
                count: responseMe.data.bookingCount ?? 0,
                limit: responseSettings.data.trialBookingLimit ?? 0
            };
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('dashboard.fetchError', { dashboard: dashboardLabel.value.toLowerCase() }), life: 5000 });
    } finally {
        localLoading.value = false;
    }
}

watch(() => authStore.viewMode, () => {
    fetchData();
});

function openNewCourse() {
    editingCourse.value = {
        title: t('course.defaultTitle'),
        capacity: 10,
        startTime: new Date(),
        durationMinutes: 60,
        description: ''
    };
    courseDialog.value = true;
}

function editCourse(course: any) {
    editingCourse.value = { ...course, startTime: new Date(course.startTime) };
    courseDialog.value = true;
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
        courseDialog.value = false;
        if (isTrainerMode.value) {
            trainerDashboard.value?.refreshTable();
        }
    } catch (e: any) {
        const errorDetail = e.response?.data?.error || t('app.error');
        toast.add({ severity: 'error', summary: t('app.error'), detail: errorDetail, life: 5000 });
    } finally {
        submitting.value = false;
    }
}

async function unbookCourse(courseId: number) {
    confirm.require({
        message: t('dashboard.cancelConfirm'),
        header: t('app.confirmation'),
        icon: 'pi pi-calendar-times',
        acceptProps: { severity: 'danger', label: t('app.yes') },
        rejectProps: {
          label: t('app.cancel'),
          severity: 'primary', // Use base styling
        },
        accept: async () => {
            try {
                await courseStore.unbookCourse(courseId);
                toast.add({ severity: 'info', summary: t('dashboard.cancelled'), detail: t('dashboard.cancelBookingSuccess'), life: 5000 });
            } catch (e) {
                toast.add({ severity: 'error', summary: t('app.error'), detail: t('dashboard.cancelBookingError'), life: 5000 });
            }
        }
    });
}

function formatDuration(min: number) {
    if (min < 60) return `${min}${t('app.minutesShort')}`;
    const hoursCount = Math.floor(min / 60);
    const remaining = min % 60;
    return remaining > 0 
        ? `${hoursCount}${t('app.hourShort')} ${remaining}${t('app.minutesShort')}` 
        : `${hoursCount}${t('app.hourShort')}`;
}

onMounted(fetchData);
</script>

<template>
  <div class="dashboard-container px-2 md:px-0">
    <div class="header-with-action flex flex-col md:flex-row md:justify-between md:items-center gap-6 mb-8 md:mb-16">
      <div class="hidden md:block">
        <h1 class="text-4xl md:text-[3.5rem]">
          {{ dashboardLabel }}
        </h1>
        <p class="text-muted text-sm md:text-lg">
          {{ t('dashboard.subtitle') }}
        </p>
      </div>
      <Button
        v-if="isTrainerMode"
        :label="t('dashboard.newCourse')"
        icon="pi pi-plus"
        severity="primary"
        size="large"
        class="w-full md:w-auto py-4 md:py-3"
        @click="openNewCourse"
      />

      <TrialStatusCard
        v-if="isTrialMember && !isTrainerMode && trialInfo.limit > 0"
        :count="trialInfo.count"
        :limit="trialInfo.limit"
        class="md:w-80"
      />
    </div>

    <InactiveAccountAlert class="mb-12" />

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex flex-col items-center justify-center py-20"
    >
      <i class="pi pi-spin pi-spinner text-4xl text-primary mb-4" />
      <p class="font-bold uppercase tracking-widest text-slate-500">
        {{ t('dashboard.syncing') }}
      </p>
    </div>

    <div v-show="!isLoading">
      <div
        v-if="isTrainerMode"
        class="trainer-layout-wrapper"
      >
        <TrainerDashboard
          ref="trainerDashboard"
          @edit-course="editCourse"
        />
      </div>

      <div
        v-else
        class="member-layout"
      >
        <section>
          <div class="pb-4">
            <h2>{{ t('dashboard.myScheduledBookings') }}</h2>
            <Button
              :label="t('dashboard.viewSchedule')"
              icon="pi pi-calendar"
              variant="text"
              @click="$router.push('/')"
            />
          </div>

          <div
            v-if="courses.length === 0"
            class="empty-state"
          >
            <i class="pi pi-calendar-plus" />
            <p>{{ t('dashboard.emptySchedule') }}</p>
            <Button
              severity="primary"
              :label="t('dashboard.exploreCourses')"
              icon="pi pi-search"
              size="large"
              class="mt-4"
              @click="$router.push('/')"
            />
          </div>

          <div
            v-else
            class="bookings-grid"
          >
            <Card
              v-for="course in courses"
              :key="course.id"
              class="booking-card"
            >
              <template #title>
                <div class="flex text-black justify-content-between align-items-start">
                  <div class="flex flex-col">
                    <span>{{ course.title }}</span>
                    <span
                      v-if="course.bookings.find(b => b.user.email === authStore.user?.email)?.isWaitlist"
                      class="waitlist-indicator"
                    >{{ t('dashboard.waitlistQueue') }}</span>
                  </div>
                </div>
              </template>
              <template #content>
                <div class="flex flex-col gap-4 py-3">
                  <div class="info-row">
                    <i class="pi pi-user" />
                    <div>
                      <label>{{ t('dashboard.trainer') }}</label>
                      <span>{{ course.user.name }}</span>
                    </div>
                  </div>

                  <div class="schedule-focus-row">
                    <div class="focus-item">
                      <i class="pi pi-calendar" />
                      <div>
                        <label>{{ t('dashboard.dateTime') }}</label>
                        <span>{{ formatDateWithDay(course.startTime, false, locale) }} @ {{ formatTime(course.startTime, locale) }}</span>
                      </div>
                    </div>
                    <div class="focus-item border-left pl-3">
                      <i class="pi pi-clock" />
                      <div>
                        <label>{{ t('dashboard.duration') }}</label>
                        <span>{{ formatDuration(course.durationMinutes) }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="info-row">
                    <i class="pi pi-users" />
                    <div>
                      <label>{{ t('dashboard.availableSlots') }}</label>
                      <span>{{ course.bookings.filter(b => !b.isWaitlist).length }} / {{ course.capacity }}</span>
                    </div>
                  </div>
                </div>
              </template>
              <template #footer>
                <div class="flex flex-col gap-2 w-full">
                  <Button
                    v-if="settings.isWaitlistVisible"
                    :label="t('dashboard.showParticipants')"
                    icon="pi pi-users"
                    variant="outlined"
                    class="w-full show-btn"
                    @click="selectedCourse = course; participantsDialog = true"
                  />
                  <Button
                    v-if="authStore.user?.isActive !== false"
                    :label="t('course.cancelReservation')"
                    severity="danger"
                    variant="text"
                    icon="pi pi-times"
                    class="w-full cancel-btn"
                    @click="unbookCourse(course.id)"
                  />
                </div>
              </template>
            </Card>
          </div>
        </section>
      </div>
    </div>


    <!-- Course Form Dialog -->
    <Dialog
      v-model:visible="courseDialog"
      :header="editingCourse?.id ? t('dashboard.modifyCourse') : t('dashboard.createNewCourse')"
      :modal="true"
      class="w-full max-w-lg"
    >
      <CourseForm
        :course="editingCourse"
        :loading="submitting"
        @save="onSaveCourse"
        @cancel="courseDialog = false"
        @delete="confirmDeleteCourse($event, () => courseDialog = false)"
      />
    </Dialog>

    <ParticipantsDialog
      v-model:visible="participantsDialog"
      :course="selectedCourse"
    />
  </div>
</template>

<style scoped lang="scss">
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 0;
    touch-action: pan-y;
    overflow-x: hidden;
}

.header-with-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4rem;
    h1 { margin: 0; font-size: 3.5rem; letter-spacing: -0.02em; }
    p { font-size: 1.1rem; font-weight: 500; }
}

.trainer-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 3rem;
}

@media (max-width: 1200px) {
    .trainer-layout { grid-template-columns: 1fr; }
}

.mark-read-btn {
    color: var(--primary-color) !important;
    &:hover { background: #fef3c7 !important; }
}

.duration-tag {
    background: #1e293b;
    color: var(--primary-color);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 900;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 0.05em;
}

.waitlist-indicator {
    font-size: 0.65rem;
    font-weight: 900;
    color: #f59e0b;
    letter-spacing: 0.1em;
    font-family: 'Barlow Condensed', sans-serif;
    margin-top: 4px;
}

.booking-card {
    border-top: 4px solid var(--primary-color) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

    @media (hover: hover) {
        &:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }
    }
}

.info-row {
    display: flex;
    align-items: center;
    gap: 1.25rem;

    i {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border-radius: 10px;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    label {
        display: block;
        font-size: 0.65rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 2px;
    }

    span { font-weight: 700; color: var(--text-header); }
}

.schedule-focus-row {
    display: flex;
    align-items: center;
    background: #f8fafc;
    padding: 1rem;
    border-radius: 10px;
    border-left: 4px solid var(--primary-color);
    margin: 0.5rem 0;

    .focus-item {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 0.75rem;

        i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        label {
            display: block;
            font-size: 0.6rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 2px;
        }

        span {
            font-weight: 800;
            color: var(--text-header);
            font-size: 0.9rem;
        }

        &.border-left {
            border-left: 1px solid var(--border-color);
        }
    }
}

.bookings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr));
    gap: 2.5rem;
}

.empty-state {
    text-align: center;
    padding: 8rem 2rem;
    background: #f8fafc;
    border-radius: 20px;
    border: 2px dashed #cbd5e1;

    i { color: #cbd5e1; font-size: 4rem; margin-bottom: 1.5rem; }
    p { font-size: 1.5rem; font-weight: 700; color: #64748b; }
}

.cancel-btn {
    font-weight: 800 !important;
    letter-spacing: 0.1em !important;
    margin-top: 0.5rem;
    &:hover { background: #fef2f2 !important; }
}

.show-btn {
    border-color: var(--border-color) !important;
    color: var(--text-header) !important;
    font-weight: 800 !important;
    letter-spacing: 0.1em !important;
    &:hover { background: #f8fafc !important; border-color: var(--text-muted) !important; }
}

</style>
