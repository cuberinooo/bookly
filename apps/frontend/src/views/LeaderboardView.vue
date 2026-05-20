<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useLeaderboardStore } from '../store/useLeaderboardStore';
import { useToast } from 'primevue/usetoast';

const leaderboardStore = useLeaderboardStore();
const toast = useToast();

const showSubmitDialog = ref(false);
const submitting = ref(false);

const recordForm = ref({
    exerciseName: null,
    weightValue: null as number | null
});

onMounted(async () => {
    try {
        await leaderboardStore.loadAll();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load leaderboard data', life: 3000 });
    }
});

const submitRecord = async () => {
    if (!recordForm.value.exerciseName || !recordForm.value.weightValue) {
        toast.add({ severity: 'warn', summary: 'Validation Error', detail: 'Please fill in all fields', life: 3000 });
        return;
    }

    submitting.value = true;
    try {
        await leaderboardStore.submitRecord(recordForm.value.exerciseName, recordForm.value.weightValue);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Personal best logged successfully', life: 3000 });
        showSubmitDialog.value = false;
        recordForm.value = { exerciseName: null, weightValue: null };
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to log personal best', life: 3000 });
    } finally {
        submitting.value = false;
    }
};

const groupedExercises = computed(() => {
    const groups: Record<string, string[]> = {};
    const rawExercises = leaderboardStore.exercises;

    if (!Array.isArray(rawExercises) || rawExercises.length === 0) {
        return [];
    }

    rawExercises.forEach(ex => {
        let name = '';
        let category = 'General';

        if (ex && typeof ex === 'object') {
            name = ex.name || '';
            category = ex.category || 'General';
        } else if (typeof ex === 'string') {
            name = ex;
        }

        if (!name) return;

        if (!groups[category]) {
            groups[category] = [];
        }
        if (!groups[category].includes(name)) {
            groups[category].push(name);
        }
    });

    return Object.keys(groups).sort().map(category => ({
        label: category,
        items: groups[category].sort((a, b) => a.localeCompare(b))
    }));
});

// Filter out exercises with no records for cleaner display
const activeExercises = computed(() => {
    return Object.keys(leaderboardStore.records).filter(ex => leaderboardStore.records[ex]?.records?.length > 0);
});

// Helper for initials if no avatar
const getInitials = (name: string) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
};

const getProfilePictureUrl = (userId: number, filename: string | null) => {
    if (!userId || !filename) return null;
    return `${import.meta.env.VITE_API_URL}/user/profile-picture/${userId}?t=${filename}`;
};
</script>

