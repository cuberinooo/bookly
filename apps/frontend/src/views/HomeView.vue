<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const courses = ref<any[]>([]);
const loading = ref(false);
const selectedCourse = ref<any>(null);
const courseVisible = ref(false);
const viewMode = ref<'week' | 'month'>('week');

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const hours = Array.from({ length: 16 }, (_, i) => i + 6); // 6 AM to 9 PM

// Form for new course from calendar click
const createVisible = ref(false);
const newCourseForm = ref({
    title: '',
    description: '',
    capacity: 10,
    startTime: '',
    durationMinutes: 60
});

// Calculate current week dates
const currentWeek = computed(() => {
    const now = new Date();
    const start = new Date(now);
    // Find Monday of current week
    const diff = now.getDay() === 0 ? 6 : now.getDay() - 1;
    start.setDate(now.getDate() - diff);
    start.setHours(0, 0, 0, 0);
    
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        return d;
    });
});

// Calculate month days
const monthDays = computed(() => {
    const now = new Date();
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    // Find the first Monday before or on the 1st
    const startDay = startOfMonth.getDay() === 0 ? 6 : startOfMonth.getDay() - 1;
    const calendarStart = new Date(startOfMonth);
    calendarStart.setDate(startOfMonth.getDate() - startDay);
    
    const daysArr = [];
    const curr = new Date(calendarStart);
    // Show 6 weeks
    for (let i = 0; i < 42; i++) {
        daysArr.push(new Date(curr));
        curr.setDate(curr.getDate() + 1);
    }
    return daysArr;
});

async function fetchCourses() {
  loading.value = true;
  try {
    const response = await api.get('/courses');
    courses.value = response.data;
  } catch (err) {
    console.error('Failed to fetch courses', err);
  } finally {
    loading.value = false;
  }
}

function getCoursesForDay(day: Date) {
    return courses.value.filter(c => {
        const cDate = new Date(c.startTime);
        return cDate.getDate() === day.getDate() &&
               cDate.getMonth() === day.getMonth() &&
               cDate.getFullYear() === day.getFullYear();
    });
}

function getCourseStyle(course: any) {
    const date = new Date(course.startTime);
    const startHour = date.getHours() + date.getMinutes() / 60;
    const duration = course.durationMinutes || 60;
    
    // Grid starts at 6 AM, 50px per hour
    const top = (startHour - 6) * 50; 
    const height = (duration / 60) * 50;
    
    return {
        top: `${top}px`,
        height: `${height}px`
    };
}

function openCourse(course: any) {
    selectedCourse.value = course;
    courseVisible.value = true;
}

function handleCellClick(day: Date, hour?: number) {
    if (!authStore.isTrainer()) return;
    
    const startTime = new Date(day);
    if (hour !== undefined) {
        startTime.setHours(hour, 0, 0, 0);
    } else {
        startTime.setHours(10, 0, 0, 0); // Default to 10 AM for month view
    }
    
    // Adjust for local timezone to match datetime-local input
    const offset = startTime.getTimezoneOffset() * 60000;
    const localISOTime = new Date(startTime.getTime() - offset).toISOString().slice(0, 16);
    
    newCourseForm.value = {
        title: '',
        description: '',
        capacity: 10,
        startTime: localISOTime,
        durationMinutes: 60
    };
    createVisible.value = true;
}

async function createCourse() {
    try {
        await api.post('/courses', newCourseForm.value);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Course created', life: 3000 });
        createVisible.value = false;
        fetchCourses();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to create course', life: 3000 });
    }
}

async function bookCourse(courseId: number) {
  if (!authStore.isLoggedIn()) {
    toast.add({ severity: 'info', summary: 'Login Required', detail: 'Please login to book a course', life: 3000 });
    return;
  }
  try {
    await api.post(`/courses/${courseId}/book`);
    toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Booking confirmed!', life: 3000 });
    courseVisible.value = false;
    fetchCourses();
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Booking failed', life: 3000 });
  }
}

