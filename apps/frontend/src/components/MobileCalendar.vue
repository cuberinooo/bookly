<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { formatDate, formatTime } from '../services/date-utils';
import { useAuthStore } from '../store/useAuthStore';

const props = defineProps<{
    courses: any[];
    userId?: number;
    baseDate?: Date;
    cycleInfo?: { name: string; currentWeek: number; totalWeeks: number; startDate: string } | null;
    loading?: boolean;
}>();

const emit = defineEmits(['course-click', 'update:baseDate']);

const authStore = useAuthStore();
const internalBaseDate = ref(new Date(props.baseDate || new Date()));
const transitionName = ref('slide-left');
const touchStartX = ref(0);
const touchStartY = ref(0);
const swipeThreshold = 50;

watch(() => props.baseDate, (newVal) => {
    if (newVal && newVal.getTime() !== internalBaseDate.value.getTime()) {
        transitionName.value = newVal.getTime() > internalBaseDate.value.getTime() ? 'slide-left' : 'slide-right';
        internalBaseDate.value = new Date(newVal);
    }
});

const displayedCycleWeek = computed(() => {
    if (!props.cycleInfo || !props.cycleInfo.startDate) return 0;
    
    const cycleStart = new Date(props.cycleInfo.startDate);
    cycleStart.setHours(0, 0, 0, 0);
    
    // Find Monday of the cycle start week
    const day = cycleStart.getDay();
    const diff = (day === 0 ? 6 : day - 1);
    cycleStart.setDate(cycleStart.getDate() - diff);

    const currentBase = new Date(internalBaseDate.value);
    currentBase.setHours(0, 0, 0, 0);
    
    // Find Monday of the current base week
    const currentDay = currentBase.getDay();
    const currentDiff = (currentDay === 0 ? 6 : currentDay - 1);
    currentBase.setDate(currentBase.getDate() - currentDiff);

    const diffDays = Math.round((currentBase.getTime() - cycleStart.getTime()) / (24 * 60 * 60 * 1000));
    const weeksElapsed = Math.floor(diffDays / 7);
    
    if (weeksElapsed < 0) return 1;
    
    return (weeksElapsed % props.cycleInfo.totalWeeks) + 1;
});

const currentWeek = computed(() => {
    const start = new Date(internalBaseDate.value);
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
    return `${start.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })} - ${end.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })}`;
});

function handleTouchStart(e: TouchEvent) {
    touchStartX.value = e.touches[0].clientX;
    touchStartY.value = e.touches[0].clientY;
}

function handleTouchEnd(e: TouchEvent) {
    const touchEndX = e.changedTouches[0].clientX;
    const touchEndY = e.changedTouches[0].clientY;

    const deltaX = touchEndX - touchStartX.value;
    const deltaY = touchEndY - touchStartY.value;

    // Ensure it's mostly a horizontal swipe and exceeds threshold
    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > swipeThreshold) {
        if (deltaX > 0) {
            // Swipe Right -> Previous Week
            transitionName.value = 'slide-right';
            navigate(-1);
        } else {
            // Swipe Left -> Next Week
            transitionName.value = 'slide-left';
            navigate(1);
        }
    }
}

function navigate(direction: number) {
    if (direction > 0) transitionName.value = 'slide-left';
    else transitionName.value = 'slide-right';

    const newDate = new Date(internalBaseDate.value);
    newDate.setDate(newDate.getDate() + (direction * 7));
    internalBaseDate.value = newDate;
    emit('update:baseDate', newDate);
}

function resetToToday() {
    const today = new Date();
    transitionName.value = today.getTime() > internalBaseDate.value.getTime() ? 'slide-left' : 'slide-right';
    internalBaseDate.value = today;
    emit('update:baseDate', today);
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
    return course.bookings?.some((b: any) => b.user?.id === props.userId);
}

function isRestrictedForTrial(course: any) {
    return authStore.isTrial && course.allowTrial === false;
}

function isPastCourse(course: any) {
    return new Date(course.endTime) < new Date();
}

function formatDayName(date: Date) {
    return date.toLocaleDateString('de-DE', { weekday: 'long' });
}
</script>

