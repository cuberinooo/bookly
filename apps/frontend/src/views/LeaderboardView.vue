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
    return Object.keys(leaderboardStore.records).filter(ex => 
        (leaderboardStore.records[ex]?.male?.length > 0) || 
        (leaderboardStore.records[ex]?.female?.length > 0) || 
        (leaderboardStore.records[ex]?.other?.length > 0)
    );
});

const monthlyStatsGrouped = computed(() => {
    const stats = leaderboardStore.monthlyStats;
    return {
        male: stats.filter(s => s.gender === 'male'),
        female: stats.filter(s => s.gender === 'female'),
        other: stats.filter(s => s.gender === 'other' || !s.gender)
    };
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

        <div v-else class="space-y-12">
            <!-- Section 1: Monthly Stats & Streaks -->
            <section>
                <h2 class="text-3xl font-bold text-white flex items-center gap-3 mb-6">
                    <i class="pi pi-calendar text-amber-500"></i> Monthly Stats & Streaks
                </h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div v-for="(stats, gender) in monthlyStatsGrouped" :key="gender" v-show="stats.length > 0">
                        <h3 class="text-xl font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i :class="gender === 'male' ? 'pi pi-mars text-blue-400' : (gender === 'female' ? 'pi pi-venus text-pink-400' : 'pi pi-users text-slate-400')"></i>
                            {{ gender }} Rankings
                        </h3>
                        <Card class="bg-slate-800 border border-slate-700 shadow-xl overflow-hidden">
                            <template #content>
                                <DataTable :value="stats" class="p-datatable-sm" responsiveLayout="scroll">
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
                </div>
            </section>

            <!-- Section 2: Personal Bests -->
            <section>
                <h2 class="text-3xl font-bold text-white flex items-center gap-3 mb-6">
                    <i class="pi pi-trophy text-amber-500"></i> Personal Bests (PBs)
                </h2>

                <div v-if="activeExercises.length === 0" class="bg-slate-800 p-12 rounded-2xl border border-slate-700 text-center text-slate-400">
                    <i class="pi pi-inbox text-5xl mb-4 opacity-50"></i>
                    <p class="text-xl">No personal bests logged yet. Be the first!</p>
                </div>

                <div v-else class="space-y-10">
                    <div v-for="ex in activeExercises" :key="ex" class="bg-slate-800/50 p-6 rounded-2xl border border-slate-700">
                        <h3 class="text-2xl font-black text-amber-500 uppercase tracking-tighter mb-6 flex items-center justify-between border-b border-slate-700 pb-4">
                            {{ ex }}
                            <span class="text-sm font-medium text-slate-500 bg-slate-800 px-3 py-1 rounded-full border border-slate-700">{{ leaderboardStore.records[ex].unit }}</span>
                        </h3>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div v-for="gender in ['male', 'female', 'other']" :key="gender" v-show="leaderboardStore.records[ex][gender].length > 0">
                                <h4 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <i :class="gender === 'male' ? 'pi pi-mars text-blue-400' : (gender === 'female' ? 'pi pi-venus text-pink-400' : 'pi pi-users text-slate-400')"></i>
                                    {{ gender }}
                                </h4>
                                <div class="space-y-3">
                                    <div v-for="(record, index) in leaderboardStore.records[ex][gender]" :key="record.userId" class="flex items-center justify-between p-3 rounded-lg" :class="index === 0 ? 'bg-amber-500/10 border border-amber-500/20' : 'bg-slate-700/30'">
                                        <div class="flex items-center gap-3">
                                            <div class="w-6 text-center font-bold" :class="index === 0 ? 'text-amber-400' : (index === 1 ? 'text-slate-300' : (index === 2 ? 'text-amber-700' : 'text-slate-500'))">
                                                {{ index + 1 }}
                                            </div>
                                            <Avatar v-if="record.profilePicture" :image="getProfilePictureUrl(record.userId, record.profilePicture)" shape="circle" size="small" />
                                            <Avatar v-else :label="getInitials(record.name)" shape="circle" size="small" class="bg-slate-600 text-white text-[10px]" />
                                            <div>
                                                <div class="font-bold text-white text-sm" :class="{'text-amber-400': index === 0}">{{ record.name }}</div>
                                                <div class="text-[10px] text-slate-500">{{ new Date(record.dateAchieved).toLocaleDateString() }}</div>
                                            </div>
                                        </div>
                                        <div class="font-black text-white">
                                            {{ record.weightValue }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
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
