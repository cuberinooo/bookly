<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';

const courses = ref<any[]>([]);
const error = ref('');

async function fetchCourses() {
  try {
    const response = await api.get('/courses');
    courses.value = response.data;
  } catch (err) {
    console.error('Failed to fetch courses', err);
  }
}

async function bookCourse(courseId: number) {
  if (!authStore.isLoggedIn()) {
    alert('Please login to book a course');
    return;
  }
  try {
    await api.post(`/courses/${courseId}/book`);
    alert('Booking confirmed!');
    fetchCourses();
  } catch (err: any) {
    alert(err.response?.data?.error || 'Booking failed');
  }
}

async function unbookCourse(courseId: number) {
  try {
    await api.delete(`/courses/${courseId}/booking`);
    alert('Booking cancelled');
    fetchCourses();
  } catch (err: any) {
    alert(err.response?.data?.error || 'Failed to cancel booking');
  }
}

function isBooked(course: any) {
  if (!authStore.user) return false;
  return course.bookings.some((b: any) => b.member?.email === authStore.user?.email);
}

onMounted(fetchCourses);
</script>

<template>
  <main>
    <h1>Available Courses</h1>
    <div class="courses-grid">
      <div v-for="course in courses" :key="course.id" class="course-card">
        <h3>{{ course.title }}</h3>
        <p>{{ course.description }}</p>
        <p><strong>Trainer:</strong> {{ course.trainer.name }}</p>
        <p><strong>Capacity:</strong> {{ course.bookings.length }} / {{ course.capacity }}</p>
        <p><strong>Time:</strong> {{ new Date(course.startTime).toLocaleString() }}</p>
        
        <div v-if="!authStore.isTrainer()">
            <button v-if="!isBooked(course)" @click="bookCourse(course.id)" :disabled="course.bookings.length >= course.capacity">
              {{ course.bookings.length >= course.capacity ? 'Full' : 'Book Now' }}
            </button>
            <button v-else @click="unbookCourse(course.id)" class="btn-cancel">Cancel Booking</button>
        </div>
        <p v-else-if="course.trainer.email === authStore.user?.email" class="owner-badge">You are the trainer</p>
      </div>
    </div>
  </main>
</template>

<style scoped lang="scss">
.courses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1.5rem;
  padding: 1rem;
}
.course-card {
  border: 1px solid #ddd;
  padding: 1rem;
  border-radius: 8px;
  background: #f9f9f9;
  
  h3 {
    margin-top: 0;
  }
}
button {
  background-color: #42b983;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  
  &:disabled {
    background-color: #ccc;
    cursor: not-allowed;
  }
}
.btn-cancel {
    background-color: #ff4d4d;
}
.owner-badge {
    color: #666;
    font-style: italic;
}
</style>
