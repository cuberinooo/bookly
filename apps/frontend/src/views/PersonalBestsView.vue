<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useLeaderboardStore } from '../store/useLeaderboardStore';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import WorkoutRecordForm from '../components/WorkoutRecordForm.vue';

const leaderboardStore = useLeaderboardStore();
const toast = useToast();
const confirm = useConfirm();

const showSubmitDialog = ref(false);

const personalBests = computed(() => {
    const pbs: Record<string, any> = {};
    leaderboardStore.myRecords.forEach(record => {
        if (!pbs[record.exerciseName] || record.weightValue > pbs[record.exerciseName].weightValue) {
            pbs[record.exerciseName] = record;
        }
    });
    return Object.values(pbs).sort((a, b) => a.exerciseName.localeCompare(b.exerciseName));
});

function confirmDelete(id: number) {
    confirm.require({
        message: 'Delete this record? This will permanently remove it from your history and the leaderboard.',
        header: 'Dangerous Action',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: {
            label: 'Delete',
            severity: 'danger'
        },
        rejectProps: {
            label: 'Cancel',
            severity: 'primary'
        },
        accept: async () => {
            try {
                await leaderboardStore.deleteRecord(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Record removed', life: 3000 });
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete record', life: 3000 });
            }
        }
    });
}

onMounted(() => {
    leaderboardStore.loadAll();
});
</script>

<template>
  <div class="min-h-screen text-white">
    <div class="max-w-7xl mx-auto py-12 px-4 md:px-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-12">
        <div>
          <h1 class="text-5xl font-black italic uppercase tracking-tighter text-white leading-none">
            My <span class="text-amber-500">Personal Bests</span>
          </h1>
          <p class="text-slate-500 font-bold uppercase tracking-widest text-xs mt-3">
            Track your progress and manage your achievements
          </p>
        </div>
        <Button
          label="Log New PB"
          icon="pi pi-plus"
          size="large"
          class="p-button-primary"
          @click="showSubmitDialog = true"
        />
      </div>

      <div
        v-if="leaderboardStore.loading && !leaderboardStore.myRecords.length"
        class="flex justify-center py-20"
      >
        <i class="pi pi-spin pi-spinner text-4xl text-amber-500" />
      </div>

      <div
        v-else
        class="grid grid-cols-1 lg:grid-cols-3 gap-8"
      >
        <!-- Left Column: Current PBs Summary -->
        <div class="lg:col-span-1 flex flex-col gap-6">
          <h2 class="text-xl font-black uppercase tracking-tight flex items-center gap-2 text-slate-400">
            <i class="pi pi-trophy text-amber-500" />
            Current Bests
          </h2>

          <div
            v-if="personalBests.length === 0"
            class="p-8 border-2 border-dashed border-slate-700 rounded-3xl text-center bg-slate-800/50"
          >
            <p class="text-slate-500 font-medium italic">No records yet. Start logging your workouts!</p>
          </div>

          <div
            v-for="pb in personalBests"
            :key="pb.exerciseName"
            class="bg-slate-800 p-6 rounded-3xl border border-slate-700 shadow-sm hover:border-amber-500 transition-colors group"
          >
            <div class="flex justify-between items-start mb-2">
              <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 group-hover:text-amber-500 transition-colors">
                Personal Best
              </span>
              <span class="text-[10px] font-bold text-slate-400 bg-slate-700 px-2 py-0.5 rounded-full border border-slate-600">
                {{ new Date(pb.dateAchieved).toLocaleDateString() }}
              </span>
            </div>
            <div class="flex items-baseline justify-between">
              <h3 class="text-2xl uppercase tracking-tighter !text-white">
                {{ pb.exerciseName }}
              </h3>
              <div class="text-3xl text-amber-500 italic">
                {{ pb.weightValue }}
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Full History Management -->
        <div class="lg:col-span-2 flex flex-col gap-6">
          <h2 class="text-xl font-black uppercase tracking-tight flex items-center gap-2 text-slate-400">
            <i class="pi pi-history text-slate-500" />
            Log History
          </h2>

          <div class="bg-slate-800 rounded-3xl border border-slate-700 shadow-sm overflow-hidden">
            <DataTable
              :value="leaderboardStore.myRecords"
              striped-rows
              responsive-layout="scroll"
              class="p-datatable-sm"
              :paginator="leaderboardStore.myRecords.length > 10"
              :rows="10"
            >
              <Column
                field="dateAchieved"
                header="Date"
                sortable
              >
                <template #body="slotProps">
                  <span class="font-bold text-slate-400 text-xs">
                    {{ new Date(slotProps.data.dateAchieved).toLocaleDateString() }}
                  </span>
                </template>
              </Column>
              <Column
                field="exerciseName"
                header="Exercise"
                sortable
              >
                <template #body="slotProps">
                  <span class="font-black uppercase tracking-tight text-white text-sm">
                    {{ slotProps.data.exerciseName }}
                  </span>
                </template>
              </Column>
              <Column
                field="weightValue"
                header="Score"
                sortable
              >
                <template #body="slotProps">
                  <span class="font-black italic text-amber-500 text-lg">
                    {{ slotProps.data.weightValue }}
                  </span>
                </template>
              </Column>
              <Column
                header="Action"
                class="text-right"
              >
                <template #body="slotProps">
                  <Button
                    icon="pi pi-trash"
                    severity="danger"
                    variant="text"
                    rounded
                    @click="confirmDelete(slotProps.data.id)"
                  />
                </template>
              </Column>
              <template #empty>
                <div class="py-12 text-center text-slate-500 font-medium italic bg-slate-800/50">
                  No logs found.
                </div>
              </template>
            </DataTable>
          </div>
        </div>
      </div>

      <!-- Submit Dialog -->
      <Dialog
        v-model:visible="showSubmitDialog"
        modal
        header="Log Personal Best"
        class="w-full max-w-md dark-dialog"
      >
        <WorkoutRecordForm
          @success="showSubmitDialog = false"
          @cancel="showSubmitDialog = false"
        />
      </Dialog>
    </div>
  </div>
</template>

<style scoped>
:deep(.p-datatable-header) {
    background: transparent;
    border: none;
}

:deep(.p-datatable-thead > tr > th) {
    background: #1e293b;
    color: #94a3b8;
    font-size: 0.75rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 1rem;
    border-bottom: 2px solid #334155;
}

:deep(.p-datatable-tbody > tr) {
    background: #1e293b;
    transition: background-color 0.2s;
}

:deep(.p-datatable-tbody > tr:hover) {
    background-color: #334155 !important;
}

:deep(.p-datatable-tbody > tr > td) {
    padding: 1rem;
    border-bottom: 1px solid #334155;
    color: #e2e8f0;
}

:deep(.p-paginator) {
    background: #1e293b;
    border-top: 1px solid #334155;
    color: #94a3b8;
}

:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-next),
:deep(.p-paginator .p-paginator-last),
:deep(.p-paginator .p-paginator-first),
:deep(.p-paginator .p-paginator-prev) {
    color: #94a3b8;
}

:deep(.p-paginator .p-paginator-page.p-highlight) {
    background: #334155;
    color: white;
}
</style>
