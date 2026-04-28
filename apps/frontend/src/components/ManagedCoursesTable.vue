<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import api from '../services/api';
import { authStore } from '../store/auth';
import ParticipantsDialog from './ParticipantsDialog.vue';

import { formatDate, formatTime } from '../services/date-utils';

const emit = defineEmits(['edit']);

const confirm = useConfirm();
const toast = useToast();

const courses = ref<any[]>([]);
const totalRecords = ref(0);
const loading = ref(false);
const participantsDialog = ref(false);
const selectedCourse = ref<any>(null);

const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
    startDate: null as Date | null,
    endDate: null as Date | null
});

async function loadLazyData() {
    loading.value = true;
    try {
        const params: any = {
            page: lazyParams.value.page,
            limit: lazyParams.value.rows,
            futureOnly: true
        };

        if (authStore.isTrainer()) {
            params.trainerId = authStore.user?.id;
        }

        if (lazyParams.value.startDate) {
            params.startDate = lazyParams.value.startDate.toISOString();
        }
        if (lazyParams.value.endDate) {
            params.endDate = lazyParams.value.endDate.toISOString();
        }

        const response = await api.get('/courses', { params });
        courses.value = response.data.data;
        totalRecords.value = response.data.meta.totalItems;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch courses', life: 5000 });
    } finally {
        loading.value = false;
    }
}

