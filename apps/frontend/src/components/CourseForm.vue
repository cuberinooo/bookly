<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { CourseFrequency } from '@/app/enums/CourseFrequency';

const props = defineProps<{
    course?: any;
    loading?: boolean;
}>();

const emit = defineEmits(['save', 'cancel']);

const workoutTypes = ['Functional Training', 'Run Training', 'Team WOD', 'Other'];

const form = ref({
    title: '',
    customTitle: '',
    description: '',
    capacity: 10,
    startTime: new Date(),
    durationMinutes: 60,
    recurrence: CourseFrequency.ONCE
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
            recurrence: CourseFrequency.ONCE // Default to once on edit
        };
    }
}, { immediate: true });

function handleSubmit() {
    const finalTitle = form.value.title === 'Other' ? form.value.customTitle : form.value.title;
    emit('save', {
        ...form.value,
        title: finalTitle,
        startTime: form.value.startTime.toISOString()
    });
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

        <div class="form-group">
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
                <i class="pi pi-lock text-xs"></i> Recurrence cannot be changed after creation.
            </small>
            <small v-else-if="form.recurrence !== CourseFrequency.ONCE" class="text-primary mt-1 block">
                <i class="pi pi-info-circle text-xs"></i> Series will be created for the next 6 months.
            </small>
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

        <div class="form-actions mt-6">
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
    gap: 1.5rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
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
