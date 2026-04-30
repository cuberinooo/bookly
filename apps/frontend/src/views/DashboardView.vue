<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { BookingWindow } from '../app/enums/BookingWindow';
import CourseForm from '../components/CourseForm.vue';
import ManagedCoursesTable from '../components/ManagedCoursesTable.vue';
import ParticipantsDialog from '../components/ParticipantsDialog.vue';
import NotificationItem from '../components/NotificationItem.vue';
import { useRoute } from 'vue-router';

import { formatDateTime } from '../services/date-utils';

const toast = useToast();
const confirm = useConfirm();
const route = useRoute();
const courses = ref<any[]>([]);
const notifications = ref<any[]>([]);
const isTrainerMode = computed(() => authStore.isElevated() && authStore.viewMode === 'trainer');
const dashboardLabel = computed(() => isTrainerMode.value ? (authStore.isAdmin() ? 'Admin Dashboard' : 'Trainer Dashboard') : 'My bookings');

const courseTable = ref<any>(null);
const courseDialog = ref(false);
const editingCourse = ref<any>(null);
const submitting = ref(false);

const participantsDialog = ref(false);
const selectedCourse = ref<any>(null);

const loading = ref(true);

const settings = ref<any>({
  showParticipantNames: true,
  isWaitlistVisible: true,
  bookingWindow: BookingWindow.OFF
});

function isOutsideBookingWindow(course: any) {
    if (!settings.value || settings.value.bookingWindow === BookingWindow.OFF) {
        return false;
    }

    const start = new Date(course.startTime);
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
}

function getBookingWindowMessage() {
    switch (settings.value?.bookingWindow) {
        case BookingWindow.CURRENT_WEEK: return 'Bookings only for current week.';
        case BookingWindow.TWO_WEEKS: return 'Bookings only for next 2 weeks.';
        case BookingWindow.MONTH: return 'Bookings only for next month.';
        default: return 'Outside booking window.';
    }
}

async function fetchData() {
    try {
        let url = '/courses?all=true&futureOnly=true';
        if (isTrainerMode.value) {
            url += `&trainerId=${authStore.user?.id}`;
        } else {
            url += `&memberId=${authStore.user?.id}`;
        }

        const response = await api.get(url);
        courses.value = response.data;
        
        if (isTrainerMode.value) {
            courseTable.value?.refresh();
            fetchNotifications();
        }

        loading.value = true;
        const responseSettings = await api.get('/settings');
        settings.value = {
          showParticipantNames: responseSettings.data.showParticipantNames ?? true,
          isWaitlistVisible: responseSettings.data.isWaitlistVisible ?? true,
          bookingWindow: responseSettings.data.bookingWindow ?? BookingWindow.OFF
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: `Failed to fetch ${dashboardLabel.value.toLowerCase()} data`, life: 5000 });
        loading.value = false;
    }
}

watch(() => authStore.viewMode, () => {
    fetchData();
});

async function fetchNotifications() {
    try {
        const response = await api.get('/notifications');
        notifications.value = response.data;
    } catch (e) {}
}

function openNewCourse() {
    editingCourse.value = {
        title: 'Functional Training',
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
            const url = transferAll ? `/courses/${editingCourse.value.id}?transferAll=true` : `/courses/${editingCourse.value.id}`;
            await api.patch(url, formData);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Course updated', life: 5000 });
        } else {
            await api.post('/courses', formData);
            toast.add({ severity: 'success', summary: 'Created', detail: 'Course created', life: 5000 });
        }
        courseDialog.value = false;
        fetchData();
        courseTable.value?.refresh();
    } catch (e: any) {
        const errorDetail = e.response?.data?.error || 'Operation failed';
        toast.add({ severity: 'error', summary: 'Error', detail: errorDetail, life: 5000 });
    } finally {
        submitting.value = false;
    }
}

async function unbookCourse(courseId: number) {
    confirm.require({
        message: 'Cancel this booking?',
        header: 'Confirmation',
        icon: 'pi pi-calendar-times',
        acceptProps: { severity: 'danger', label: 'Yes, cancel' },
        rejectProps: {
          label: 'Cancel',
          severity: 'primary', // Use base styling
        },
        accept: async () => {
            try {
                await api.delete(`/courses/${courseId}/book`);
                toast.add({ severity: 'info', summary: 'Cancelled', detail: 'Booking removed', life: 5000 });
                fetchData();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to cancel booking', life: 5000 });
            }
        }
    });
}

async function onDeleteCourse(course: any) {
    const isSeries = !!course.seriesId;

    confirm.require({
        message: isSeries
            ? `Do you want to delete the entire series "${course.title}"? This cannot be undone.`
            : `Delete "${course.title}"? This cannot be undone.`,
        header: isSeries ? 'Series Detected' : 'Dangerous Action',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: {
            label: isSeries ? 'Delete Entire Series' : 'Delete',
            severity: 'danger'
        },
        rejectProps: {
          label: isSeries ? 'Delete Only This' : 'Cancel',
          severity: isSeries ? 'warn' : 'primary',
        },
        accept: async () => {
            try {
                const url = isSeries ? `/courses/${course.id}?deleteAll=true` : `/courses/${course.id}`;
                await api.delete(url);
                toast.add({ severity: 'warn', summary: 'Deleted', detail: isSeries ? 'Series removed' : 'Course removed', life: 5000 });
                courseDialog.value = false;
                fetchData();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
            }
        },
        reject: async () => {
            if (isSeries) {
                try {
                    await api.delete(`/courses/${course.id}`);
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Single instance removed', life: 5000 });
                    courseDialog.value = false;
                    fetchData();
                } catch (e) {
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
                }
            }
        }
    });
}

