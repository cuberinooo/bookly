<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useAuthStore } from '../store/useAuthStore';
import { useCourseStore } from '../store/useCourseStore';
import { useCourseDeletion } from '../composables/useCourseDeletion';
import ParticipantsDialog from './ParticipantsDialog.vue';

import { formatTime, formatDateWithDay } from '../services/date-utils';

const props = defineProps<{
    showAllDefault?: boolean
}>();

const emit = defineEmits(['edit']);

const confirm = useConfirm();
const toast = useToast();
const authStore = useAuthStore();
const courseStore = useCourseStore();
const { confirmDeleteCourse } = useCourseDeletion();

const courses = computed(() => courseStore.courseList);
const totalRecords = computed(() => courseStore.pagination.totalItems);
const loading = computed(() => courseStore.loading);
const transitionName = ref('slide-left');
const participantsDialog = ref(false);
const selectedCourse = ref<any>(null);

const showAllCourses = ref(true);

const menu = ref();
const activeCourse = ref<any>(null);
const menuItems = computed(() => {
    if (!activeCourse.value) return [];

    return [
        {
            label: 'Participants',
            icon: 'pi pi-users',
            disabled: activeCourse.value.status === 'postponed',
            command: () => {
                selectedCourse.value = activeCourse.value;
                participantsDialog.value = true;
            }
        },
        {
            label: 'Postpone',
            icon: 'pi pi-clock',
            disabled: activeCourse.value.status === 'postponed',
            command: () => confirmPostponeCourse(activeCourse.value)
        },
        {
            label: 'Edit',
            icon: 'pi pi-pencil',
            command: () => emit('edit', activeCourse.value)
        },
        {
            separator: true
        },
        {
            label: 'Delete',
            icon: 'pi pi-trash',
            class: 'delete-menu-item',
            command: () => confirmDeleteCourse(activeCourse.value)
        }
    ];
});

function toggleMenu(event: any, course: any) {
    activeCourse.value = course;
    menu.value.toggle(event);
}

const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
    startDate: (() => {
        const d = new Date();
        const day = d.getDay();
        const diff = (day === 0 ? 6 : day - 1);
        d.setDate(d.getDate() - diff);
        d.setHours(0, 0, 0, 0);
        return d;
    })(),
    endDate: (() => {
        const d = new Date();
        const day = d.getDay();
        const diff = (day === 0 ? 0 : 7 - day);
        d.setDate(d.getDate() + diff);
        d.setHours(23, 59, 59, 999);
        return d;
    })()
});

const isMobile = ref(window.innerWidth <= 768);

const currentWeekStart = computed(() => {
    const d = new Date(lazyParams.value.startDate);
    const day = d.getDay();
    const diff = (day === 0 ? 6 : day - 1);
    d.setDate(d.getDate() - diff);
    d.setHours(0, 0, 0, 0);
    return d;
});

const currentWeekEnd = computed(() => {
    const d = new Date(currentWeekStart.value);
    d.setDate(d.getDate() + 6);
    d.setHours(23, 59, 59, 999);
    return d;
});

const weekLabel = computed(() => {
    const start = currentWeekStart.value;
    const end = currentWeekEnd.value;
    return `${start.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })} - ${end.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })}`;
});

function navigateWeek(direction: number) {
    const newDate = new Date(currentWeekStart.value);
    newDate.setDate(newDate.getDate() + (direction * 7));

    transitionName.value = direction > 0 ? 'slide-left' : 'slide-right';

    lazyParams.value.startDate = newDate;
    lazyParams.value.endDate = new Date(newDate);
    lazyParams.value.endDate.setDate(newDate.getDate() + 6);
    lazyParams.value.endDate.setHours(23, 59, 59, 999);

    onFilter();
}

function handleResize() {
    isMobile.value = window.innerWidth <= 768;
}

watch(
  () => authStore.user?.id,
  (newId) => {
    if (newId) {
      loadLazyData();
    }
  }
);

async function loadLazyData() {
    try {
        const params: any = {
            page: lazyParams.value.page,
            limit: isMobile.value ? 50 : lazyParams.value.rows
        };

        if (authStore.isTrainer && !showAllCourses.value) {
            params.trainerId = authStore.user?.id;
        }

        if (lazyParams.value.startDate) {
            params.startDate = lazyParams.value.startDate.toISOString();
        }
        if (lazyParams.value.endDate) {
            params.endDate = lazyParams.value.endDate.toISOString();
        }

        await courseStore.fetchCourses(params);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch courses', life: 5000 });
    }
}

