<script setup lang="ts">
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    courses: any[];
    isCompactView?: boolean;
    userId?: number;
}>(), {
    isCompactView: true
});

const emit = defineEmits(['course-click', 'cell-click']);

const baseDate = ref(new Date());

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const hours = Array.from({ length: 16 }, (_, i) => i + 6); // 6:00 to 22:00

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
    return props.courses
        .filter(c => {
            const cDate = new Date(c.startTime);
            return cDate.toDateString() === day.toDateString();
        })
        .sort((a, b) => new Date(a.startTime).getTime() - new Date(b.startTime).getTime());
}

function getGridRow(startTime: string, durationMinutes: number) {
    const date = new Date(startTime);
    const hour = date.getHours();
    const minutes = date.getMinutes();

    // Start row (1-based, starting from 6:00)
    // Each hour is 2 rows (30 min each) to allow some granularity
    const startRow = (hour - 6) * 2 + (minutes >= 30 ? 2 : 1);
    const rowSpan = Math.ceil(durationMinutes / 30);

    return `${startRow} / span ${rowSpan}`;
}

function isToday(date: Date) {
    const today = new Date();
    return date.toDateString() === today.toDateString();
}

function isBookedByUser(course: any) {
    if (!props.userId) return false;
    return course.bookings?.some((b: any) => b.member?.id === props.userId);
}

function onSlotClick(day: Date, hour: number) {
    const clickDate = new Date(day);
    clickDate.setHours(hour, 0, 0, 0);
    emit('cell-click', clickDate);
}
</script>

<template>
    <div class="athletic-calendar" :class="{ 'compact-mode': isCompactView }">
        <div class="calendar-toolbar">
            <div class="date-info">
                <h2>{{ currentWeekLabel }}</h2>
            </div>
            <div class="view-controls">
                <div class="nav-group">
                    <Button icon="pi pi-chevron-left" @click="navigate(-1)" variant="text" />
                    <Button label="TODAY" @click="resetToToday()" variant="outlined" size="small" class="today-btn" />
                    <Button icon="pi pi-chevron-right" @click="navigate(1)" variant="text" />
                </div>
            </div>
        </div>

        <div class="calendar-container">
            <!-- Header Grid -->
            <div class="calendar-header-grid">
                <div class="time-corner" v-if="!isCompactView"></div>
                <div class="days-header-wrapper" :class="{ 'grid-cols-7': true }">
                    <div v-for="(date, idx) in currentWeek" :key="idx"
                         :class="['day-header', { 'is-today': isToday(date) }]">
                        <span class="day-name">{{ days[idx] }}</span>
                        <span class="day-date">{{ date.getDate() }}</span>
                    </div>
                </div>
            </div>

            <!-- Body Scroll Area -->
            <div class="calendar-body-scroll">
                <div class="calendar-body-grid">
                    <!-- Time Axis -->
                    <div class="time-axis" v-if="!isCompactView">
                        <div v-for="hour in hours" :key="hour" class="time-slot-label">
                            {{ hour.toString().padStart(2, '0') }}:00
                        </div>
                    </div>

                    <!-- Days Grid -->
                    <div class="days-body-wrapper" :class="{ 'grid-cols-7': true }">
                        <div v-for="(date, dayIdx) in currentWeek" :key="dayIdx" class="day-column">

                            <!-- Standard View: Time Grid -->
                            <template v-if="!isCompactView">
                                <div v-for="hour in hours" :key="hour" class="hour-slot-row" @click="onSlotClick(date, hour)">
                                    <div class="half-hour-slot"></div>
                                    <div class="half-hour-slot"></div>
                                </div>
                            </template>

                            <!-- Courses (Both views use grid positioning if not compact, or flex if compact) -->
                            <div v-for="course in getCoursesForDay(date)" :key="course.id"
                                 class="course-card"
                                 :class="{ 'is-booked': isBookedByUser(course) }"
                                 :style="!isCompactView ? { gridRow: getGridRow(course.startTime, course.durationMinutes) } : {}"
                                 @click.stop="$emit('course-click', course)">
                                <div v-if="isBookedByUser(course)" class="booked-badge">
                                    <i class="pi pi-check"></i> BOOKED
                                </div>
                                <div class="course-time">
                                    {{ new Date(course.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    <span v-if="!isCompactView" class="duration-tag">/ {{ course.durationMinutes }} MIN</span>
                                </div>
                                <div class="course-title">{{ course.title }}</div>
                                
                                <div class="course-meta">
                                    <div class="coach-line" v-if="!isCompactView">
                                        <i class="pi pi-user text-[10px]"></i> {{ course.trainer?.name }}
                                    </div>
                                    <div class="course-spots">
                                        <template v-if="course.bookings.filter(b => !b.isWaitlist).length < course.capacity">
                                            {{ course.bookings.filter(b => !b.isWaitlist).length }} / {{ course.capacity }} <i class="pi pi-users text-[10px]"></i>
                                        </template>
                                        <template v-else>
                                            <span class="text-amber-500">FULL</span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
$brand-amber: #ffc107;
$brand-slate-dark: #0f172a;
$brand-slate-medium: #1e293b;
$border-color: #e2e8f0;

.athletic-calendar {
    background: white;
    border: 1px solid $border-color;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 800px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);

    &.compact-mode {
        height: auto;
        min-height: 400px;
    }
}

.calendar-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 2rem;
    background: $brand-slate-dark;
    color: white;

    h2 {
        margin: 0;
        color: $brand-amber;
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 1.5rem;
    }
}

.nav-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;

    :deep(.p-button.p-button-text) {
        color: white !important;
        &:hover { color: $brand-amber !important; background: rgba(255,255,255,0.1) !important; }
    }
}