<template>
  <div
    class="mobile-calendar"
    @touchstart="handleTouchStart"
    @touchend="handleTouchEnd"
  >
    <div
      v-if="cycleInfo"
      class="mobile-cycle-info p-3 bg-slate-900 flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <i class="pi pi-sync text-amber-400 animate-spin-slow text-xs" />
        <span class="text-[10px] font-black text-white uppercase tracking-widest">{{ cycleInfo.name }}</span>
      </div>
      <span class="text-[10px] font-black text-amber-400 uppercase">WEEK {{ displayedCycleWeek }} / {{ cycleInfo.totalWeeks }}</span>
    </div>

    <div class="mobile-nav">
      <div class="nav-header">
        <h2>{{ currentWeekLabel }}</h2>
      </div>
      <div class="nav-actions">
        <Button
          icon="pi pi-chevron-left"
          variant="text"
          rounded
          @click="navigate(-1)"
        />
        <Button
          label="TODAY"
          variant="outlined"
          size="small"
          class="today-btn"
          @click="resetToToday()"
        />
        <Button
          icon="pi pi-chevron-right"
          variant="text"
          rounded
          @click="navigate(1)"
        />
      </div>
    </div>

    <div class="calendar-content-wrapper">
      <Transition
        :name="transitionName"
        mode="out-in"
      >
        <div
          :key="currentWeek[0].toISOString()"
          class="mobile-days-list"
        >
          <div
            v-for="date in currentWeek"
            :key="date.toISOString()"
            class="day-group"
            :class="{ 'is-today': isToday(date) }"
          >
            <div class="day-header-sticky">
              <span class="day-name">{{ formatDayName(date) }}</span>
              <span class="day-date">{{ date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) }}</span>
            </div>

            <div class="courses-stack">
              <template v-if="loading">
                <div
                  v-for="i in 2"
                  :key="i"
                  class="mobile-course-card bg-slate-50 border-none"
                >
                  <Skeleton
                    width="100%"
                    height="4rem"
                    border-radius="12px"
                  />
                </div>
              </template>
              <template v-else>
                <div
                  v-if="getCoursesForDay(date).length === 0"
                  class="empty-day"
                >
                  No sessions scheduled
                </div>
                <div
                  v-for="course in getCoursesForDay(date)"
                  :key="course.id"
                  class="mobile-course-card"
                  :class="{
                    'is-booked': isBookedByUser(course),
                    'is-restricted': isRestrictedForTrial(course),
                    'is-past': isPastCourse(course)
                  }"
                  :style="course.cycleCategory ? { borderLeft: `6px solid ${course.cycleCategory.colorHex}` } : {}"
                  @click="$emit('course-click', course)"
                >
                  <div class="card-left">
                    <div
                      v-if="course.cycleCategory"
                      class="px-1.5 py-0.5 rounded-full text-[8px] font-black uppercase"
                      :style="{ backgroundColor: course.cycleCategory.colorHex, color: 'white' }"
                    >
                      {{ course.cycleCategory.name }}
                    </div>
                    <div class="course-time">
                      {{ formatTime(course.startTime) }}
                    </div>
                    <div class="course-duration">
                      {{ course.durationMinutes }} MIN
                    </div>
                  </div>

                  <div class="card-main">
                    <div class="course-title">
                      {{ course.title }}
                      <span
                        v-if="isRestrictedForTrial(course)"
                        class="ml-2 text-[10px] text-slate-500 font-black"
                      >
                        <i class="pi pi-lock" /> RESTRICTED
                      </span>
                      <span
                        v-if="isPastCourse(course)"
                        class="ml-2 text-[10px] text-slate-400 font-black"
                      >
                        <i class="pi pi-history" /> PAST
                      </span>
                    </div>
                    <div class="course-coach">
                      Coach: {{ course.user?.name }}
                    </div>
                  </div>

                  <div class="card-right">
                    <div
                      v-if="isBookedByUser(course)"
                      class="booked-indicator"
                    >
                      <i class="pi pi-check-circle" />
                    </div>
                    <div
                      v-else
                      class="spots-pill"
                      :class="{ 'is-full': course.bookings.filter(b => !b.isWaitlist).length >= course.capacity }"
                    >
                      {{ course.capacity - course.bookings.filter(b => !b.isWaitlist).length }}
                      <i class="pi pi-users" />
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped lang="scss">
.mobile-calendar {
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    touch-action: pan-y; /* Allow vertical scrolling but let us handle horizontal swipes */
}

.calendar-content-wrapper {
  position: relative;
  width: 100%;
  overflow-x: hidden;
}

/* Slide Transitions */
.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-left-enter-from {
  opacity: 0;
  transform: translateX(30px);
}
.slide-left-leave-to {
  opacity: 0;
  transform: translateX(-30px);
}

.slide-right-enter-from {
  opacity: 0;
  transform: translateX(-30px);
}
.slide-right-leave-to {
  opacity: 0;
  transform: translateX(30px);
}

.mobile-nav {
    background: #0f172a;
    color: white;
    padding: 1rem;
    position: sticky;
    top: 80px;
    z-index: 100;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
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

    &.is-restricted {
        background: #f1f5f9;
        border-color: #cbd5e1;
        opacity: 0.8;

        .card-left .course-time, .card-main .course-title {
            color: #64748b;
        }
    }

    &.is-past {
        opacity: 0.6;
        filter: grayscale(0.8);
        background: #f1f5f9;
        border-color: #e2e8f0;

        .card-left {
          border-right-color: #e2e8f0;
          .course-time, .course-duration { color: #94a3b8; }
        }

        .card-main {
          .course-title, .course-coach { color: #94a3b8; }
        }

        .card-right {
          .booked-indicator { color: #94a3b8; }
          .spots-pill { background: #e2e8f0; color: #94a3b8; }
        }
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