function onPage(event: any) {
    const direction = event.page + 1 > lazyParams.value.page ? 1 : -1;
    transitionName.value = direction > 0 ? 'slide-left' : 'slide-right';

    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    lazyParams.value.page = event.page + 1;
    loadLazyData();
}

const touchStart = ref({ x: 0, y: 0 });

function handleTouchStart(e: TouchEvent) {
    touchStart.value = {
        x: e.changedTouches[0].screenX,
        y: e.changedTouches[0].screenY
    };
}

function handleTouchEnd(e: TouchEvent) {
    const deltaX = e.changedTouches[0].screenX - touchStart.value.x;
    const deltaY = e.changedTouches[0].screenY - touchStart.value.y;

    // Only trigger if horizontal swipe is dominant and significant
    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 60) {
        if (deltaX < 0) {
            // Swipe Left -> Next Page
            if (isMobile.value) {
                navigateWeek(1);
            } else {
                const nextFirst = lazyParams.value.first + lazyParams.value.rows;
                if (nextFirst < totalRecords.value) {
                    transitionName.value = 'slide-left';
                    onPage({
                        first: nextFirst,
                        rows: lazyParams.value.rows,
                        page: Math.floor(nextFirst / lazyParams.value.rows)
                    });
                }
            }
        } else {
            // Swipe Right -> Prev Page
            if (isMobile.value) {
                navigateWeek(-1);
            } else {
                const prevFirst = lazyParams.value.first - lazyParams.value.rows;
                if (prevFirst >= 0) {
                    transitionName.value = 'slide-right';
                    onPage({
                        first: prevFirst,
                        rows: lazyParams.value.rows,
                        page: Math.floor(prevFirst / lazyParams.value.rows)
                    });
                }
            }
        }
    }
}

function onFilter() {
    lazyParams.value.page = 1;
    lazyParams.value.first = 0;
    loadLazyData();
}

function clearFilters() {
    const d = new Date();
    const day = d.getDay();
    const diff = (day === 0 ? 6 : day - 1);
    d.setDate(d.getDate() - diff);
    d.setHours(0, 0, 0, 0);

    lazyParams.value.startDate = d;

    const end = new Date(d);
    end.setDate(d.getDate() + 6);
    end.setHours(23, 59, 59, 999);
    lazyParams.value.endDate = end;

    onFilter();
}

defineExpose({ refresh: loadLazyData });

function formatDuration(min: number) {
    if (min < 60) return `${min}min`;
    const hours = Math.floor(min / 60);
    const remaining = min % 60;
    return remaining > 0 ? `${hours}h ${remaining}min` : `${hours} hour${hours > 1 ? 's' : ''}`;
}
function confirmPostponeCourse(course: any) {
    confirm.require({
        message: `Are you sure you want to postpone "${course.title}"? This will automatically unbook all participants and mark the course as postponed.`,
        header: 'Confirm Postponement',
        icon: 'pi pi-clock',
        acceptProps: {
            label: 'Postpone Course',
            severity: 'warn'
        },
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary'
        },
        accept: async () => {
            try {
                await api.post(`/courses/${course.id}/postpone`);
                toast.add({ severity: 'success', summary: 'Postponed', detail: 'Course postponed and members unbooked', life: 5000 });
                loadLazyData();
            } catch (e: any) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.error || 'Failed to postpone course', life: 5000 });
            }
        }
    });
}

async function removeParticipant(bookingId: number) {
    confirm.require({
        message: 'Remove this participant from the course?',
        header: 'Confirm Removal',
        icon: 'pi pi-user-minus',
        acceptProps: { severity: 'danger' },
        rejectProps: {
          label: 'Cancel',
          severity: 'primary', // Use base styling
        },
        accept: async () => {
            try {
                await api.delete(`/bookings/${bookingId}`);
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Participant removed', life: 5000 });
                loadLazyData();
                participantsDialog.value = false;
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to remove participant', life: 5000 });
            }
        }
    });
}

onMounted(() => {
    window.addEventListener('resize', handleResize);
    loadLazyData();
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});
</script>

