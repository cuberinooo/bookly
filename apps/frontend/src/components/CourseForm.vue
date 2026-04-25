<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { CourseFrequency } from '@/app/enums/CourseFrequency';
import api from '../services/api';
import {authStore} from "../store/auth";
import {useToast} from "primevue/usetoast";

const props = defineProps<{
    course?: any;
    loading?: boolean;
}>();

const emit = defineEmits(['save', 'book', 'unbook', 'cancel', 'delete']);

const trainers = ref<any[]>([]);
const toast = useToast();
const workoutTypes = ['Functional Training', 'Run Training', 'Team WOD', 'Other'];

const form = ref({
    title: '',
    customTitle: '',
    description: '',
    capacity: 10,
    startTime: new Date(),
    durationMinutes: 60,
    recurrence: CourseFrequency.ONCE,
    trainerId: null as number | null,
    transferAll: false
});

onMounted(async () => {
    try {
        const response = await api.get('/user/trainers');
        trainers.value = response.data;
    } catch (e) {
        console.error('Failed to fetch trainers', e);
    }
});

const recurrenceOptions = computed(() => {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const dayName = days[form.value.startTime.getDay()];

    return [
        { label: 'Einmalig (Once)', value: CourseFrequency.ONCE },
        { label: 'Täglich (Daily)', value: CourseFrequency.DAILY },
        { label: `Jeden ${dayName} (Weekly)`, value: CourseFrequency.WEEKLY },
        { label: 'Montag bis Freitag (Weekdays)', value: CourseFrequency.WEEKDAYS }
    ];
});

watch(() => props.course, (newVal) => {
    if (newVal) {
        const isPreset = workoutTypes.includes(newVal.title) && newVal.title !== 'Other';
        form.value = {
            title: isPreset ? newVal.title : 'Other',
            customTitle: isPreset ? '' : newVal.title,
            description: newVal.description || '',
            capacity: newVal.capacity,
            startTime: new Date(newVal.startTime),
            durationMinutes: newVal.durationMinutes,
            recurrence: CourseFrequency.ONCE, // Default to once on edit
            trainerId: newVal.trainer?.id || null,
            transferAll: false
        };
    }
}, { immediate: true });

async function bookCourse(courseId: number) {
  if (!authStore.isLoggedIn()) {
    toast.add({ severity: 'info', summary: 'Login Required', detail: 'Please login to book a course', life: 5000 });
    return;
  }
  try {
    await api.post(`/courses/${courseId}/book`);
    toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Booking confirmed!', life: 5000 });
    emit('book');
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Booking failed', life: 5000 });
  }
}

async function unbookCourse(courseId: number) {
  try {
    await api.delete(`/courses/${courseId}/book`);
    toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Booking cancelled', life: 5000 });
    emit('unbook');
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to cancel booking', life: 5000 });
  }
}

function handleSubmit() {
    const finalTitle = form.value.title === 'Other' ? form.value.customTitle : form.value.title;
    const payload = {
        ...form.value,
        title: finalTitle,
        startTime: form.value.startTime.toISOString()
    };

    // We emit the transferAll flag separately if it's relevant
    emit('save', payload, form.value.transferAll);
}
</script>

<template>
    <div class="course-form-athletic">
        <div class="form-group">
            <label for="workoutType">Workout Type</label>
            <Select id="workoutType" v-model="form.title" :options="workoutTypes" fluid class="athletic-input" />
        </div>

        <div v-if="form.title === 'Other'" class="form-group animate-fadein">
            <label for="customTitle">Custom Course Name</label>
            <InputText id="customTitle" v-model="form.customTitle" placeholder="e.g. Spartan Strength" fluid class="athletic-input" />
        </div>

        <div class="form-group">
            <label for="startTime">Scheduled Date & Time</label>
            <DatePicker id="startTime" v-model="form.startTime" showTime hourFormat="24" fluid class="athletic-input" />
        </div>

        <div class="form-row">
            <div class="form-group flex-1">
                <label for="recurrence">Recurrence</label>
                <Select
                    id="recurrence"
                    v-model="form.recurrence"
                    :options="recurrenceOptions"
                    optionLabel="label"
                    optionValue="value"
                    fluid
                    class="athletic-input"
                    :disabled="!!course?.id"
                />
                <small v-if="course?.id" class="text-slate-400 mt-1 block">
                    <i class="pi pi-lock text-xs"></i> Fixed after creation.
                </small>
            </div>

            <div v-if="course?.id" class="form-group flex-1">
                <label for="trainer">Head Coach (Transfer)</label>
                <Select
                    id="trainer"
                    v-model="form.trainerId"
                    :options="trainers"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select Trainer"
                    fluid
                    class="athletic-input"
                />
            </div>
        </div>

        <div v-if="course?.id && course.seriesId && form.trainerId !== course.trainer?.id" class="form-group animate-fadein">
            <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <Checkbox v-model="form.transferAll" :binary="true" inputId="transferAll" />
                <label for="transferAll" class="text-sm font-bold text-amber-900 cursor-pointer">
                    Transfer all future workouts in this series
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Workout Description</label>
            <Textarea id="description" v-model="form.description" rows="4" placeholder="What should athletes expect?" fluid class="athletic-input" />
        </div>

        <div class="form-row">
            <div class="form-group flex-1">
                <label for="capacity">Max Capacity</label>
                <InputNumber id="capacity" v-model="form.capacity" showButtons :min="1" fluid class="athletic-input" />
            </div>
            <div class="form-group flex-1">
                <label for="duration">Duration (Minutes)</label>
                <InputNumber id="duration" v-model="form.durationMinutes" showButtons :min="15" fluid class="athletic-input" />
            </div>
        </div>

      <div class="form-actions mt-6" v-if="course.trainer.id !== authStore.user?.id">
        <Button v-if="!course.bookings.some((b: any) => b.member?.id === authStore.user?.id)"
                :label="course.bookings.filter(b => !b.isWaitlist).length < course.capacity ? 'RESERVE SPOT' : 'JOIN WAITLIST'"
                severity="primary" class="w-full p-4" @click="bookCourse(course.id)" />
        <Button v-else label="CANCEL RESERVATION" severity="primary" variant="text" class="w-full p-4 cancel-btn" @click="unbookCourse(course.id)" />
      </div>

        <div class="form-actions mt-6">
            <Button v-if="course?.id" label="Delete" severity="danger" variant="text" @click="$emit('delete', course)" :disabled="loading" class="mr-auto delete-btn" />
            <Button label="Cancel" severity="primary" variant="text" @click="$emit('cancel')" :disabled="loading" class="cancel-btn" />
            <Button :label="course?.id ? 'Update Workout' : 'Launch Course'" severity="primary" :loading="loading" @click="handleSubmit" class="submit-btn" />
        </div>
    </div>
</template>

<style lang="scss" scoped>
.course-form-athletic {
    padding: 1rem 0;
}

.form-row {
    display: flex;
    gap: 2rem;
}

@media (max-width: 600px) {
    .form-row { flex-direction: column; gap: 0; }
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 1.5rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.delete-btn {
    font-weight: 800 !important;
    letter-spacing: 0.1em !important;
    &:hover { background: #fef2f2 !important; }
}

.submit-btn {
    min-width: 200px;
}

:deep(.p-select-label) {
  color: unset;
}

:deep(.p-button.p-button-secondary) {
  color: unset !important;
}

</style>
