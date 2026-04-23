<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';

const courses = ref<any[]>([]);
const notifications = ref<any[]>([]);
const isTrainer = authStore.isTrainer();

// Trainer course form
const courseForm = ref({
    title: '',
    description: '',
    capacity: 10,
    startTime: '',
    endTime: ''
});

async function fetchMyCourses() {
    const response = await api.get('/courses');
    if (isTrainer) {
        courses.value = response.data.filter((c: any) => c.trainer.email === authStore.user?.email);
    } else {
        courses.value = response.data.filter((c: any) => c.bookings.some((b: any) => b.member.email === authStore.user?.email));
    }
}

async function fetchNotifications() {
    if (!isTrainer) return;
    try {
        const response = await api.get('/notifications');
        notifications.value = response.data;
    } catch (e) {}
}

async function createCourse() {
    try {
        await api.post('/courses', courseForm.value);
        alert('Course created');
        fetchMyCourses();
    } catch (e) {
        alert('Failed to create course');
    }
}

async function markAsRead(id: number) {
    try {
        await api.patch(`/notifications/${id}/read`);
        fetchNotifications();
    } catch (e) {}
}

onMounted(() => {
    fetchMyCourses();
    fetchNotifications();
});
</script>

<template>
  <div class="dashboard">
    <h1>Dashboard</h1>
    
    <div v-if="isTrainer">
        <section class="trainer-section">
            <h2>My Courses</h2>
            <ul>
                <li v-for="course in courses" :key="course.id">
                    <strong>{{ course.title }}</strong> - {{ course.bookings.length }} participants
                </li>
            </ul>
            
            <h3>Add New Course</h3>
            <form @submit.prevent="createCourse" class="course-form">
                <input v-model="courseForm.title" placeholder="Title" required />
                <textarea v-model="courseForm.description" placeholder="Description"></textarea>
                <input v-model.number="courseForm.capacity" type="number" placeholder="Capacity" required />
                <input v-model="courseForm.startTime" type="datetime-local" required />
                <input v-model="courseForm.endTime" type="datetime-local" required />
                <button type="submit">Create</button>
            </form>
        </section>

        <section class="notifications">
            <h2>Notifications</h2>
            <ul>
                <li v-for="notif in notifications" :key="notif.id" :class="{ unread: !notif.isRead }">
                    <p>{{ notif.message }} <small>({{ new Date(notif.createdAt).toLocaleString() }})</small></p>
                    <button v-if="!notif.isRead" @click="markAsRead(notif.id)">Mark as Read</button>
                </li>
            </ul>
        </section>
    </div>
    
    <div v-else>
        <h2>My Bookings</h2>
        <div v-if="courses.length === 0">No bookings yet. Go to <RouterLink to="/">Home</RouterLink> to book a course.</div>
        <div class="courses-grid">
            <div v-for="course in courses" :key="course.id" class="course-card">
                <h3>{{ course.title }}</h3>
                <p><strong>Trainer:</strong> {{ course.trainer.name }}</p>
                <p><strong>Time:</strong> {{ new Date(course.startTime).toLocaleString() }}</p>
            </div>
        </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.dashboard {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
}
.course-form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 2rem;
    input, textarea {
        padding: 0.5rem;
    }
}
.unread {
    background: #e6f7ff;
    padding: 0.5rem;
    border-radius: 4px;
}
.courses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}
.course-card {
  border: 1px solid #ddd;
  padding: 1rem;
  border-radius: 8px;
}
</style>
