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
    <div class="athletic-calendar-container shadow-md">
        <div class="calendar-toolbar">
            <div class="date-range">
                <h2>{{ currentWeekLabel }}</h2>
            </div>
            <div class="nav-controls">
                <Button icon="pi pi-chevron-left" @click="navigate(-1)" variant="text" />
                <Button label="TODAY" @click="resetToToday()" variant="outlined" size="small" class="today-btn" />
                <Button icon="pi pi-chevron-right" @click="navigate(1)" variant="text" />
            </div>
        </div>

        <div class="calendar-view">
            <div class="grid-header">
                <div class="time-corner"></div>
                <div v-for="(date, idx) in currentWeek" :key="idx"
                     :class="['day-column-header', { 'is-today': isToday(date) }]">
                    <span class="day-abbr">{{ days[idx].substring(0, 3) }}</span>
                    <span class="day-num">{{ date.getDate() }}</span>
                </div>
            </div>

            <div class="scroll-area">
                <div class="grid-body">
                    <div class="time-axis">
                        <div v-for="hour in hours" :key="hour" class="time-label">
                            {{ hour.toString().padStart(2, '0') }}:00
                        </div>
                    </div>

                    <div v-for="(date, dayIdx) in currentWeek" :key="dayIdx"
                         class="day-track" @click="onCellClick(date, $event)">
                        <!-- Grid lines -->
                        <div v-for="hour in hours" :key="hour" class="hour-slot"></div>

                        <!-- Courses -->
                        <div v-for="course in getCoursesForDay(date)" :key="course.id"
                             class="course-block" :style="getCourseStyle(course)"
                             @click.stop="$emit('course-click', course)">
                            <div class="course-meta">
                                {{ new Date(course.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                            </div>
                            <div class="course-title">{{ course.title }}</div>
                            <div class="course-spots">
                                {{ course.capacity - course.bookings.length }} LEFT
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.athletic-calendar-container {
    background: white;
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 900px;
}

.calendar-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: #0f172a;
    color: white;
    
    h2 { 
        margin: 0; 
        font-family: 'Barlow Condensed', sans-serif; 
        font-weight: 800; 
        color: var(--primary-color);
        letter-spacing: 0.05em;
    }
}

.nav-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    
    :deep(.p-button.p-button-text) {
        color: white !important;
        &:hover { color: var(--primary-color) !important; background: rgba(255,255,255,0.1) !important; }
    }
}

.today-btn {
    border-color: rgba(255,255,255,0.3) !important;
    color: white !important;
    font-weight: 800 !important;
    font-family: 'Barlow Condensed', sans-serif !important;
    &:hover { border-color: var(--primary-color) !important; color: var(--primary-color) !important; }
}

.calendar-view {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

.grid-header {
    display: grid;
    grid-template-columns: 70px repeat(7, 1fr);
    background: #1e293b;
    border-bottom: 2px solid #334155;
    
    .time-corner { border-right: 1px solid #334155; }
}

.day-column-header {
    padding: 1rem 0;
    text-align: center;
    display: flex;
    flex-direction: column;
    border-left: 1px solid #334155;
    color: #94a3b8;
    
    .day-abbr { font-family: 'Barlow Condensed', sans-serif; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.1em; }
    .day-num { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.4rem; color: white; }
    
    &.is-today {
        background: rgba(255, 193, 7, 0.1);
        .day-num { color: var(--primary-color); }
        .day-abbr { color: var(--primary-color); }
    }
}

.scroll-area {
    flex: 1;
    overflow-y: auto;
    overflow-x: auto;
    background: #f8fafc;
}

.grid-body {
    display: grid;
    grid-template-columns: 70px repeat(7, 1fr);
    min-width: 900px;
    position: relative;
}

.time-axis {
    background: #f1f5f9;
    border-right: 1px solid var(--border-color);
}

.time-label {
    height: 60px;
    padding: 0.5rem;
    font-size: 0.7rem;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    text-align: right;
    color: #94a3b8;
}

.day-track {
    position: relative;
    border-left: 1px solid #e2e8f0;
    height: 1440px; // 24h * 60px
    cursor: cell;
    
    &:hover { background: rgba(255, 193, 7, 0.02); }
}

.hour-slot {
    height: 60px;
    border-bottom: 1px solid #f1f5f9;
}

.course-block {
    position: absolute;
    left: 4px;
    right: 4px;
    background: var(--primary-color);
    color: #000;
    border-radius: 6px;
    padding: 8px;
    overflow: hidden;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 2px;
    border-left: 4px solid #b45309;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    z-index: 1;
    transition: all 0.2s;

    &:hover {
        transform: scale(1.02);
        z-index: 10;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
        background: #fbbf24;
    }
}

.course-meta {
    font-size: 0.65rem;
    font-weight: 800;
    font-family: 'Barlow Condensed', sans-serif;
    opacity: 0.7;
}

.course-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800;
    font-size: 0.95rem;
    line-height: 1.1;
    text-transform: uppercase;
}

.course-spots {
    margin-top: auto;
    font-size: 0.6rem;
    font-weight: 900;
    letter-spacing: 0.05em;
}

/* Custom scrollbar */
.scroll-area::-webkit-scrollbar { width: 10px; }
.scroll-area::-webkit-scrollbar-track { background: #f1f5f9; }
.scroll-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 5px; border: 2px solid #f1f5f9; }
.scroll-area::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
