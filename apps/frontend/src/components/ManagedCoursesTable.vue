<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import api from '../services/api';
import ParticipantsDialog from './ParticipantsDialog.vue';

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
            ? `Do you want to delete only this instance or all upcoming workouts in this series?`
            : `Delete "${course.title}"? This cannot be undone.`,
        header: isSeries ? 'Series Detected' : 'Dangerous Action',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: {
            label: isSeries ? 'Delete Series' : 'Delete',
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
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Single course removed', life: 5000 });
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
            <div class="flex justify-between items-center">
                <h2>Managed Courses</h2>
                <div class="flex gap-4 items-end">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase text-slate-500">From</label>
                        <DatePicker v-model="lazyParams.startDate" @date-select="onFilter" placeholder="Start Date" size="small" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase text-slate-500">To</label>
                        <DatePicker v-model="lazyParams.endDate" @date-select="onFilter" placeholder="End Date" size="small" />
                    </div>
                    <Button icon="pi pi-filter-slash" variant="text" @click="clearFilters" v-tooltip="'Clear Filters'" />
                </div>
            </div>
        </div>

        <DataTable
            :value="courses"
            lazy
            paginator
            :rows="10"
            :totalRecords="totalRecords"
            :loading="loading"
            @page="onPage"
            responsiveLayout="stack"
            breakpoint="960px"
            class="managed-table"
        >
            <Column field="title" header="Course">
                <template #body="slotProps">
                    <span class="course-title-cell">{{ slotProps.data.title }}</span>
                </template>
            </Column>
            <Column header="Schedule">
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="font-bold text-sm">{{ new Date(slotProps.data.startTime).toLocaleDateString() }}</span>
                        <span class="text-xs">{{ new Date(slotProps.data.startTime).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) }}</span>
                    </div>
                </template>
            </Column>
            <Column header="Duration">
                <template #body="slotProps">
                  <span>
                    {{ formatDuration(slotProps.data.durationMinutes) }}
                  </span>
                </template>
            </Column>
            <Column header="Slots">
                <template #body="slotProps">
                    <div class="flex items-center gap-2">
                        <span :class="['slot-badge', { 'is-full': slotProps.data.bookings.length >= slotProps.data.capacity }]">
                            {{ slotProps.data.bookings.length }} / {{ slotProps.data.capacity }}
                        </span>
                    </div>
                </template>
            </Column>
            <Column header="Actions" class="text-right">
                <template #body="slotProps">
                    <div class="flex justify-end gap-2">
                        <Button icon="pi pi-users" variant="text" @click="selectedCourse = slotProps.data; participantsDialog = true" v-tooltip="'Participants'" class="action-btn" />
                        <Button icon="pi pi-pencil" variant="text" @click="$emit('edit', slotProps.data)" class="action-btn" />
                        <Button icon="pi pi-trash" variant="text" severity="danger" @click="confirmDeleteCourse(slotProps.data)" class="action-btn delete-btn" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <ParticipantsDialog
            v-model:visible="participantsDialog"
            :course="selectedCourse"
            @remove-participant="removeParticipant"
        />
    </section>
</template>

<style lang="scss" scoped>
.managed-courses-section {
    @apply bg-white p-10 rounded-2xl border border-slate-200 shadow-sm;
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

.participants-table {
    @apply border border-slate-200 rounded-lg overflow-hidden;

    :deep(.p-datatable-thead > tr > th) {
        @apply bg-slate-50 text-slate-600 font-bold text-xs uppercase tracking-widest p-4;
    }
}

.section-title {
    @apply flex items-center text-sm font-black uppercase tracking-tighter text-slate-700 mb-4;
    font-family: 'Barlow Condensed', sans-serif;
}

.waitlist-badge {
    @apply px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs font-black;
    font-family: 'Barlow Condensed', sans-serif;
}

.empty-squad {
    @apply py-12 text-center text-slate-400 flex flex-col items-center;
    p { @apply font-bold uppercase text-sm tracking-tight; font-family: 'Barlow Condensed', sans-serif; }
}
</style>
