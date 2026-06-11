<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { formatTime } from '../services/date-utils';
import { useAuthStore } from '../store/useAuthStore';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    courses: any[];
    userId?: number;
    baseDate?: Date;
    cycleInfo?: { name: string; currentWeek: number; totalWeeks: number; startDate: string } | null;
    loading?: boolean;
}>(), {
    userId: undefined,
    baseDate: () => new Date(),
    cycleInfo: null,
    loading: false
});

const emit = defineEmits(['course-click', 'cell-click', 'update:baseDate']);

const { t, locale } = useI18n();
const authStore = useAuthStore();
const isTrainerMode = computed(() => authStore.isTrainer && authStore.viewMode === 'trainer');
const internalBaseDate = ref(new Date(props.baseDate));

watch(() => props.baseDate, (newVal) => {
    if (newVal.getTime() !== internalBaseDate.value.getTime()) {
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

const days = computed(() => [
    t('app.days.monday'),
    t('app.days.tuesday'),
    t('app.days.wednesday'),
    t('app.days.thursday'),
    t('app.days.friday'),
    t('app.days.saturday'),
    t('app.days.sunday')
]);

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
    return `${start.toLocaleDateString(locale.value, { day: '2-digit', month: '2-digit' })} - ${end.toLocaleDateString(locale.value, { day: '2-digit', month: '2-digit', year: 'numeric' })}`;
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

function isToday(date: Date) {
    const today = new Date();
    return date.toDateString() === today.toDateString();
}

function isBookedByUser(course: any) {
    if (!props.userId) return false;
    return course.bookings?.some((b: any) => b.user?.id === props.userId);
}

function isWaitlistByUser(course: any) {
    if (!props.userId) return false;
    return course.bookings?.some((b: any) => b.user?.id === props.userId && b.isWaitlist);
}

function isRestrictedForTrial(course: any) {
    const isTrial = authStore.isTrial;
    return isTrial && course.allowTrial === false;
}

function isPastCourse(course: any) {
    return new Date(course.endTime) < new Date();
}

function onQuickAdd(day: Date) {
    const clickDate = new Date(day);
    clickDate.setHours(9, 0, 0, 0);
    emit('cell-click', clickDate);
}
</script>

<template>
  <div
    class="athletic-calendar compact-mode"
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
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ t('calendar.activeProgramming') }}</span>
          <span class="text-lg font-black text-slate-900 font-barlow uppercase">{{ cycleInfo.name }}</span>
        </div>
      </div>
      <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase">{{ t('calendar.cycleProgress') }}</span>
        <span class="text-sm font-black text-amber-600">{{ t('calendar.week') }} {{ displayedCycleWeek }} / {{ cycleInfo.totalWeeks }}</span>
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
            :label="t('home.today')"
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
          class="days-header-wrapper grid-cols-7"
        >
          <div
            v-for="(date, idx) in currentWeek"
            :key="idx"
            :class="['day-header', { 'is-today': isToday(date) }]"
          >
            <span class="day-name">{{ days[idx] }}</span>
            <span class="day-date">{{ date.getDate() }}</span>

            <!-- Quick Add Button for Trainer Mode -->
            <Button
              v-if="isTrainerMode"
              v-tooltip.top="t('dashboard.createNewCourse')"
              icon="pi pi-plus"
              severity="primary"
              variant="outlined"
              size="small"
              class="mt-1.5 cursor-pointer border-amber-500/30 text-amber-500 hover:bg-amber-500/10 hover:border-amber-500 hover:text-amber-400 transition-all duration-200 rounded-full w-6 h-6 flex items-center justify-center p-0 shadow-sm text-[10px]"
              @click.stop="onQuickAdd(date)"
            />
          </div>
        </div>
      </div>

      <!-- Body Scroll Area -->
      <div class="calendar-body-scroll">
        <div class="calendar-body-grid">
          <!-- Days Grid -->
          <div
            class="days-body-wrapper grid-cols-7"
          >
            <div
              v-for="(date, dayIdx) in currentWeek"
              :key="dayIdx"
              class="day-column"
            >
              <!-- Courses -->
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
                    'is-cancelled': course.status === 'cancelled'
                  }"
                  :style="course.cycleCategory ? { borderLeft: `6px solid ${course.cycleCategory.colorHex}` } : {}"
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
                      v-if="course.status === 'cancelled'"
                      class="cancelled-badge"
                    >
                      <i class="pi pi-clock" /> {{ course.autoCancelled ? t('calendar.autoCancelled') : t('calendar.cancelled') }}
                    </div>
                    <div class="flex justify-between items-start w-full gap-1">
                      <div
                        v-if="isBookedByUser(course)"
                        class="booked-badge"
                        :class="{ 'is-waitlist': isWaitlistByUser(course) }"
                      >
                        <i :class="isWaitlistByUser(course) ? 'pi pi-clock' : 'pi pi-check'" />
                        {{ isWaitlistByUser(course) ? t('app.waitlist').toUpperCase() : t('calendar.booked') }}
                      </div>
                      <div
                        v-if="isRestrictedForTrial(course)"
                        class="restricted-badge"
                        :title="t('calendar.restrictedTooltip')"
                      >
                        <i class="pi pi-lock" /> {{ t('calendar.restricted') }}
                      </div>
                      <div
                        v-if="isPastCourse(course)"
                        class="past-badge"
                      >
                        <i class="pi pi-history" /> {{ t('calendar.past') }}
                      </div>
                    </div>
                  </div>
                  <div class="course-time">
                    {{ formatTime(course.startTime) }}
                  </div>
                  <div class="course-title">
                    {{ course.title }}
                  </div>

                  <div class="course-meta">
                    <div
                      v-if="course.status === 'cancelled'"
                      class="coach-line !text-red-500 font-bold"
                    >
                      <template v-if="course.autoCancelled">
                        <i class="pi pi-cog text-[10px]" /> {{ t('course.autoCancelledLabel') }}
                      </template>
                      <template v-else-if="course.cancelledBy">
                        <i class="pi pi-user text-[10px]" /> {{ t('course.cancelledByLabel') }} {{ course.cancelledBy.name }}
                      </template>
                    </div>
                    <div class="coach-line">
                      <i class="pi pi-user text-[10px]" /> {{ course.user?.name }}
                    </div>
                    <div class="course-spots">
                      <template v-if="course.bookings.filter(b => !b.isWaitlist).length < course.capacity">
                        {{ course.bookings.filter(b => !b.isWaitlist).length }} / {{ course.capacity }} <i class="pi pi-users text-[10px]" />
                      </template>
                      <template v-else>
                        <span class="text-amber-500">{{ t('calendar.full') }}<template v-if="course.bookings.filter(b => b.isWaitlist).length > 0"> (+{{ course.bookings.filter(b => b.isWaitlist).length }})</template></span>
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
    height: auto;
    min-height: 400px;
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
    align-items: center;
    border-left: 1px solid #334155;
    position: relative;

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

.days-body-wrapper {
    flex: 1;
}

.day-column {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem;
    border-left: 1px solid #e2e8f0;
    position: relative;
    min-height: 100px;

    &:first-child { border-left: none; }
}

.course-card {
    grid-column: 1;
    grid-row: auto;
    margin: 0;
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

    &.is-cancelled {
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

        &.is-waitlist {
            background: #64748b;
            color: white;
        }
    }

    .cancelled-badge {
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
        height: auto;
    }

    .course-card {
        margin-bottom: 0.5rem;
    }
}
</style>
