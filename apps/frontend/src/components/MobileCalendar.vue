<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    courses: any[];
    userId?: number;
}>();

const emit = defineEmits(['course-click']);

const baseDate = ref(new Date());

const currentWeek = computed(() => {
    const start = new Date(baseDate.value);
    const day = start.getDay();
    const diff = (day === 0 ? 6 : day - 1);
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

function isToday(date: Date) {
    const today = new Date();
    return date.toDateString() === today.toDateString();
}

function isBookedByUser(course: any) {
    if (!props.userId) return false;
    return course.bookings?.some((b: any) => b.member?.id === props.userId);
}

function formatDayName(date: Date) {
    return date.toLocaleDateString([], { weekday: 'long' });
}
</script>

<template>
    <div class="mobile-calendar animate-fadein">
        <div class="mobile-nav">
            <div class="nav-header">
                <h2>{{ currentWeekLabel }}</h2>
            </div>
            <div class="nav-actions">
                <Button icon="pi pi-chevron-left" @click="navigate(-1)" variant="text" rounded />
                <Button label="TODAY" @click="resetToToday()" variant="outlined" size="small" class="today-btn" />
                <Button icon="pi pi-chevron-right" @click="navigate(1)" variant="text" rounded />
            </div>
        </div>

        <div class="mobile-days-list">
            <div v-for="date in currentWeek" :key="date.toISOString()" class="day-group" :class="{ 'is-today': isToday(date) }">
                <div class="day-header-sticky">
                    <span class="day-name">{{ formatDayName(date) }}</span>
                    <span class="day-date">{{ date.toLocaleDateString([], { day: 'numeric', month: 'short' }) }}</span>
                </div>

                <div class="courses-stack">
                    <div v-if="getCoursesForDay(date).length === 0" class="empty-day">
                        No sessions scheduled
                    </div>
                    <div v-for="course in getCoursesForDay(date)" :key="course.id" 
                         class="mobile-course-card"
                         :class="{ 'is-booked': isBookedByUser(course) }"
                         @click="$emit('course-click', course)">
                        
                        <div class="card-left">
                            <div class="course-time">
                                {{ new Date(course.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                            </div>
                            <div class="course-duration">{{ course.durationMinutes }} MIN</div>
                        </div>

                        <div class="card-main">
                            <div class="course-title">{{ course.title }}</div>
                            <div class="course-coach">Coach: {{ course.trainer?.name }}</div>
                        </div>

                        <div class="card-right">
                            <div v-if="isBookedByUser(course)" class="booked-indicator">
                                <i class="pi pi-check-circle"></i>
                            </div>
                            <div v-else class="spots-pill" :class="{ 'is-full': course.bookings.filter(b => !b.isWaitlist).length >= course.capacity }">
                                {{ course.capacity - course.bookings.filter(b => !b.isWaitlist).length }} 
                                <i class="pi pi-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.mobile-calendar {
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}

.mobile-nav {
    background: #0f172a;
    color: white;
    padding: 1.5rem 1rem;
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);

    .nav-header h2 {
        margin: 0;
        color: #ffc107;
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 1.25rem;
        text-align: center;
        letter-spacing: 0.05em;
    }

    .nav-actions {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 2rem;

        :deep(.p-button.p-button-text) {
            color: white !important;
            &:active { background: rgba(255, 193, 7, 0.2) !important; }
        }
    }
}

.today-btn {
    border-color: rgba(255, 193, 7, 0.5) !important;
    color: #ffc107 !important;
    font-weight: 800 !important;
    padding: 0.5rem 1.5rem !important;
}

.mobile-days-list {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.day-header-sticky {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;

    .day-name {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 900;
        text-transform: uppercase;
        font-size: 1rem;
        color: #64748b;
        letter-spacing: 0.05em;
    }

    .day-date {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.9rem;
    }
}

.is-today .day-header-sticky {
    border-bottom: 2px solid #ffc107;
    .day-name { color: #ffc107; }
}

.courses-stack {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.empty-day {
    padding: 1rem;
    text-align: center;
    color: #94a3b8;
    font-size: 0.85rem;
    font-style: italic;
}

.mobile-course-card {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    cursor: pointer;
    transition: transform 0.1s;

    &:active {
        transform: scale(0.98);
        background: #f8fafc;
    }

    &.is-booked {
        border-left: 6px solid #ffc107;
        background: #fffbeb;
    }

    .card-left {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 60px;
        padding-right: 1rem;
        border-right: 1px solid #f1f5f9;

        .course-time {
            font-weight: 900;
            font-size: 1.1rem;
            color: #0f172a;
            font-family: 'Barlow Condensed', sans-serif;
        }
        .course-duration {
            font-size: 0.65rem;
            font-weight: 800;
            color: #64748b;
        }
    }

    .card-main {
        flex: 1;
        .course-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 1rem;
            color: #0f172a;
            line-height: 1.1;
        }
        .course-coach {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
    }

    .card-right {
        .booked-indicator {
            color: #ffc107;
            font-size: 1.5rem;
        }

        .spots-pill {
            background: #f1f5f9;
            color: #475569;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.3rem;

            &.is-full {
                background: #fee2e2;
                color: #ef4444;
            }
        }
    }
}
</style>
