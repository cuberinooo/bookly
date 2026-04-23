<script setup lang="ts">
import { ref, watch } from 'vue';

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
    durationMinutes: 60
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
            durationMinutes: newVal.durationMinutes
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
    <div class="course-form">
        <div class="form-group mb-4">
            <label for="workoutType">Workout Type</label>
            <Select id="workoutType" v-model="form.title" :options="workoutTypes" fluid />
        </div>

        <div v-if="form.title === 'Other'" class="form-group mb-4 animate-fadein">
            <label for="customTitle">Custom Title</label>
            <InputText id="customTitle" v-model="form.customTitle" placeholder="Enter course name" fluid />
        </div>

        <div class="form-group mb-4">
            <label for="startTime">Date & Time</label>
            <DatePicker id="startTime" v-model="form.startTime" showTime hourFormat="24" fluid />
        </div>

        <div class="form-group mb-4">
            <label for="description">Description (Optional)</label>
            <Textarea id="description" v-model="form.description" rows="3" fluid />
        </div>

        <div class="form-row mb-4">
            <div class="form-group flex-1">
                <label for="capacity">Capacity</label>
                <InputNumber id="capacity" v-model="form.capacity" showButtons :min="1" fluid />
            </div>
            <div class="form-group flex-1">
                <label for="duration">Duration (Min)</label>
                <InputNumber id="duration" v-model="form.durationMinutes" showButtons :min="15" fluid />
            </div>
        </div>

        <div class="form-actions flex justify-content-end gap-2 mt-4">
            <Button label="Cancel" severity="secondary" variant="text" @click="$emit('cancel')" :disabled="loading" />
            <Button :label="course?.id ? 'Save Changes' : 'Create Course'" severity="primary" :loading="loading" @click="handleSubmit" />
        </div>
    </div>
</template>

<style lang="scss">



</style>