<template>
  <section class="managed-courses-section">
    <!-- Action Menu (Single instance used for all rows/cards) -->
    <Menu
      ref="menu"
      :model="menuItems"
      :popup="true"
    />

    <div class="section-header mb-6">
      <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h2>Managed Courses</h2>
        <div class="flex flex-wrap items-end gap-3 md:gap-4">
          <div
            v-if="authStore.isTrainer"
            class="flex items-center gap-2 h-10 md:h-8 px-3 bg-slate-100 rounded-lg border border-slate-200"
          >
            <ToggleSwitch
              v-model="showAllCourses"
              size="small"
              @change="onFilter"
            />
            <span class="text-[10px] font-bold uppercase text-slate-600 whitespace-nowrap">Show All</span>
          </div>
          <div
            v-if="!isMobile"
            class="flex flex-col gap-1 flex-1 min-w-[120px]"
          >
            <label
              for="filterFrom"
              class="text-[10px] md:text-xs font-bold uppercase text-slate-500"
            >From</label>
            <DatePicker
              v-model="lazyParams.startDate"
              input-id="filterFrom"
              placeholder="Start"
              size="small"
              date-format="dd.mm.yy"
              fluid
              @date-select="onFilter"
            />
          </div>
          <div
            v-if="!isMobile"
            class="flex flex-col gap-1 flex-1 min-w-[120px]"
          >
            <label
              for="filterTo"
              class="text-[10px] md:text-xs font-bold uppercase text-slate-500"
            >To</label>
            <DatePicker
              v-model="lazyParams.endDate"
              input-id="filterTo"
              placeholder="End"
              size="small"
              date-format="dd.mm.yy"
              fluid
              @date-select="onFilter"
            />
          </div>
          <Button
            v-if="!isMobile"
            v-tooltip="'Clear Filters'"
            icon="pi pi-filter-slash"
            variant="text"
            class="h-10 w-10 md:h-8 md:w-8"
            @click="clearFilters"
          />
        </div>
      </div>
    </div>

    <!-- Mobile Week Navigation -->
    <div
      v-if="isMobile"
      class="mobile-week-nav mb-6"
    >
      <div class="flex items-center justify-between bg-slate-900 text-white rounded-xl p-2">
        <Button
          icon="pi pi-chevron-left"
          variant="text"
          rounded
          class="!text-white"
          @click="navigateWeek(-1)"
        />
        <div class="flex flex-col items-center">
          <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest">Selected Week</span>
          <span class="text-sm font-black font-['Barlow_Condensed']">{{ weekLabel }}</span>
        </div>
        <Button
          icon="pi pi-chevron-right"
          variant="text"
          rounded
          class="!text-white"
          @click="navigateWeek(1)"
        />
      </div>
    </div>

    <!-- Desktop Table View (Hidden on mobile) -->
    <div class="hidden md:block">
      <DataTable
        v-model:first="lazyParams.first"
        :value="loading ? Array(10).fill({}) : courses"
        lazy
        paginator
        :rows="lazyParams.rows"
        :total-records="totalRecords"
        class="managed-table"
        :row-class="(data) => ({ 'is-postponed': data.status === 'postponed' })"
        @page="onPage"
      >
        <Column
          field="title"
          header="Course"
        >
          <template #body="slotProps">
            <Skeleton
              v-if="loading"
              width="60%"
            />
            <div
              v-else
              class="flex flex-col"
            >
              <span class="course-title-cell">{{ slotProps.data.title }}</span>
              <Tag
                v-if="slotProps.data.status === 'postponed'"
                severity="secondary"
                class="w-fit text-[8px] uppercase font-black tracking-widest mt-1"
              >
                Postponed by {{ slotProps.data.postponedBy?.name || 'Trainer' }}
              </Tag>
            </div>
          </template>
        </Column>
        <Column header="Schedule">
          <template #body="slotProps">
            <div
              v-if="loading"
              class="flex flex-col gap-1"
            >
              <Skeleton width="40%" />
              <Skeleton width="30%" />
            </div>
            <div
              v-else
              class="flex flex-col"
            >
              <span class="font-bold text-sm">{{ formatDateWithDay(slotProps.data.startTime, true) }}</span>
              <span class="text-xs">{{ formatTime(slotProps.data.startTime) }}</span>
            </div>
          </template>
        </Column>
        <Column
          header="Trainer"
        >
          <template #body="slotProps">
            <Skeleton
              v-if="loading"
              width="60%"
            />
            <span
              v-else
              class="text-sm font-medium"
              :class="{ 'text-amber-600_ font-bold': slotProps.data.user?.id === authStore.user?.id }"
            >
              {{ slotProps.data.user?.id === authStore.user?.id ? 'YOU' : slotProps.data.user?.name }}
            </span>
          </template>
        </Column>
        <Column header="Duration">
          <template #body="slotProps">
            <Skeleton
              v-if="loading"
              width="3rem"
            />
            <span v-else>
              {{ formatDuration(slotProps.data.durationMinutes) }}
            </span>
          </template>
        </Column>
        <Column header="Slots">
          <template #body="slotProps">
            <Skeleton
              v-if="loading"
              width="4rem"
              height="2.5rem"
            />
            <div
              v-else
              class="flex items-center gap-2"
            >
              <span :class="['slot-badge', { 'is-full': slotProps.data.bookings.length >= slotProps.data.capacity }]">
                {{ slotProps.data.bookings.length }} / {{ slotProps.data.capacity }}
              </span>
            </div>
          </template>
        </Column>
        <Column
          header="Actions"
          class="text-right"
        >
          <template #body="slotProps">
            <div
              v-if="loading"
              class="flex justify-end gap-2"
            >
              <Skeleton
                shape="circle"
                size="2rem"
              />
            </div>
            <div
              v-else
              class="flex justify-end"
            >
              <Button
                label="Manage"
                icon="pi pi-cog"
                variant="text"
                size="small"
                class="action-btn"
                @click="toggleMenu($event, slotProps.data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Mobile Card View (Hidden on desktop) -->
    <div
      class="md:hidden flex flex-col gap-4 min-h-[500px] touch-pan-y"
      @touchstart="handleTouchStart"
      @touchend="handleTouchEnd"
    >
      <div class="calendar-content-wrapper min-h-[400px]">
        <Transition
          :name="transitionName"
          mode="out-in"
        >
          <div
            :key="lazyParams.startDate.getTime()"
            class="flex flex-col gap-4"
          >
            <template v-if="loading">
              <div
                v-for="i in 3"
                :key="i"
                class="mobile-course-card p-4 bg-slate-50 rounded-xl border border-slate-200"
              >
                <div class="flex justify-between items-start mb-3">
                  <div class="flex flex-col gap-2">
                    <Skeleton
                      width="140px"
                      height="1.5rem"
                    />
                    <Skeleton
                      width="100px"
                      height="1rem"
                    />
                  </div>
                  <Skeleton
                    width="50px"
                    height="1.5rem"
                  />
                </div>
                <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-200">
                  <Skeleton
                    width="80px"
                    height="1rem"
                  />
                  <Skeleton
                    shape="circle"
                    size="2.5rem"
                  />
                </div>
              </div>
            </template>
            <template v-else>
              <div
                v-for="course in courses"
                :key="course.id"
                class="mobile-course-card p-4 bg-slate-50 rounded-xl border border-slate-200"
              >
                <div class="flex justify-between items-start mb-3">
                  <div class="flex flex-col">
                    <span
                      class="text-lg font-black uppercase tracking-tight leading-tight mb-1"
                      style="font-family: 'Barlow Condensed', sans-serif;"
                    >
                      {{ course.title }}
                    </span>
                    <Tag
                      v-if="course.status === 'postponed'"
                      severity="secondary"
                      class="w-fit text-[8px] uppercase font-black tracking-widest mb-2"
                    >
                      Postponed by {{ course.postponedBy?.name || 'Trainer' }}
                    </Tag>
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                      <i class="pi pi-calendar text-[10px]" />
                      {{ formatDateWithDay(course.startTime, true) }} @ {{ formatTime(course.startTime) }}
                    </div>
                    <div
                      class="flex items-center gap-2 text-xs font-bold mt-1"
                      :class="course.user?.id === authStore.user?.id ? '!text-amber-600' : 'text-slate-500'"
                    >
                      <i class="pi pi-user text-[10px]" />

                      {{ course.user?.id === authStore.user?.id ? 'YOU' : course.user?.name }}
                    </div>
                  </div>
                  <span :class="['slot-badge !py-1 !px-2 !text-[10px]', { 'is-full': course.bookings.length >= course.capacity }]">
                    {{ course.bookings.length }} / {{ course.capacity }}
                  </span>
                </div>

                <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-200">
                  <div
                    class="text-[10px] font-black uppercase text-slate-400"
                    style="font-family: 'Barlow Condensed', sans-serif;"
                  >
                    Duration: {{ formatDuration(course.durationMinutes) }}
                  </div>
                  <Button
                    label="Manage"
                    icon="pi pi-cog"
                    severity="secondary"
                    outlined
                    size="small"
                    class="!py-1 !px-3"
                    @click="toggleMenu($event, course)"
                  />
                </div>
              </div>

              <div
                v-if="courses.length === 0"
                class="text-center py-8 text-slate-400"
              >
                <i class="pi pi-calendar-slash text-4xl mb-2" />
                <p class="font-bold uppercase text-sm">
                  No courses found
                </p>
              </div>
            </template>
          </div>
        </Transition>
      </div>

      <Paginator
        v-if="!isMobile"
        v-model:first="lazyParams.first"
        :rows="lazyParams.rows"
        :total-records="totalRecords"
        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
        class="mobile-paginator !bg-transparent !border-none mt-4"
        @page="onPage"
      />
    </div>

    <ParticipantsDialog
      v-model:visible="participantsDialog"
      :course="selectedCourse"
      @remove-participant="removeParticipant"
    />
  </section>