<template>
    <div class="container">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-4xl font-extrabold text-white mb-2 tracking-tight">Leaderboard</h1>
                <p class="text-slate-400 text-lg">Push your limits and see how you stack up.</p>
            </div>
            <Button label="Log New PB" icon="pi pi-plus" class="p-button-primary" @click="showSubmitDialog = true" />
        </div>

        <div v-if="leaderboardStore.loading" class="flex justify-center items-center py-20">
            <i class="pi pi-spin pi-spinner text-5xl text-amber-500"></i>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Section 1: Monthly Stats (Left Column) -->
            <div class="lg:col-span-1 space-y-6">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2 border-b border-slate-700 pb-2">
                    <i class="pi pi-calendar text-amber-500"></i> Monthly Stats & Streaks
                </h2>

                <Card class="bg-slate-800 border border-slate-700 shadow-xl overflow-hidden">
                    <template #content>
                        <DataTable :value="leaderboardStore.monthlyStats" class="p-datatable-sm" responsiveLayout="scroll" :rows="10" paginator>
                            <Column header="Athlete">
                                <template #body="slotProps">
                                    <div class="flex items-center gap-3">
                                        <Avatar v-if="slotProps.data.profilePicture" :image="getProfilePictureUrl(slotProps.data.id, slotProps.data.profilePicture)" shape="circle" />
                                        <Avatar v-else :label="getInitials(slotProps.data.name)" shape="circle" class="bg-amber-500 text-slate-900 font-bold" />
                                        <span class="font-semibold text-white">{{ slotProps.data.name }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column field="attendanceCount" header="Att." class="text-center">
                                <template #body="slotProps">
                                    <div class="font-bold text-amber-400">{{ slotProps.data.attendanceCount }}</div>
                                </template>
                            </Column>
                            <Column field="streak" header="Streak" class="text-center">
                                <template #body="slotProps">
                                    <div class="flex items-center justify-center gap-1">
                                        <i v-if="slotProps.data.streak > 0" class="pi pi-bolt text-orange-500"></i>
                                        <span class="font-bold text-white">{{ slotProps.data.streak }}</span>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </template>
                </Card>
            </div>

            <!-- Section 2: PB Board (Right 2 Columns) -->
            <div class="lg:col-span-2 space-y-6">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2 border-b border-slate-700 pb-2">
                    <i class="pi pi-trophy text-amber-500"></i> Personal Bests (PBs)
                </h2>

                <div v-if="activeExercises.length === 0" class="bg-slate-800 p-8 rounded-xl border border-slate-700 text-center text-slate-400">
                    <i class="pi pi-inbox text-4xl mb-4 opacity-50"></i>
                    <p>No personal bests logged yet. Be the first!</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card v-for="ex in activeExercises" :key="ex" class="bg-slate-800 border border-slate-700 shadow-xl overflow-hidden hover:border-amber-500/50 transition-colors">
                        <template #title>
                            <div class="text-amber-500 font-bold uppercase tracking-wider text-xl border-b border-slate-700 pb-2 mb-2">
                                {{ ex }}
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <div v-for="(record, index) in leaderboardStore.records[ex].records" :key="record.userId" class="flex items-center justify-between p-3 rounded-lg" :class="index === 0 ? 'bg-yellow-300/10 border border-amber-500/20' : 'bg-slate-700/50'">
                                    <div class="flex items-center gap-3">
                                        <div v-if="index === 0" class="text-amber-400 font-bold w-6 text-center">1st</div>
                                        <div v-else-if="index === 1" class="text-slate-300 font-bold w-6 text-center">2nd</div>
                                        <div v-else-if="index === 2" class="text-amber-700 font-bold w-6 text-center">3rd</div>
                                        <div v-else class="text-slate-500 font-bold w-6 text-center">{{ index + 1 }}</div>

                                        <Avatar v-if="record.profilePicture" :image="getProfilePictureUrl(record.userId, record.profilePicture)" shape="circle" size="small" />
                                        <Avatar v-else :label="getInitials(record.name)" shape="circle" size="small" class="bg-slate-600 text-white text-xs" />

                                        <div>
                                            <div class="font-bold text-primary text-sm" :class="{'text-amber-400': index === 0}">{{ record.name }}</div>
                                            <div class="text-xs text-primary text-slate-400">{{ new Date(record.dateAchieved).toLocaleDateString() }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-primary font-extrabold  text-lg">
                                            {{ record.weightValue }}
                                            <span class="text-xs text-primary font-normal text-slate-400">{{ leaderboardStore.records[ex].unit }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

        </div>

        <Dialog v-model:visible="showSubmitDialog" modal header="Log Personal Best" :style="{ width: '400px' }" class="p-fluid">
            <div class="space-y-6 pt-4">
                <div class="field">
                    <label for="exercise" class="text-slate-300 font-bold mb-2 block">Exercise</label>
                    <Select
                        id="exercise"
                        v-model="recordForm.exerciseName"
                        :options="groupedExercises"
                        optionGroupLabel="label"
                        optionGroupChildren="items"
                        filter
                        :loading="leaderboardStore.loading"
                        placeholder="Select or search exercise"
                        class="w-full"
                    />
                </div>

                <div class="field">
                    <label for="weight" class="text-slate-300 font-bold mb-2 block">Weight (kg / reps / time)</label>
                    <InputNumber id="weight" v-model="recordForm.weightValue" inputId="minmaxfraction" :minFractionDigits="0" :maxFractionDigits="2" placeholder="e.g., 100" class="w-full" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 mt-6">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text p-button-secondary" @click="showSubmitDialog = false" />
                    <Button label="Save PB" icon="pi pi-check" class="p-button-primary" @click="submitRecord" :loading="submitting" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
/* Scoped styles to complement Tailwind and PrimeVue */
:deep(.p-card-body) {
    padding: 1.5rem;
}
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: #1e293b;
    color: #94a3b8;
    border-bottom: 1px solid #334155;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}
:deep(.p-datatable .p-datatable-tbody > tr) {
    background: #1e293b;
    border-bottom: 1px solid #334155;
}
:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background: #334155;
}
:deep(.p-select) {
    background: #1e293b;
    border-color: #334155;
}
</style>
