<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { CourseFrequency } from '@/app/enums/CourseFrequency';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    course?: any;
    loading?: boolean;
}>();

const emit = defineEmits(['save', 'cancel', 'delete']);

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
      <Select
        id="workoutType"
        v-model="form.title"
        :options="workoutTypes"
        fluid
        class="athletic-input"
      />
    </div>

    <div
      v-if="form.title === 'Other'"
      class="form-group animate-fadein"
    >
      <label for="customTitle">Custom Course Name</label>
      <InputText
        id="customTitle"
        v-model="form.customTitle"
        placeholder="e.g. Spartan Strength"
        fluid
        class="athletic-input"
      />
    </div>

    <div class="form-group">
      <label for="startTime">Scheduled Date & Time</label>
      <DatePicker
        id="startTime"
        v-model="form.startTime"
        show-time
        hour-format="24"
        fluid
        class="athletic-input"
      />
    </div>

    <div class="form-row">
      <div class="form-group flex-1">
        <label for="recurrence">Recurrence</label>
        <Select
          id="recurrence"
          v-model="form.recurrence"
          :options="recurrenceOptions"
          option-label="label"
          option-value="value"
          fluid
          class="athletic-input"
          :disabled="!!course?.id"
        />
        <small
          v-if="course?.id"
          class="text-slate-400 mt-1 block"
        >
          <i class="pi pi-lock text-xs" /> Fixed after creation.
        </small>
      </div>

      <div
        v-if="course?.id"
        class="form-group flex-1"
      >
        <label for="trainer">Head Coach (Transfer)</label>
        <Select
          id="trainer"
          v-model="form.trainerId"
          :options="trainers"
          option-label="name"
          option-value="id"
          placeholder="Select Trainer"
          fluid
          class="athletic-input"
        />
      </div>
    </div>

    <div
      v-if="course?.id && course.seriesId && form.trainerId !== course?.trainer?.id"
      class="form-group animate-fadein"
    >
      <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
        <Checkbox
          v-model="form.transferAll"
          :binary="true"
          input-id="transferAll"
        />
        <label
          for="transferAll"
          class="text-sm font-bold text-amber-900 cursor-pointer"
        >
          Transfer all future workouts in this series
        </label>
      </div>
    </div>

    <div class="form-group">
      <label for="description">Workout Description</label>
      <Textarea
        id="description"
        v-model="form.description"
        rows="4"
        placeholder="What should athletes expect?"
        fluid
        class="athletic-input"
      />
    </div>

    <div class="form-row">
      <div class="form-group flex-1">
        <label for="capacity">Max Capacity</label>
        <InputNumber
          id="capacity"
          v-model="form.capacity"
          show-buttons
          :min="1"
          fluid
          class="athletic-input"
        />
      </div>
      <div class="form-group flex-1">
        <label for="duration">Duration (Minutes)</label>
        <InputNumber
          id="duration"
          v-model="form.durationMinutes"
          show-buttons
          :min="15"
          fluid
          class="athletic-input"
        />
      </div>
    </div>
    <div class="form-actions mt-6">
      <Button
        v-if="course?.id"
        label="Delete"
        severity="danger"
        variant="text"
        :disabled="loading"
        class="mr-auto delete-btn"
        @click="$emit('delete', course)"
      />
      <Button
        label="Cancel"
        severity="primary"
        variant="text"
        :disabled="loading"
        class="cancel-btn"
        @click="$emit('cancel')"
      />
      <Button
        :label="course?.id ? 'Update Workout' : 'Launch Course'"
        severity="primary"
        :loading="loading"
        class="submit-btn"
        @click="handleSubmit"
      />
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

.participation-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px dashed var(--border-color);
}

.divider-text {
    display: flex;
    align-items: center;
    text-transform: uppercase;
    font-size: 0.75rem;
    font-weight: 900;
    color: var(--text-muted);
    letter-spacing: 0.1em;
    margin-bottom: 1.5rem;

    &::after {
        content: "";
        flex: 1;
        height: 1px;
        background: var(--border-color);
        margin-left: 1rem;
    }
}

.participation-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
}

.status-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    color: var(--text-header);

    i { font-size: 1.25rem; color: #94a3b8; }
    &.active i { color: var(--primary-color); }
}

.participation-btn {
    font-weight: 800 !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase;
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