function onPage(event: any) {
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
            const nextFirst = lazyParams.value.first + lazyParams.value.rows;
            if (nextFirst < totalRecords.value) {
                onPage({
                    first: nextFirst,
                    rows: lazyParams.value.rows,
                    page: Math.floor(nextFirst / lazyParams.value.rows)
                });
            }
        } else {
            // Swipe Right -> Prev Page
            const prevFirst = lazyParams.value.first - lazyParams.value.rows;
            if (prevFirst >= 0) {
                onPage({
                    first: prevFirst,
                    rows: lazyParams.value.rows,
                    page: Math.floor(prevFirst / lazyParams.value.rows)
                });
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
    lazyParams.value.startDate = null;
    lazyParams.value.endDate = null;
    onFilter();
}

defineExpose({ refresh: loadLazyData });

function formatDuration(min: number) {
    if (min < 60) return `${min}min`;
    const hours = Math.floor(min / 60);
    const remaining = min % 60;
    return remaining > 0 ? `${hours}h ${remaining}min` : `${hours} hour${hours > 1 ? 's' : ''}`;
}

function confirmDeleteCourse(course: any) {
    const isSeries = !!course.seriesId;

    confirm.require({
        message: isSeries
            ? `Do you want to delete the entire series "${course.title}"? This cannot be undone.`
            : `Delete "${course.title}"? This cannot be undone.`,
        header: isSeries ? 'Series Detected' : 'Dangerous Action',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: {
            label: isSeries ? 'Delete Entire Series' : 'Delete',
            severity: 'danger'
        },
        rejectProps: {
          label: isSeries ? 'Delete Only This' : 'Cancel',
          severity: isSeries ? 'warn' : 'primary',
        },
        accept: async () => {
            try {
                const url = isSeries ? `/courses/${course.id}?deleteAll=true` : `/courses/${course.id}`;
                await api.delete(url);
                toast.add({ severity: 'warn', summary: 'Deleted', detail: isSeries ? 'Series removed' : 'Course removed', life: 5000 });
                loadLazyData();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
            }
        },
        reject: async () => {
            if (isSeries) {
                try {
                    await api.delete(`/courses/${course.id}`);
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Single instance removed', life: 5000 });
                    loadLazyData();
                } catch (e) {
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
                }
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

onMounted(loadLazyData);
</script>

<template>
  <section class="managed-courses-section">
    <div class="section-header mb-6">
      <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h2>Managed Courses</h2>
        <div class="flex flex-wrap items-end gap-3 md:gap-4">
          <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
            <label class="text-[10px] md:text-xs font-bold uppercase text-slate-500">From</label>
            <DatePicker
              v-model="lazyParams.startDate"
              placeholder="Start"
              size="small"
              date-format="dd.mm.yy"
              fluid
              @date-select="onFilter"
            />
          </div>
          <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
            <label class="text-[10px] md:text-xs font-bold uppercase text-slate-500">To</label>
            <DatePicker
              v-model="lazyParams.endDate"
              placeholder="End"
              size="small"
              date-format="dd.mm.yy"
              fluid
              @date-select="onFilter"
            />
          </div>
          <Button
            v-tooltip="'Clear Filters'"
            icon="pi pi-filter-slash"
            variant="text"
            class="h-10 w-10 md:h-8 md:w-8"
            @click="clearFilters"
          />
        </div>
      </div>
    </div>

    <!-- Desktop Table View (Hidden on mobile) -->
    <div class="hidden md:block">
      <DataTable
        v-model:first="lazyParams.first"
        :value="loading ? Array(5).fill({}) : courses"
        lazy
        paginator
        :rows="lazyParams.rows"
        :total-records="totalRecords"
        class="managed-table"
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
            <span
              v-else
              class="course-title-cell"
            >{{ slotProps.data.title }}</span>
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
              <span class="font-bold text-sm">{{ formatDate(slotProps.data.startTime) }}</span>
              <span class="text-xs">{{ formatTime(slotProps.data.startTime) }}</span>
            </div>
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
              height="1.5rem"
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
              <Skeleton
                shape="circle"
                size="2rem"
              />
              <Skeleton
                shape="circle"
                size="2rem"
              />
            </div>
            <div
              v-else
              class="flex justify-end gap-2"
            >
              <Button
                v-tooltip="'Participants'"
                icon="pi pi-users"
                variant="text"
                class="action-btn"
                @click="selectedCourse = slotProps.data; participantsDialog = true"
              />
              <Button
                icon="pi pi-pencil"
                variant="text"
                class="action-btn"
                @click="$emit('edit', slotProps.data)"
              />
              <Button
                icon="pi pi-trash"
                variant="text"
                severity="danger"
                class="action-btn delete-btn"
                @click="confirmDeleteCourse(slotProps.data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Mobile Card View (Hidden on desktop) -->
    <div
      class="md:hidden flex flex-col gap-4 min-h-[500px]"
      @touchstart="handleTouchStart"
      @touchend="handleTouchEnd"
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
            <div class="flex gap-2">
              <Skeleton
                shape="circle"
                size="2.5rem"
              />
              <Skeleton
                shape="circle"
                size="2.5rem"
              />
              <Skeleton
                shape="circle"
                size="2.5rem"
              />
            </div>
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
              <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                <i class="pi pi-calendar text-[10px]" />
                {{ formatDate(course.startTime) }} @ {{ formatTime(course.startTime) }}
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
            <div class="flex gap-2">
              <Button
                icon="pi pi-users"
                rounded
                outlined
                class="!h-10 !w-10 !p-0"
                @click="selectedCourse = course; participantsDialog = true"
              />
              <Button
                icon="pi pi-pencil"
                rounded
                outlined
                class="!h-10 !w-10 !p-0"
                @click="$emit('edit', course)"
              />
              <Button
                icon="pi pi-trash"
                rounded
                outlined
                severity="danger"
                class="!h-10 !w-10 !p-0"
                @click="confirmDeleteCourse(course)"
              />
            </div>
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

      <Paginator
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
        &:hover { @apply bg-slate-50/50; }
    }
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
    &:hover { @apply text-amber-500 bg-amber-50; }

    &.delete-btn:hover { @apply text-red-500 bg-red-50; }
}

.mobile-paginator {
    :deep(.p-paginator-page), :deep(.p-paginator-next), :deep(.p-paginator-last), :deep(.p-paginator-prev), :deep(.p-paginator-first) {
      @apply #{"!min-w-[32px] !h-8 !text-xs"};
    }
}
</style>