.today-btn {
    border-color: rgba(255,255,255,0.2) !important;
    color: white !important;
    font-family: 'Barlow Condensed', sans-serif !important;
    font-weight: 700 !important;
    &:hover { border-color: $brand-amber !important; color: $brand-amber !important; }
}

.calendar-container {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

.calendar-header-grid {
    display: flex;
    background: $brand-slate-medium;
    border-bottom: 2px solid #334155;
    position: sticky;
    top: 0;
    z-index: 20;

    .time-corner {
        width: 80px;
        flex-shrink: 0;
        border-right: 1px solid #334155;
    }
}

.days-header-wrapper {
    flex: 1;
    display: grid;
    // grid-cols-7 is handled by the class
}

.grid-cols-7 {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.day-header {
    padding: 1rem 0.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    border-left: 1px solid #334155;

    .day-name {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
        color: #94a3b8;
    }

    .day-date {
        font-family: 'Barlow', sans-serif;
        font-weight: 800;
        font-size: 1.25rem;
        color: white;
    }

    &.is-today {
        background: rgba($brand-amber, 0.1);
        .day-name, .day-date { color: $brand-amber; }
    }
}

.calendar-body-scroll {
    flex: 1;
    overflow-y: auto;
    background: #f8fafc;
}

.calendar-body-grid {
    display: flex;
    position: relative;
    min-height: 100%;
}

.time-axis {
    width: 80px;
    flex-shrink: 0;
    background: #f1f5f9;
    border-right: 1px solid $border-color;
}

.time-slot-label {
    height: 80px; // Each hour is 80px
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 0.5rem;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: 0.75rem;
    color: #64748b;
}

.days-body-wrapper {
    flex: 1;
    // grid-cols-7 is handled by the class
}

.day-column {
    display: grid;
    grid-template-rows: repeat(32, 40px); // 16 hours * 2 slots = 32 slots of 40px each
    border-left: 1px solid #e2e8f0;
    position: relative;

    &:first-child { border-left: none; }
}

.compact-mode .day-column {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem;
    grid-template-rows: none;
    min-height: 100px;
}

.hour-slot-row {
    grid-column: 1;
    display: contents;
    cursor: cell;

    .half-hour-slot {
        height: 40px;
        border-bottom: 1px solid #f1f5f9;

        &:last-child { border-bottom: 1px solid #e2e8f0; }

        &:hover { background: rgba($brand-amber, 0.05); }
    }
}

.course-card {
    grid-column: 1;
    margin: 2px;
    background: white;
    border: 1px solid $border-color;
    border-left: 4px solid $brand-amber;
    border-radius: 6px;
    padding: 0.5rem;
    z-index: 10;
    cursor: pointer;
    transition: all 0.2s;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);

    &:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: $brand-amber;
    }

    &.is-booked {
        background: #fffbeb;
        border-color: $brand-amber;
        border-left-width: 8px;
        box-shadow: 0 4px 6px -1px rgba($brand-amber, 0.2);

        .course-title {
            color: #92400e;
        }
    }

    .booked-badge {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 0.55rem;
        color: $brand-amber;
        background: #0f172a;
        padding: 1px 4px;
        border-radius: 3px;
        display: flex;
        align-items: center;
        gap: 2px;
        z-index: 2;

        i { font-size: 0.5rem; }
    }

    .course-time {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 0.7rem;
        color: #64748b;
        display: flex;
        gap: 0.25rem;

        .duration-tag {
            color: #94a3b8;
        }
    }

    .course-title {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.9rem;
        line-height: 1.1;
        color: $brand-slate-dark;
        margin: 2px 0;
    }

    .course-meta {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .coach-line {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 700;
        font-size: 0.65rem;
        color: #64748b;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .course-spots {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 0.65rem;
        color: $brand-amber;
        display: flex;
        align-items: center;
        gap: 4px;
    }
}

.compact-mode .course-card {
    grid-row: auto !important;
    margin: 0;
}

/* Responsive Styles */
@media (max-width: 1024px) {
    .calendar-body-scroll {
        overflow-x: auto;
    }
    .calendar-body-grid, .calendar-header-grid {
        min-width: 800px;
    }
}

@media (max-width: 768px) {
    .athletic-calendar { height: auto; }

    .calendar-header-grid, .calendar-body-grid {
        display: block; // Stack days vertically or use a better mobile view
        min-width: 0;
    }

    .days-header-wrapper, .days-body-wrapper {
        display: block;
    }

    .day-header {
        border-left: none;
        border-bottom: 1px solid #334155;
        padding: 0.75rem;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .day-column {
        border-left: none;
        border-bottom: 2px solid $border-color;
        padding: 0.5rem;
        display: flex;
        flex-direction: column;
        grid-template-rows: none;
        height: auto;
    }

    .time-corner, .time-axis, .hour-slot-row {
        display: none !important;
    }

    .course-card {
        grid-row: auto !important;
        margin-bottom: 0.5rem;
    }
}
</style>
