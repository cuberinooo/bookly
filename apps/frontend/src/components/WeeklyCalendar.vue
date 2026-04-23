<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    courses: any[];
}>();

const emit = defineEmits(['course-click', 'cell-click']);

const baseDate = ref(new Date());

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const hours = Array.from({ length: 24 }, (_, i) => i);

const currentWeek = computed(() => {
    const start = new Date(baseDate.value);
    const day = start.getDay();
    const diff = (day === 0 ? 6 : day - 1); // Adjust for Monday-start
    start.setDate(start.getDate() - diff);
    start.setHours(0, 0, 0, 0);

    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        return d;
    });
});

const currentWeekLabel = computed(() => {
    const start = currentWeek.value[0];
    const end = currentWeek.value[6];
    return `${start.toLocaleDateString([], { day: 'numeric', month: 'short' })} - ${end.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' })}`;
});

function navigate(direction: number) {
    const newDate = new Date(baseDate.value);
    newDate.setDate(newDate.getDate() + (direction * 7));
    baseDate.value = newDate;
}

function resetToToday() {
    baseDate.value = new Date();
}

function getCoursesForDay(day: Date) {
    return props.courses.filter(c => {
        const cDate = new Date(c.startTime);
        return cDate.toDateString() === day.toDateString();
    });
}

function getCourseStyle(course: any) {
    const date = new Date(course.startTime);
    const startHour = date.getHours() + date.getMinutes() / 60;
    const duration = course.durationMinutes || 60;
    const slotHeight = 60; // 1 hour = 60px
    return {
        top: `${startHour * slotHeight}px`,
        height: `${(duration / 60) * slotHeight}px`
    };
}

function isToday(date: Date) {
    const today = new Date();
    return date.toDateString() === today.toDateString();
}

function onCellClick(day: Date, event: MouseEvent) {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const offsetY = event.clientY - rect.top;
    const hour = Math.floor(offsetY / 60);

    const clickDate = new Date(day);
    clickDate.setHours(hour, 0, 0, 0);
    emit('cell-click', clickDate);
}
</script>

<template>
    <div class="calendar-wrapper border-1 border-round border-300 bg-card shadow-1">
        <div class="calendar-nav flex justify-content-between align-items-center p-3 border-bottom-1 border-300">
            <div class="flex align-items-center gap-3">
                <h2 class="m-0 text-xl font-bold">{{ currentWeekLabel }}</h2>
                <div class="p-buttonset">
                    <Button icon="pi pi-chevron-left" @click="navigate(-1)" severity="secondary" variant="outlined" size="small" />
                    <Button label="Today" @click="resetToToday()" severity="secondary" variant="outlined" size="small" />
                    <Button icon="pi pi-chevron-right" @click="navigate(1)" severity="secondary" variant="outlined" size="small" />
                </div>
            </div>
        </div>

        <div class="calendar-scroll-container">
            <div class="calendar-grid">
                <!-- Header -->
                <div class="grid-header">
                    <div class="time-column-header"></div>
                    <div v-for="(date, idx) in currentWeek" :key="idx"
                         :class="['day-header', { 'is-today': isToday(date) }]">
                        <span class="day-name font-bold">{{ days[idx] }}</span>
                        <span class="day-number opacity-70">{{ date.toLocaleDateString([], { day: 'numeric', month: 'short' }) }}</span>
                    </div>
                </div>

                <!-- Body -->
                <div class="grid-body">
                    <div class="time-column">
                        <div v-for="hour in hours" :key="hour" class="time-slot">
                            {{ hour.toString().padStart(2, '0') }}:00
                        </div>
                    </div>

                    <div v-for="(date, dayIdx) in currentWeek" :key="dayIdx"
                         class="day-column" @click="onCellClick(date, $event)">
                        <!-- Hour indicators -->
                        <div v-for="hour in hours" :key="hour" class="hour-indicator"></div>

                        <!-- Courses -->
                        <div v-for="course in getCoursesForDay(date)" :key="course.id"
                             class="course-card shadow-2" :style="getCourseStyle(course)"
                             @click.stop="$emit('course-click', course)">
                            <div class="course-time font-bold">
                                {{ new Date(course.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                            </div>
                            <div class="course-title">{{ course.title }}</div>
                            <div class="course-footer mt-auto text-xs opacity-80 uppercase font-bold">
                                {{ course.capacity - course.bookings.length }} slots left
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.flex { display: flex; }
.justify-content-between { justify-content: space-between; }
.align-items-center { align-items: center; }
.gap-3 { gap: 1rem; }
.p-3 { padding: 1rem; }
.border-1 { border: 1px solid var(--border-color); }
.border-round { border-radius: 8px; }
.border-300 { border-color: #cbd5e1; }
.border-bottom-1 { border-bottom-width: 1px; }
.shadow-1 { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }

.calendar-wrapper {
    display: flex;
    flex-direction: column;
    height: 800px;
    background: var(--surface-card);
    overflow: hidden;
}

.calendar-scroll-container {
    flex: 1;
    overflow-y: auto;
}

.calendar-grid {
    display: flex;
    flex-direction: column;
    min-width: 800px;
}

.grid-header {
    display: grid;
    grid-template-columns: 60px repeat(7, 1fr);
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--surface-card);
    border-bottom: 1px solid var(--border-color);
}

.day-header {
    padding: 1rem 0.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    border-left: 1px solid var(--border-color);

    &.is-today {
        background: var(--primary-50);
        .day-name { color: var(--primary-color); }
    }
}

.grid-body {
    display: grid;
    grid-template-columns: 60px repeat(7, 1fr);
    position: relative;
}

.time-column {
    background: var(--surface-ground);
    border-right: 1px solid var(--border-color);
}

.time-slot {
    height: 60px;
    padding: 0.5rem;
    font-size: 0.75rem;
    text-align: right;
    color: var(--text-muted);
}

.day-column {
    position: relative;
    border-left: 1px solid var(--border-color);
    height: 1440px; // 24 hours * 60px
    cursor: cell;
}

.hour-indicator {
    height: 60px;
    border-bottom: 1px solid var(--border-color);
    pointer-events: none;
}

.course-card {
    position: absolute;
    left: 4px;
    right: 4px;
    background: var(--primary-color);
    color: white;
    border-radius: 4px;
    padding: 0.5rem;
    overflow: hidden;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    transition: transform 0.1s, box-shadow 0.1s;
    border-left: 4px solid var(--primary-700);
    z-index: 1;

    &:hover {
        transform: scale(1.02);
        z-index: 2;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
}

.course-time {
    font-size: 0.75rem;
}

.course-title {
    font-weight: 700;
    font-size: 0.9rem;
    line-height: 1.2;
}

/* Custom scrollbar */
.calendar-scroll-container::-webkit-scrollbar {
    width: 8px;
}
.calendar-scroll-container::-webkit-scrollbar-track {
    background: var(--surface-ground);
}
.calendar-scroll-container::-webkit-scrollbar-thumb {
    background: var(--primary-300);
    border-radius: 4px;
}
</style>
