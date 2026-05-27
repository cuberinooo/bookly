<script setup lang="ts">
import { ref, computed } from 'vue';
import { useLeaderboardStore } from '../store/useLeaderboardStore';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';

const props = defineProps<{
  onSuccess?: () => void;
  onCancel?: () => void;
}>();

const leaderboardStore = useLeaderboardStore();
const toast = useToast();

const submitting = ref(false);
const recordForm = ref({
    exerciseName: '',
    weightValue: null as number | null
});

const groupedExercises = computed(() => {
    const groups: Record<string, any[]> = {};
    leaderboardStore.exercises.forEach(ex => {
        if (!groups[ex.category]) {
            groups[ex.category] = [];
        }
        groups[ex.category].push({
            label: ex.name,
            value: ex.name,
            unit: ex.unit || 'kg'
        });
    });

    return Object.keys(groups).map(category => ({
        label: category,
        items: groups[category]
    }));
});

async function submitRecord() {
    if (!recordForm.value.exerciseName || recordForm.value.weightValue === null) {
        toast.add({ severity: 'warn', summary: 'Warning', detail: 'Please fill in all fields', life: 3000 });
        return;
    }

    submitting.value = true;
    try {
        await leaderboardStore.submitRecord(recordForm.value.exerciseName, recordForm.value.weightValue);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Personal best logged successfully', life: 3000 });
        recordForm.value = { exerciseName: '', weightValue: null };
        if (props.onSuccess) props.onSuccess();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to log personal best', life: 3000 });
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
  <div class="space-y-6 pt-4 pb-2">
    <div class="field">
      <label
        for="exercise"
        class="text-slate-300 font-bold mb-2 block uppercase text-xs tracking-widest"
      >Exercise</label>
      <Select
        id="exercise"
        v-model="recordForm.exerciseName"
        :options="groupedExercises"
        option-group-label="label"
        option-group-children="items"
        filter
        :loading="leaderboardStore.loading"
        placeholder="Select or search exercise"
        class="w-full"
      />
    </div>

    <div class="field">
      <label
        for="weight"
        class="text-slate-300 font-bold mb-2 block uppercase text-xs tracking-widest"
      >Weight (kg / reps / time)</label>
      <InputNumber
        id="weight"
        v-model="recordForm.weightValue"
        :min-fraction-digits="0"
        :max-fraction-digits="2"
        placeholder="e.g., 100"
        class="w-full"
      />
    </div>

    <div class="flex justify-end gap-3 mt-8">
      <Button
        label="Cancel"
        severity="secondary"
        text
        class="font-bold uppercase tracking-tight"
        @click="onCancel"
      />
      <Button
        label="Save PB"
        icon="pi pi-check"
        class="p-button-primary font-black uppercase tracking-tight"
        :loading="submitting"
        @click="submitRecord"
      />
    </div>
  </div>
</template>
