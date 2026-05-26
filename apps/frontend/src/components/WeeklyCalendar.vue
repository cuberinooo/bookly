<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { formatDate, formatTime } from '../services/date-utils';
import { useAuthStore } from '../store/useAuthStore';

const props = withDefaults(defineProps<{
    courses: any[];
    isCompactView?: boolean;
    userId?: number;
    baseDate?: Date;
    cycleInfo?: { name: string; currentWeek: number; totalWeeks: number } | null;
    loading?: boolean;
}>(), {
    isCompactView: true,
    userId: undefined,
    baseDate: () => new Date(),
    cycleInfo: null,
    loading: false
});

const emit = defineEmits(['course-click', 'cell-click', 'update:baseDate']);

const authStore = useAuthStore();
const internalBaseDate = ref(new Date(props.baseDate));

watch(() => props.baseDate, (newVal) => {
    if (newVal.getTime() !== internalBaseDate.value.getTime()) {
        internalBaseDate.value = new Date(newVal);
    }
});

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const hours = Array.from({ length: 16 }, (_, i) => i + 6); // 6:00 to 22:00

const currentWeek = computed(() => {
    const start = new Date(internalBaseDate.value);
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
    return `${start.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })} - ${end.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })}`;
});

function navigate(direction: number) {
    const newDate = new Date(internalBaseDate.value);
    newDate.setDate(newDate.getDate() + (direction * 7));
    internalBaseDate.value = newDate;
    emit('update:baseDate', newDate);
}

function resetToToday() {
    const newDate = new Date();
    internalBaseDate.value = newDate;
    emit('update:baseDate', newDate);
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
    return course.bookings?.some((b: any) => b.user?.id === props.userId);
}

function isRestrictedForTrial(course: any) {
    const isTrial = authStore.isTrial;
    return isTrial && course.allowTrial === false;
}

function isPastCourse(course: any) {
    return new Date(course.endTime) < new Date();
}

function onSlotClick(day: Date, hour: number) {
    const clickDate = new Date(day);
    clickDate.setHours(hour, 0, 0, 0);
    emit('cell-click', clickDate);
}
</script>

