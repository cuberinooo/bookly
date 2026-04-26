<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import WeeklyCalendar from '../components/WeeklyCalendar.vue';
import MobileCalendar from '../components/MobileCalendar.vue';
import CourseForm from '../components/CourseForm.vue';

const toast = useToast();
const confirm = useConfirm();
const courses = ref<any[]>([]);
const loading = ref(false);
const submitting = ref(false);

const selectedCourse = ref<any>(null);
const detailVisible = ref(false);
const formVisible = ref(false);
const editingCourse = ref<any>(null);
const isCompactView = ref(true);

const isMobile = ref(window.innerWidth <= 768);

const isTrainerMode = computed(() => authStore.isTrainer() && authStore.viewMode === 'trainer');
const isMemberMode = computed(() => !authStore.isLoggedIn() || !authStore.isTrainer() || authStore.viewMode === 'member');

const isPastCourse = computed(() => {
    if (!selectedCourse.value?.endTime) return false;
    return new Date(selectedCourse.value.endTime) < new Date();
});

function handleResize() {
    isMobile.value = window.innerWidth <= 768;
}

watch(() => authStore.viewMode, () => {
    fetchCourses();
});

async function fetchCourses() {
  loading.value = true;
  try {
    const response = await api.get('/courses?all=true');
    courses.value = response.data;
  } catch (err) {
    console.error('Failed to fetch courses', err);
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load courses', life: 5000 });
  } finally {
    loading.value = false;
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
            const url = transferAll ? `/courses/${editingCourse.value.id}?transferAll=true` : `/courses/${editingCourse.value.id}`;
            await api.patch(url, formData);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Course updated successfully', life: 5000 });
        } else {
            await api.post('/courses', formData);
            toast.add({ severity: 'success', summary: 'Created', detail: 'Course created successfully', life: 5000 });
        }
        formVisible.value = false;
        fetchCourses();
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
                const url = isSeries ? `/courses/${course.id}?deleteAll=true` : `/courses/${course.id}`;
                await api.delete(url);
                toast.add({ severity: 'warn', summary: 'Deleted', detail: isSeries ? 'Series removed' : 'Course removed', life: 5000 });
                formVisible.value = false;
                fetchCourses();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
            }
        },
        reject: async () => {
            if (isSeries) {
                try {
                    await api.delete(`/courses/${course.id}`);
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Single course removed', life: 5000 });
                    formVisible.value = false;
                    fetchCourses();
                } catch (e) {
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
                }
            }
        }
    });
}

async function bookCourse(courseId: number) {
  if (!authStore.isLoggedIn()) {
    toast.add({ severity: 'info', summary: 'Login Required', detail: 'Please login to book a course', life: 5000 });
    return;
  }
  try {
    await api.post(`/courses/${courseId}/book`);
    toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Booking confirmed!', life: 5000 });
    detailVisible.value = false;
    fetchCourses();
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Booking failed', life: 5000 });
  }
}

