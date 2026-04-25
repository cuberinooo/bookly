<script setup lang="ts">
import { computed, ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import api from '../services/api';
import ParticipantsDialog from './ParticipantsDialog.vue';

const props = defineProps<{
    courses: any[];
}>();

const emit = defineEmits(['edit', 'refresh']);

const confirm = useConfirm();
const toast = useToast();

const participantsDialog = ref(false);
const selectedCourse = ref<any>(null);

// Sorting logic: earliest course first
const sortedCourses = computed(() => {
    return [...props.courses].sort((a, b) => {
        return new Date(a.startTime).getTime() - new Date(b.startTime).getTime();
    });
});

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
        // In PrimeVue, 'accept' is for the primary action (Delete Series)
        // and 'reject' is for the secondary action (Delete Only This or Cancel)
        // However, for single courses we want 'reject' to be Cancel.
        // Let's use a custom approach or handle 'reject' carefully.
        accept: async () => {
            try {
                const url = isSeries ? `/courses/${course.id}?deleteAll=true` : `/courses/${course.id}`;
                await api.delete(url);
                toast.add({ severity: 'warn', summary: 'Deleted', detail: isSeries ? 'Series removed' : 'Course removed', life: 3000 });
                emit('refresh');
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete' });
            }
        },
        reject: async () => {
            if (isSeries) {
                try {
                    await api.delete(`/courses/${course.id}`);
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Single course removed', life: 3000 });
                    emit('refresh');
                } catch (e) {
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete' });
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
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Participant removed', life: 3000 });
                emit('refresh');
                // We might need to refresh the selectedCourse if the dialog is open
                // Since selectedCourse is just a ref to an object in the prop,
                // the parent needs to fetch data and we might need to re-find it.
                participantsDialog.value = false;
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to remove participant' });
            }
        }
    });
}
</script>

<template>
    <section class="managed-courses-section">
        <div class="section-header">
            <h2>Managed Courses</h2>
        </div>

        <DataTable :value="sortedCourses" responsiveLayout="stack" breakpoint="960px" class="mt-4 managed-table">
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
                    {{ formatDuration(slotProps.data.durationMinutes) }}
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
