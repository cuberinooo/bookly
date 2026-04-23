<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import WeeklyCalendar from '../components/WeeklyCalendar.vue';
import CourseForm from '../components/CourseForm.vue';

const toast = useToast();
const courses = ref<any[]>([]);
const loading = ref(false);
const submitting = ref(false);

const selectedCourse = ref<any>(null);
const detailVisible = ref(false);
const formVisible = ref(false);
const editingCourse = ref<any>(null);

async function fetchCourses() {
  loading.value = true;
  try {
    const response = await api.get('/courses');
    courses.value = response.data;
  } catch (err) {
    console.error('Failed to fetch courses', err);
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load courses' });
  } finally {
    loading.value = false;
  }
}

function handleCourseClick(course: any) {
    if (authStore.isTrainer() && course.trainer?.id === authStore.user?.id) {
        editingCourse.value = course;
        formVisible.value = true;
    } else {
        selectedCourse.value = course;
        detailVisible.value = true;
    }
}

function handleCellClick(date: Date) {
    if (!authStore.isTrainer()) return;
    editingCourse.value = {
        startTime: date,
        title: 'Functional Training',
        capacity: 10,
        durationMinutes: 60,
        description: ''
    };
    formVisible.value = true;
}

async function onSaveCourse(formData: any) {
    submitting.value = true;
    try {
        if (editingCourse.value?.id) {
            await api.patch(`/courses/${editingCourse.value.id}`, formData);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Course updated successfully' });
        } else {
            await api.post('/courses', formData);
            toast.add({ severity: 'success', summary: 'Created', detail: 'Course created successfully' });
        }
        formVisible.value = false;
        fetchCourses();
    } catch (err: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Failed to save course' });
    } finally {
        submitting.value = false;
    }
}

async function bookCourse(courseId: number) {
  if (!authStore.isLoggedIn()) {
    toast.add({ severity: 'info', summary: 'Login Required', detail: 'Please login to book a course' });
    return;
  }
  try {
    await api.post(`/courses/${courseId}/book`);
    toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Booking confirmed!' });
    detailVisible.value = false;
    fetchCourses();
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Booking failed' });
  }
}

async function unbookCourse(courseId: number) {
    try {
        await api.delete(`/courses/${courseId}/book`);
        toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Booking cancelled' });
        detailVisible.value = false;
        fetchCourses();
    } catch (err: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to cancel booking' });
    }
}

function formatDuration(min: number) {
    if (min < 60) return `${min}min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h} hour${h > 1 ? 's' : ''}`;
}

onMounted(fetchCourses);
</script>

<template>
  <main class="home-view p-4">
    <div class="container">
        <header class="flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="m-0">Athletic Schedule</h1>
                <p class="text-muted">Book your next workout session</p>
            </div>
        </header>

        <WeeklyCalendar
            :courses="courses"
            @course-click="handleCourseClick"
            @cell-click="handleCellClick"
        />
    </div>

    <!-- Details Dialog -->
    <Dialog v-model:visible="detailVisible" :header="selectedCourse?.title" :modal="true" class="w-full max-w-md">
        <div v-if="selectedCourse" class="py-2">
            <div class="flex align-items-center gap-3 mb-4 p-3 bg-primary-50 border-round">
                <i class="pi pi-user text-primary text-2xl"></i>
                <div class="flex flex-column">
                    <small class="text-muted font-bold uppercase">Coach</small>
                    <span class="font-bold text-lg">{{ selectedCourse.trainer?.name }}</span>
                </div>
            </div>

            <p class="mb-4 text-slate-600 line-height-3">{{ selectedCourse.description }}</p>

            <div class="grid mb-4">
                <div class="col-6">
                    <div class="info-card">
                        <small>DATE</small>
                        <span>{{ new Date(selectedCourse.startTime).toLocaleDateString() }}</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="info-card">
                        <small>TIME</small>
                        <span>{{ new Date(selectedCourse.startTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="info-card">
                        <small>DURATION</small>
                        <span>{{ formatDuration(selectedCourse.durationMinutes) }}</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="info-card">
                        <small>AVAILABILITY</small>
                        <span>{{ selectedCourse.capacity - selectedCourse.bookings.length }} spots left</span>
                    </div>
                </div>
            </div>

            <div v-if="!authStore.isTrainer()">
                <Button v-if="!selectedCourse.bookings.some((b: any) => b.member?.id === authStore.user?.id)"
                        label="Reserve Spot" class="w-full p-3" @click="bookCourse(selectedCourse.id)"
                        :disabled="selectedCourse.bookings.length >= selectedCourse.capacity" />
                <Button v-else label="Cancel Reservation" severity="danger" variant="text" class="w-full p-3" @click="unbookCourse(selectedCourse.id)" />
            </div>
        </div>
    </Dialog>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="formVisible" :header="editingCourse?.id ? 'Edit Course' : 'New Course'" :modal="true" class="w-full max-w-lg">
        <CourseForm
            :course="editingCourse"
            :loading="submitting"
            @save="onSaveCourse"
            @cancel="formVisible = false"
        />
    </Dialog>
  </main>
</template>

<style scoped lang="scss">
.info-card {
    background: var(--surface-ground);
    padding: 0.75rem;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    height: 100%;

    small {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }

    span {
        font-weight: 600;
    }
}
</style>
