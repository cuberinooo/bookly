<script setup lang="ts">
import { ref, computed } from 'vue';
import { useLeaderboardStore } from '../store/useLeaderboardStore';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';

const { t } = useI18n();
const emit = defineEmits(['success', 'cancel']);

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
        toast.add({ severity: 'warn', summary: t('app.warning'), detail: t('pb.fillFields'), life: 3000 });
        return;
    }

    submitting.value = true;
    try {
        await leaderboardStore.submitRecord(recordForm.value.exerciseName, recordForm.value.weightValue);
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('pb.saveSuccess'), life: 3000 });
        recordForm.value = { exerciseName: '', weightValue: null };
        emit('success');
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('pb.logFailed'), life: 3000 });
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
      >{{ $t('pb.exercise') }}</label>
      <Select
        id="exercise"
        v-model="recordForm.exerciseName"
        :options="groupedExercises"
        option-group-label="label"
        option-group-children="items"
        option-label="label"
        option-value="value"
        filter
        :loading="leaderboardStore.loading"
        :placeholder="$t('pb.exercisePlaceholder')"
        class="w-full"
      />
    </div>

    <div class="field">
      <label
        for="weight"
        class="text-slate-300 font-bold mb-2 block uppercase text-xs tracking-widest"
      >{{ $t('pb.weightRepsTime') }}</label>
      <InputNumber
        id="weight"
        v-model="recordForm.weightValue"
        :min-fraction-digits="0"
        :max-fraction-digits="2"
        :placeholder="$t('pb.weightPlaceholder')"
        class="w-full"
      />
    </div>

    <div class="flex justify-end gap-3 mt-8">
      <Button
        :label="$t('app.cancel')"
        severity="secondary"
        text
        class="font-bold uppercase tracking-tight"
        @click="emit('cancel')"
      />
      <Button
        :label="$t('pb.savePB')"
        icon="pi pi-check"
        class="p-button-primary font-black uppercase tracking-tight"
        :loading="submitting"
        @click="submitRecord"
      />
    </div>
  </div>
</template>
