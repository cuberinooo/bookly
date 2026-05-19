<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trainingCycleService, TrainingCategory, TrainingCycle } from '../services/training-cycle.service';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ColorPicker from 'primevue/colorpicker';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';
import TrainingCategoryManager from './TrainingCategoryManager.vue';

const toast = useToast();

const categories = ref<TrainingCategory[]>([]);
const cycles = ref<TrainingCycle[]>([]);
const loading = ref(true);

const selectedCategory = ref<TrainingCategory | null>(null);

const cycleName = ref('New Training Cycle');
const cycleStartDate = ref(new Date());
const cycleWeeks = ref(4);
const matrix = ref<Record<string, number>>({}); // Key: "week-day", Value: categoryId

const days = [
    { label: 'Mon', value: 1 },
    { label: 'Tue', value: 2 },
    { label: 'Wed', value: 3 },
    { label: 'Thu', value: 4 },
    { label: 'Fri', value: 5 },
    { label: 'Sat', value: 6 },
    { label: 'Sun', value: 7 }
];

async function loadData() {
    loading.value = true;
    try {
        const [catData, cycleData] = await Promise.all([
            trainingCycleService.getCategories(),
            trainingCycleService.getCycles()
        ]);
        categories.value = catData;
        cycles.value = cycleData;
        
        // Auto-load active cycle into matrix if exists
        const active = cycleData.find(c => c.isActive);
        if (active) {
            cycleName.value = active.name;
            cycleStartDate.value = new Date(active.startDate);
            cycleWeeks.value = active.durationWeeks;
            matrix.value = {};
            active.assignments.forEach(a => {
                matrix.value[`${a.weekNumber}-${a.dayOfWeek}`] = a.category.id;
            });
        }
    } finally {
        loading.value = false;
    }
}

async function addCategory(data: { name: string; colorHex: string }) {
    try {
        await trainingCycleService.createCategory(data);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Category added', life: 3000 });
        await loadData();
    } catch (e) {}
}

async function deleteCategory(id: number) {
    try {
        await trainingCycleService.deleteCategory(id);
        toast.add({ severity: 'info', summary: 'Deleted', detail: 'Category removed', life: 3000 });
        await loadData();
    } catch (e) {}
}

function toggleCell(week: number, day: number) {
    const key = `${week}-${day}`;
    if (!selectedCategory.value) {
        delete matrix.value[key];
        return;
    }
    
    if (matrix.value[key] === selectedCategory.value.id) {
        delete matrix.value[key];
    } else {
        matrix.value[key] = selectedCategory.value.id;
    }
}

function getCellColor(week: number, day: number) {
    const catId = matrix.value[`${week}-${day}`];
    if (!catId) return 'transparent';
    return categories.value.find(c => c.id === catId)?.colorHex || 'transparent';
}

async function saveCycle() {
    const assignments = Object.entries(matrix.value).map(([key, catId]) => {
        const [week, day] = key.split('-').map(Number);
        return { weekNumber: week, dayOfWeek: day, categoryId: catId };
    });

    try {
        await trainingCycleService.createCycle({
            name: cycleName.value,
            startDate: cycleStartDate.value.toISOString().split('T')[0],
            durationWeeks: cycleWeeks.value,
            assignments
        });
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Cycle updated successfully', life: 3000 });
        await loadData();
    } catch (e) {}
}

onMounted(loadData);
</script>

<template>
  <div class="cycle-builder p-4 md:p-6 bg-white rounded-2xl border border-slate-200 shadow-sm">
    <div
      v-if="loading && categories.length === 0"
      class="flex justify-center py-12"
    >
      <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
    </div>

    <div
      v-else
      class="flex flex-col lg:grid lg:grid-cols-[300px_1fr] gap-8"
    >
      <!-- LEFT: Categories Palette -->
      <TrainingCategoryManager
        :categories="categories"
        :selected-category="selectedCategory"
        @add="addCategory"
        @delete="deleteCategory"
        @select="selectedCategory = $event"
      />

      <!-- RIGHT: The Matrix -->
      <main>
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
          <div class="flex flex-col gap-4">
            <h2 class="text-2xl font-black uppercase">
              Training Cycle Matrix
            </h2>
            <div class="flex flex-wrap gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Cycle Name</label>
                <InputText
                  v-model="cycleName"
                  size="small"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Start Date (Week 1)</label>
                <DatePicker
                  v-model="cycleStartDate"
                  size="small"
                  date-format="dd.mm.yy"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Weeks</label>
                <Select
                  v-model="cycleWeeks"
                  :options="[4, 5, 6]"
                  class="!w-24"
                />
              </div>
            </div>
          </div>
          <Button
            label="Save Cycle"
            icon="pi pi-save"
            severity="primary"
            @click="saveCycle"
          />
        </div>

        <div class="matrix-grid-wrapper overflow-x-auto">
          <div class="matrix-grid min-w-[600px]">
            <!-- Header Row -->
            <div class="grid grid-cols-[80px_repeat(7,1fr)] gap-2 mb-2">
              <div class="flex items-center justify-center font-black text-[10px] uppercase text-slate-400">
                Week
              </div>
              <div
                v-for="day in days"
                :key="day.value"
                class="text-center font-black text-xs uppercase py-2 text-slate-500"
              >
                {{ day.label }}
              </div>
            </div>

            <!-- Matrix Rows -->
            <div
              v-for="week in cycleWeeks"
              :key="week"
              class="grid grid-cols-[80px_repeat(7,1fr)] gap-2 mb-2"
            >
              <div class="flex items-center justify-center font-black text-xs bg-slate-100 rounded-lg">
                W{{ week }}
              </div>
              <div 
                v-for="day in 7" 
                :key="day" 
                class="aspect-square rounded-lg border-2 border-slate-100 cursor-pointer transition-all hover:scale-105"
                :style="{ backgroundColor: getCellColor(week, day) }"
                @click="toggleCell(week, day)"
              />
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped lang="scss">
.matrix-grid-wrapper {
  &::-webkit-scrollbar { height: 6px; }
  &::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
}
</style>