async function unbookCourse(courseId: number) {
    try {
        await api.delete(`/courses/${courseId}/book`);
        toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Booking cancelled', life: 5000 });
        detailVisible.value = false;
        fetchCourses();
    } catch (err: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to cancel booking', life: 5000 });
    }
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
  <div class="home-view" :class="{ 'is-mobile-view': isMobile }">
    <div class="container">
        <header class="home-header">
            <div class="header-left">
                <h1>Athletic Schedule</h1>
                <p class="text-muted">Master your discipline. Book your next session.</p>
            </div>

            <div class="header-right" v-if="!isMobile">
                <div class="view-toggle">
                    <span :class="{ active: !isCompactView }">STANDARD</span>
                    <ToggleSwitch v-model="isCompactView" />
                    <span :class="{ active: isCompactView }">COMPACT</span>
                </div>

                <div class="header-badge" v-if="authStore.isTrainer()">
                    <span class="pulse"></span>
                    TRAINER MODE ACTIVE
                </div>
            </div>
        </header>

        <div v-if="isMobile">
            <MobileCalendar
                :courses="courses"
                :user-id="authStore.user?.id"
                @course-click="handleCourseClick"
            />
        </div>
        <div v-else>
            <WeeklyCalendar
                :courses="courses"
                :is-compact-view="isCompactView"
                :user-id="authStore.user?.id"
                @course-click="handleCourseClick"
                @cell-click="handleCellClick"
            />
        </div>
    </div>

    <!-- Details Dialog -->
    <Dialog v-model:visible="detailVisible" :header="selectedCourse?.title" :modal="true" :position="isMobile ? 'bottom' : 'center'" class="w-full max-w-md athletic-dialog" :class="{ 'mobile-full-width': isMobile }">
        <div v-if="selectedCourse" class="workout-details">
            <div class="trainer-info">
                <div class="avatar-placeholder">
                    <i class="pi pi-user"></i>
                </div>
                <div>
                    <small>HEAD COACH</small>
                    <span class="trainer-name">{{ selectedCourse.trainer?.name }}</span>
                </div>
            </div>

            <div class="field">
              <label>Workout Brief</label>
              <Textarea disabled="" class="w-full" :modelValue="selectedCourse.description || 'No description provided.'"/>
            </div>

            <div class="specs-grid">
                <div class="field">
                    <label>DATE</label>
                  <InputText disabled="" class="w-full" :modelValue="new Date(selectedCourse.startTime).toLocaleDateString([], { month: 'long', day: 'numeric' })"/>
                </div>
                <div class="field">
                    <label>TIME</label>
                    <InputText disabled="" class="w-full" :modelValue="new Date(selectedCourse.startTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})"/>
                </div>
                <div class="field">
                    <label>DURATION</label>
                    <InputText disabled="" class="w-full" :modelValue="formatDuration(selectedCourse.durationMinutes)"/>
                </div>
                <div class="field">
                    <label>CAPACITY</label>
                    <InputText disabled="" class="w-full" :modelValue="selectedCourse.bookings.filter(b => !b.isWaitlist).length < selectedCourse.capacity ? (selectedCourse.capacity - selectedCourse.bookings.filter(b => !b.isWaitlist).length) + ' SPOTS LEFT' : 'WAITLIST ACTIVE'"/>
                </div>
            </div>

            <div class="action-footer" v-if="isMemberMode && !isPastCourse">
                <template v-if="selectedCourse.trainer?.id !== authStore.user?.id">
                    <Button v-if="!selectedCourse.bookings.some((b: any) => b.member?.id === authStore.user?.id)"
                            :label="selectedCourse.bookings.filter(b => !b.isWaitlist).length < selectedCourse.capacity ? 'RESERVE SPOT' : 'JOIN WAITLIST'"
                            severity="primary" class="w-full p-4" @click="bookCourse(selectedCourse.id)" />
                    <Button v-else label="CANCEL RESERVATION" severity="primary" variant="text" class="w-full p-4 cancel-btn" @click="unbookCourse(selectedCourse.id)" />
                </template>
                <div v-else class="text-center font-bold text-slate-500 uppercase tracking-widest text-xs">
                    <i class="pi pi-info-circle"></i> You are the coach for this session.
                </div>
            </div>

            <div class="action-footer past-course-info" v-if="isPastCourse">
                <p class="text-center font-bold text-slate-500 uppercase tracking-widest text-xs">
                    <i class="pi pi-lock"></i> This session has already finished.
                </p>
            </div>
        </div>
    </Dialog>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="formVisible" :header="editingCourse?.id ? 'Modify Workout' : 'Launch New Workout'" :modal="true" :position="isMobile ? 'bottom' : 'center'" class="w-full max-w-lg" :class="{ 'mobile-full-width': isMobile }">
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
            h1 { font-size: 2.25rem; }
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
    &:hover { background: #fef2f2 !important; color: #ef4444 !important; }
}

::v-deep(.p-inputtext:disabled) {
  background-color: var(--bg-color) !important;
}

::v-deep(.p-textarea:disabled) {
  background-color: var(--bg-color) !important;
}

.mobile-full-width {
    :deep(.p-dialog-content) { padding: 1.5rem; }
    :deep(.p-dialog-header) { padding: 1.5rem; border-top-left-radius: 20px; border-top-right-radius: 20px; }
}

</style>