function formatDuration(min: number) {
    if (min < 60) return `${min}min`;
    const hours = Math.floor(min / 60);
    const remaining = min % 60;
    return remaining > 0 ? `${hours}h ${remaining}min` : `${hours} hour${hours > 1 ? 's' : ''}`;
}

onMounted(fetchData);
</script>

<template>
  <div class="dashboard-container px-4 md:px-0">
    <div class="header-with-action flex flex-col md:flex-row md:justify-between md:items-center gap-6 mb-8 md:mb-16">
      <div>
        <h1 class="text-4xl md:text-[3.5rem]">{{ dashboardLabel }}</h1>
        <p class="text-muted text-sm md:text-lg">
          Manage your athletic journey
        </p>
      </div>
      <Button
        v-if="isTrainerMode"
        label="New Course"
        icon="pi pi-plus"
        severity="primary"
        size="large"
        class="w-full md:w-auto py-4 md:py-3"
        @click="openNewCourse"
      />
    </div>

    <div
      v-if="isTrainerMode"
      class="trainer-layout flex flex-col lg:grid lg:grid-columns-[1fr_380px] gap-8 md:gap-12"
    >
      <div class="main-content order-2 lg:order-1">
        <ManagedCoursesTable
          ref="courseTable"
          @edit="editCourse"
        />
      </div>

      <aside class="notifications-panel order-1 lg:order-2">
        <div class="panel-header mb-4">
          <h2 class="text-xl md:text-2xl font-black uppercase tracking-tight" style="font-family: 'Barlow Condensed', sans-serif;">Live Feed</h2>
        </div>
        <div class="notif-list">
          <NotificationItem
            v-for="notif in notifications"
            :key="notif.id"
            :notification="notif"
            @read="(id) => api.patch(`/notifications/${id}/read`).then(fetchNotifications)"
          />
          <div
            v-if="notifications.length === 0"
            class="empty-notifs"
          >
            <i class="pi pi-bell-slash" />
            <p>No new alerts</p>
          </div>
        </div>
      </aside>
    </div>

    <div
      v-else
      class="member-layout"
    >
      <section>
        <div class="pb-4">
          <h2>My Scheduled Bookings</h2>
          <Button
            label="View Schedule"
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
          <p>Ready to train? Your schedule is empty.</p>
          <Button
            severity="primary"
            label="Explore Courses"
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
                    v-if="course.bookings.find(b => b.member.email === authStore.user?.email)?.isWaitlist"
                    class="waitlist-indicator"
                  >WAITLIST QUEUE</span>
                </div>
                <span class="duration-tag ml-2">{{ formatDuration(course.durationMinutes) }}</span>
              </div>
            </template>
            <template #content>
              <div class="flex flex-col gap-4 py-3">
                <div class="info-row">
                  <i class="pi pi-user" />
                  <div>
                    <label>TRAINER</label>
                    <span>{{ course.trainer.name }}</span>
                  </div>
                </div>
                
                <div class="schedule-focus-row">
                  <div class="focus-item">
                    <i class="pi pi-calendar" />
                    <div>
                      <label>DATE & TIME</label>
                      <span>{{ formatDateTime(course.startTime) }}</span>
                    </div>
                  </div>
                  <div class="focus-item border-left pl-3">
                    <i class="pi pi-clock" />
                    <div>
                      <label>DURATION</label>
                      <span>{{ formatDuration(course.durationMinutes) }}</span>
                    </div>
                  </div>
                </div>

                <div class="info-row">
                  <i class="pi pi-users" />
                  <div>
                    <label>AVAILABLE SLOTS</label>
                    <span>{{ course.bookings.filter(b => !b.isWaitlist).length }} / {{ course.capacity }}</span>
                  </div>
                </div>
              </div>
            </template>
            <template #footer>
              <div class="flex flex-col gap-2 w-full">
                <Button
                  v-if="settings.showParticipantNames"
                  label="SHOW PARTICIPANTS"
                  icon="pi pi-users"
                  variant="outlined"
                  class="w-full show-btn"
                  @click="selectedCourse = course; participantsDialog = true"
                />
                <Button
                  label="CANCEL BOOKING"
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

    <!-- Course Form Dialog -->
    <Dialog
      v-model:visible="courseDialog"
      :header="editingCourse?.id ? 'Modify Course' : 'Create New Course'"
      :modal="true"
      class="w-full max-w-lg"
    >
      <CourseForm
        :course="editingCourse"
        :loading="submitting"
        @save="onSaveCourse"
        @cancel="courseDialog = false"
        @delete="onDeleteCourse"
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

.notif-list {
    background: #f8fafc;
    border: 1px solid var(--border-color);
    max-height: 700px;
    overflow-y: auto;
    border-radius: 16px;
}

.notif-item {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: white;
    transition: all 0.2s;

    &:last-child { border-bottom: none; }

    &.unread {
        background-color: #fffbeb;
        border-left: 6px solid var(--primary-color);
    }

    p { margin: 0; color: var(--text-header); font-weight: 600; line-height: 1.4; }
    small { color: var(--text-muted); font-family: 'Barlow Condensed', sans-serif; font-weight: 700; text-transform: uppercase; }
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

    &:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
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
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
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

.empty-notifs {
    padding: 4rem 2rem;
    text-align: center;
    color: #94a3b8;
    i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.5; }
    p { font-family: 'Barlow Condensed', sans-serif; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
}
</style>