</template>

<style lang="scss" scoped>
.managed-courses-section {
    @apply bg-white p-4 md:p-10 rounded-xl md:rounded-2xl border border-slate-200 shadow-sm;
    touch-action: pan-y;
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

.section-header {
    h2 {
        @apply text-xl md:text-2xl font-black uppercase tracking-tighter text-slate-900;
        font-family: 'Barlow Condensed', sans-serif;
    }
}

.managed-table {
    :deep(.p-datatable-thead > tr > th) {
        @apply bg-slate-50 text-slate-700 font-bold uppercase tracking-wider py-5 px-4;
        font-family: 'Barlow Condensed', sans-serif;
    }

    :deep(.p-datatable-tbody > tr) {
        @apply transition-colors duration-200;

        @media (hover: hover) {
            &:hover { @apply bg-slate-50/50; }
        }

        &.is-postponed {
          @apply opacity-50 grayscale;

          .action-btn {
            @apply pointer-events-auto; // Allow deletion/edit even if postponed
          }
        }
    }
}

.is-postponed-card {
  @apply opacity-60 grayscale border-dashed;
}

.course-title-cell {
    @apply font-bold text-lg text-slate-900 uppercase;
    font-family: 'Barlow Condensed', sans-serif;
}

.slot-badge {
    @apply px-4 py-1.5 bg-slate-100 rounded text-sm font-extrabold text-slate-600;
    font-family: 'Barlow Condensed', sans-serif;

    &.is-full { @apply bg-red-100 text-red-600; }
    &:not(.is-full) { @apply bg-amber-50 text-amber-600; }
}

.action-btn {
    @apply text-slate-500 transition-colors duration-200;

    @media (hover: hover) {
        &:hover { @apply text-amber-500 bg-amber-50; }
        &.delete-btn:hover { @apply text-red-500 bg-red-50; }
    }
}

.mobile-paginator {
    :deep(.p-paginator-page), :deep(.p-paginator-next), :deep(.p-paginator-last), :deep(.p-paginator-prev), :deep(.p-paginator-first) {
      @apply #{"!min-w-[32px] !h-8 !text-xs"};
    }
}

.text-amber-600_ {
  color: var(--color-amber-600) !important;
}

.mobile-week-nav {
  position: sticky;
  top: 75px; // Offset parent padding
  z-index: 100;
  margin-left: -1rem;
  margin-right: -1rem;
  padding: 0.5rem 1rem;
  background: white; // Cover content scrolling underneath

  .p-button {
    @apply h-10 w-10 p-0;
  }

  > div {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  }
}

:deep(.delete-menu-item) {
  .p-menuitem-link {
    .p-menuitem-text, .p-menuitem-icon {
      @apply text-red-500 font-bold;
    }
  }
}
</style>