async function unbookCourse(courseId: number) {
  try {
    await api.delete(`/courses/${courseId}/booking`);
    toast.add({ severity: 'warn', summary: 'Cancelled', detail: 'Booking cancelled', life: 3000 });
    courseVisible.value = false;
    fetchCourses();
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Failed to cancel booking', life: 3000 });
  }
}

function isBooked(course: any) {
  if (!authStore.user || !course) return false;
  return course.bookings.some((b: any) => b.member?.email === authStore.user?.email);
}

function isToday(date: Date) {
    const now = new Date();
    return date.getDate() === now.getDate() &&
           date.getMonth() === now.getMonth() &&
           date.getFullYear() === now.getFullYear();
}

onMounted(fetchCourses);
</script>

<template>
  <main class="home-view">
    <div class="calendar-hero bg-dark text-white p-6 mb-4">
        <h1 class="text-white mb-2">Athletic Schedule</h1>
        <div class="flex justify-content-center gap-2 mt-4">
            <Button :label="'Week View'" 
                    :class="viewMode === 'week' ? 'p-button-primary' : 'p-button-outlined p-button-secondary'" 
                    @click="viewMode = 'week'" />
            <Button :label="'Month View'" 
                    :class="viewMode === 'month' ? 'p-button-primary' : 'p-button-outlined p-button-secondary'" 
                    @click="viewMode = 'month'" />
        </div>
    </div>

    <div class="container">
        <!-- Week View -->
        <div v-if="viewMode === 'week'" class="schedule-container shadow-2">
            <div class="schedule-header">
                <div class="day-label time-corner">TIME</div>
                <div v-for="(dayDate, idx) in currentWeek" :key="idx" class="day-label">
                    {{ days[idx] }}
                    <div class="text-xs opacity-60">{{ dayDate.toLocaleDateString([], {day: 'numeric', month: 'short'}) }}</div>
                </div>
            </div>
            
            <div class="schedule-body">
                <div class="time-axis">
                    <div v-for="hour in hours" :key="hour" class="time-slot">
                        {{ hour }}:00
                    </div>
                </div>
                
                <div v-for="(dayDate, dayIdx) in currentWeek" 
                     :key="'col-'+dayIdx" 
                     class="day-column"
                     @click.self="handleCellClick(dayDate)">
                    <div v-for="course in getCoursesForDay(dayDate)" 
                         :key="course.id" 
                         class="course-entry"
                         :style="getCourseStyle(course)"
                         @click.stop="openCourse(course)">
                        <span class="course-time">
                            {{ new Date(course.startTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                        </span>
                        <span class="course-title">{{ course.title }}</span>
                        <div class="mt-1 text-xs opacity-80">
                            {{ course.bookings.length }}/{{ course.capacity }} slots
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Month View -->
        <div v-else class="month-container shadow-2">
            <div v-for="day in ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']" :key="day" class="bg-dark text-white text-center p-2 font-bold text-xs">
                {{ day }}
            </div>
            <div v-for="(dayDate, idx) in monthDays" 
                 :key="idx" 
                 :class="['month-day', { today: isToday(dayDate), 'other-month': dayDate.getMonth() !== new Date().getMonth() }]"
                 @click="handleCellClick(dayDate)">
                <div class="day-number">{{ dayDate.getDate() }}</div>
                <div v-for="course in getCoursesForDay(dayDate)" 
                     :key="course.id" 
                     class="month-course"
                     @click.stop="openCourse(course)">
                    {{ new Date(course.startTime).getHours() }}:{{ new Date(course.startTime).getMinutes().toString().padStart(2, '0') }} {{ course.title }}
                </div>
            </div>
        </div>
    </div>

    <!-- Course Info Dialog -->
    <Dialog v-model:visible="courseVisible" :header="selectedCourse?.title" :modal="true" class="w-full max-w-md">
        <div v-if="selectedCourse" class="course-details">
            <div class="flex align-items-center gap-2 mb-4">
                <i class="pi pi-user text-primary"></i>
                <span class="font-bold text-xl">{{ selectedCourse.trainer.name }}</span>
            </div>
            
            <p class="mb-6 text-lg line-height-3">{{ selectedCourse.description }}</p>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="info-box">
                    <i class="pi pi-calendar mb-2 text-primary"></i>
                    <span class="block font-bold">DATE</span>
                    <span class="text-lg">{{ new Date(selectedCourse.startTime).toLocaleDateString() }}</span>
                </div>
                <div class="info-box">
                    <i class="pi pi-clock mb-2 text-primary"></i>
                    <span class="block font-bold">TIME</span>
                    <span class="text-lg">{{ new Date(selectedCourse.startTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span>
                </div>
                <div class="info-box">
                    <i class="pi pi-hourglass mb-2 text-primary"></i>
                    <span class="block font-bold">DURATION</span>
                    <span class="text-lg">{{ selectedCourse.durationMinutes }} MIN</span>
                </div>
                <div class="info-box">
                    <i class="pi pi-users mb-2 text-primary"></i>
                    <span class="block font-bold">AVAILABILITY</span>
                    <span class="text-lg">{{ selectedCourse.capacity - selectedCourse.bookings.length }} LEFT</span>
                </div>
            </div>

            <div v-if="!authStore.isTrainer()">
                <Button v-if="!isBooked(selectedCourse)" 
                        label="CONFIRM BOOKING" 
                        icon="pi pi-check"
                        class="w-full p-4 text-xl"
                        @click="bookCourse(selectedCourse.id)" 
                        :disabled="selectedCourse.bookings.length >= selectedCourse.capacity" />
                
                <Button v-else 
                        label="CANCEL MY BOOKING" 
                        icon="pi pi-times"
                        severity="danger" 
                        variant="outlined"
                        class="w-full p-4 text-xl"
                        @click="unbookCourse(selectedCourse.id)" />
            </div>
        </div>
    </Dialog>

    <!-- Create Course Dialog -->
    <Dialog v-model:visible="createVisible" header="New Course" :modal="true" class="p-fluid w-full max-w-lg">
        <div class="field mt-4">
            <label for="title">Course Title</label>
            <InputText id="title" v-model="newCourseForm.title" required autofocus />
        </div>
        <div class="field mt-4">
            <label for="description">Description</label>
            <Textarea id="description" v-model="newCourseForm.description" rows="3" />
        </div>
        <div class="formgrid grid mt-4">
            <div class="field col">
                <label for="capacity">Capacity</label>
                <InputNumber id="capacity" v-model="newCourseForm.capacity" showButtons :min="1" />
            </div>
            <div class="field col">
                <label for="duration">Duration (Min)</label>
                <InputNumber id="duration" v-model="newCourseForm.durationMinutes" showButtons :min="15" :step="15" />
            </div>
        </div>
        <div class="field mt-4">
            <label for="startTime">Start Time</label>
            <input id="startTime" type="datetime-local" v-model="newCourseForm.startTime" class="p-inputtext p-component w-full" />
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="createVisible = false" />
            <Button label="Create" icon="pi pi-check" @click="createCourse" />
        </template>
    </Dialog>
  </main>
</template>

<style scoped lang="scss">
.calendar-hero {
    text-align: center;
    border-bottom: 5px solid var(--accent-color);
    h1 { color: white; font-size: 3rem; }
}

.info-box {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 4px;
    text-align: center;
    border: 1px solid #e2e8f0;
    
    i { font-size: 1.5rem; }
    span.block { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }
    span.text-lg { color: var(--primary-color); font-weight: 700; }
}

.course-details {
    padding: 1rem 0;
    // Fix contrast issues
    color: var(--text-color) !important;
}

@media (max-width: 768px) {
    .grid-cols-2 {
        grid-template-columns: 1fr;
    }
}
</style>