<template>
  <div
    class="athletic-calendar"
    :class="{ 'compact-mode': isCompactView }"
  >
    <div
      v-if="cycleInfo"
      class="cycle-status-bar mb-6 p-4 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between"
    >
      <div class="flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
          <i class="pi pi-sync text-amber-600 animate-spin-slow" />
        </div>
        <div class="flex flex-col">
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Active Programming</span>
          <span class="text-lg font-black text-slate-900 font-barlow uppercase">{{ cycleInfo.name }}</span>
        </div>
      </div>
      <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase">Cycle Progress</span>
        <span class="text-sm font-black text-amber-600">WEEK {{ cycleInfo.currentWeek }} / {{ cycleInfo.totalWeeks }}</span>
      </div>
    </div>

    <div class="calendar-toolbar">
      <div class="date-info">
        <h2>{{ currentWeekLabel }}</h2>
      </div>
      <div class="view-controls">
        <div class="nav-group">
          <Button
            icon="pi pi-chevron-left"
            variant="text"
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
            @click="navigate(1)"
          />
        </div>
      </div>
    </div>

    <div class="calendar-container">
      <!-- Header Grid -->
      <div class="calendar-header-grid">
        <div
          v-if="!isCompactView"
          class="time-corner"
        />
        <div
          class="days-header-wrapper"
          :class="{ 'grid-cols-7': true }"
        >
          <div
            v-for="(date, idx) in currentWeek"
            :key="idx"
            :class="['day-header', { 'is-today': isToday(date) }]"
          >
            <span class="day-name">{{ days[idx] }}</span>
            <span class="day-date">{{ date.getDate() }}</span>
          </div>
        </div>
      </div>

      <!-- Body Scroll Area -->
      <div class="calendar-body-scroll">
        <div class="calendar-body-grid">
          <!-- Time Axis -->
          <div
            v-if="!isCompactView"
            class="time-axis"
          >
            <div
              v-for="hour in hours"
              :key="hour"
              class="time-slot-label"
            >
              {{ hour.toString().padStart(2, '0') }}:00
            </div>
          </div>

          <!-- Days Grid -->
          <div
            class="days-body-wrapper"
            :class="{ 'grid-cols-7': true }"
          >
            <div
              v-for="(date, dayIdx) in currentWeek"
              :key="dayIdx"
              class="day-column"
            >
              <!-- Standard View: Time Grid -->
              <template v-if="!isCompactView">
                <div
                  v-for="hour in hours"
                  :key="hour"
                  class="hour-slot-row"
                  @click="onSlotClick(date, hour)"
                >
                  <div class="half-hour-slot" />
                  <div class="half-hour-slot" />
                </div>
              </template>

              <!-- Courses (Both views use grid positioning if not compact, or flex if compact) -->
              <template v-if="loading">
                <div
                  v-for="i in 2"
                  :key="i"
                  class="p-2 border border-slate-100 rounded-lg mb-2"
                >
                  <Skeleton
                    width="100%"
                    height="3rem"
                  />
                </div>
              </template>
              <template v-else>
                <div
                  v-for="course in getCoursesForDay(date)"
                  :key="course.id"
                  class="course-card"
                  :class="{
                    'is-booked': isBookedByUser(course),
                    'is-restricted': isRestrictedForTrial(course),
                    'is-past': isPastCourse(course),
                    'is-postponed': course.status === 'postponed'
                  }"
                  :style="[
                    !isCompactView ? { gridRow: getGridRow(course.startTime, course.durationMinutes) } : {},
                    course.cycleCategory ? { borderLeft: `6px solid ${course.cycleCategory.colorHex}` } : {}
                  ]"
                  @click.stop="$emit('course-click', course)"
                >
                  <!-- Cycle Category Tag -->
                  <div
                    v-if="course.cycleCategory"
                    class="cycle-tag mb-1 flex items-center gap-1"
                    :style="{ color: course.cycleCategory.colorHex }"
                  >
                    <i class="pi pi-bolt text-[8px]" />
                    <span class="text-[9px] font-black uppercase tracking-tighter">{{ course.cycleCategory.name }}</span>
                  </div>

                  <div class="flex flex-col gap-1 w-full mb-1">
                    <div
                      v-if="course.status === 'postponed'"
                      class="postponed-badge"
                    >
                      <i class="pi pi-clock" /> POSTPONED
                    </div>
                    <div class="flex justify-between items-start w-full gap-1">
                      <div
                        v-if="isBookedByUser(course)"
                        class="booked-badge"
                      >
                        <i class="pi pi-check" /> BOOKED
                      </div>
                      <div
                        v-if="isRestrictedForTrial(course)"
                        class="restricted-badge"
                        title="Restricted for Trial Members"
                      >
                        <i class="pi pi-lock" /> TRIAL RESTRICTED
                      </div>
                      <div
                        v-if="isPastCourse(course)"
                        class="past-badge"
                      >
                        <i class="pi pi-history" /> PAST
                      </div>
                    </div>
                  </div>
                  <div class="course-time">
                    {{ formatTime(course.startTime) }}
                    <span
                      v-if="!isCompactView"
                      class="duration-tag"
                    >/ {{ course.durationMinutes }} MIN</span>
                  </div>
                  <div class="course-title">
                    {{ course.title }}
                  </div>

                  <div class="course-meta">
                    <div
                      v-if="course.status === 'postponed' && course.postponedBy"
                      class="coach-line !text-red-500 font-bold"
                    >
                      POSTPONED BY {{ course.postponedBy.name }}
                    </div>
                    <div class="coach-line">
                      <i class="pi pi-user text-[10px]" /> {{ course.user?.name }}
                    </div>
                    <div class="course-spots">
                      <template v-if="course.bookings.filter(b => !b.isWaitlist).length < course.capacity">
                        {{ course.bookings.filter(b => !b.isWaitlist).length }} / {{ course.capacity }} <i class="pi pi-users text-[10px]" />
                      </template>
                      <template v-else>
                        <span class="text-amber-500">FULL</span>
                      </template>
                    </div>
                  </div>
                </div>
              </template>
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

    &.is-restricted {
        background: #f1f5f9;
        border-color: #94a3b8;
        border-left: 4px solid #94a3b8;
        opacity: 0.8;

        .course-title {
            color: #475569;
        }
    }

    &.is-past {
        opacity: 0.6;
        border-left-color: #94a3b8;
        background: #f8fafc;
        filter: grayscale(0.8);

        &:hover {
          opacity: 0.9;
          filter: grayscale(0.2);
        }

        .course-title, .course-time, .coach-line, .course-spots {
            color: #94a3b8 !important;
        }
    }

    &.is-postponed {
        background: #f1f5f9;
        border-color: #94a3b8;
        border-left: 4px dashed #64748b;
        opacity: 0.7;
        filter: grayscale(0.5);

        .course-title {
            text-decoration: line-through;
            color: #64748b;
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

    .postponed-badge {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 0.6rem;
        color: white;
        background: #ef4444; // red-500
        padding: 2px 6px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
        width: fit-content;
        letter-spacing: 0.05em;
        margin-bottom: 4px;

        i { font-size: 0.6rem; }
    }

    .restricted-badge {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 0.55rem;
        color: #f8fafc;
        background: #64748b;
        padding: 1px 4px;
        border-radius: 3px;
        display: flex;
        align-items: center;
        gap: 2px;
        z-index: 2;

        i { font-size: 0.5rem; }
    }

    .past-badge {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-size: 0.55rem;
        color: #64748b;
        background: #e2e8f0;
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

.cycle-tag {
  font-family: 'Barlow Condensed', sans-serif;
  line-height: 1;
  margin-bottom: 4px;
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
