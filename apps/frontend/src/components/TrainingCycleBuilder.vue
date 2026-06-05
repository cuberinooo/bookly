<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { trainingCycleService, TrainingCategory, TrainingCycle } from '../services/training-cycle.service';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ColorPicker from 'primevue/colorpicker';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';
import TrainingCategoryManager from './TrainingCategoryManager.vue';

import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();

const categories = ref<TrainingCategory[]>([]);
const currentCycle = ref<TrainingCycle | null>(null);
const loading = ref(true);

const selectedCategory = ref<TrainingCategory | null>(null);

const cycleStartDate = ref(new Date());
const cycleWeeks = ref(4);
const isActive = ref(true);
const matrix = ref<Record<string, number>>({}); // Key: "week-day", Value: categoryId

const days = [
    { label: t('app.daysShort.mon'), value: 1 },
    { label: t('app.daysShort.tue'), value: 2 },
    { label: t('app.daysShort.wed'), value: 3 },
    { label: t('app.daysShort.thu'), value: 4 },
    { label: t('app.daysShort.fri'), value: 5 },
    { label: t('app.daysShort.sat'), value: 6 },
    { label: t('app.daysShort.sun'), value: 7 }
];

const weekRanges = computed(() => {
    const ranges = [];
    const start = new Date(cycleStartDate.value);
    // Find Monday of that week
    const day = start.getDay();
    const diff = (day === 0 ? 6 : day - 1);
    start.setDate(start.getDate() - diff);

    for (let i = 0; i < cycleWeeks.value; i++) {
        const weekStart = new Date(start);
        weekStart.setDate(start.getDate() + (i * 7));

        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);

        const format = (d: Date) => d.toLocaleDateString(t('app.language') === 'English' ? 'en-GB' : 'de-DE', { day: '2-digit', month: '2-digit' });
        ranges.push(`${format(weekStart)} - ${format(weekEnd)}`);
    }
    return ranges;
});

async function loadData() {
    loading.value = true;
    try {
        const [catData, cycleData] = await Promise.all([
            trainingCycleService.getCategories(),
            trainingCycleService.getCycles()
        ]);
        categories.value = catData;

        if (cycleData.length > 0) {
            const active = cycleData[0]; // Take the only cycle
            currentCycle.value = active;
            cycleStartDate.value = new Date(active.startDate);
            cycleWeeks.value = active.durationWeeks;
            isActive.value = active.isActive;
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
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('admin.cycle.categoryAdded'), life: 3000 });
        await loadData();
    } catch (e) {}
}

async function deleteCategory(id: number) {
    try {
        await trainingCycleService.deleteCategory(id);
        toast.add({ severity: 'info', summary: t('app.deleted'), detail: t('admin.cycle.categoryRemoved'), life: 3000 });
        await loadData();
    } catch (e) {}
}

async function updateCategory(data: { id: number; name?: string; colorHex?: string; description?: string }) {
    try {
        const { id, ...payload } = data;
        await trainingCycleService.updateCategory(id, payload);
        toast.add({ severity: 'success', summary: t('app.updated'), detail: t('admin.cycle.categoryUpdated'), life: 3000 });
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
        await trainingCycleService.saveCycle({
            startDate: cycleStartDate.value.toISOString().split('T')[0],
            durationWeeks: cycleWeeks.value,
            isActive: isActive.value,
            assignments
        });
        toast.add({ severity: 'success', summary: t('app.updated'), detail: t('admin.cycle.cycleSaved'), life: 3000 });
        await loadData();
    } catch (e) {}
}

async function toggleCycleStatus() {
    try {
        await trainingCycleService.toggleStatus(isActive.value);
        toast.add({
            severity: 'info',
            summary: isActive.value ? t('admin.cycle.active') : t('admin.cycle.deactivated'),
            detail: isActive.value ? t('admin.cycle.active') : t('admin.cycle.deactivated'),
            life: 3000
        });
    } catch (e) {
        isActive.value = !isActive.value; // Revert on error
    }
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
        @update="updateCategory"
        @select="selectedCategory = $event"
      />

      <!-- RIGHT: The Matrix -->
      <main>
        <!-- Info Banner -->
        <div class="mb-6 p-5 rounded-xl border border-blue-100 bg-blue-50/50 text-slate-700">
          <h4 class="text-sm font-black uppercase text-blue-800 tracking-wide mb-3 flex items-center gap-2">
            <i class="pi pi-info-circle text-base" />
            {{ $t('admin.cycle.infoTitle') }}
          </h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs leading-relaxed">
            <div class="flex flex-col gap-1">
              <span class="font-bold text-slate-900 uppercase tracking-wider text-[10px]">{{ $t('admin.cycle.infoPaletteTitle') }}</span>
              <p class="text-slate-600">
                {{ $t('admin.cycle.infoPaletteText') }}
              </p>
            </div>
            <div class="flex flex-col gap-1">
              <span class="font-bold text-slate-900 uppercase tracking-wider text-[10px]">{{ $t('admin.cycle.infoImpactTitle') }}</span>
              <p class="text-slate-600">
                {{ $t('admin.cycle.infoImpactText') }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
          <div class="flex flex-col gap-4">
            <h2 class="text-2xl font-black uppercase">
              {{ $t('admin.cycle.matrixTitle') }}
            </h2>
            <div class="flex flex-wrap gap-4 items-center">
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase">{{ $t('admin.cycle.startDate') }}</label>
                <DatePicker
                  v-model="cycleStartDate"
                  size="small"
                  date-format="dd.mm.yy"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase">{{ $t('admin.cycle.weeks') }}</label>
                <Select
                  v-model="cycleWeeks"
                  :options="[4, 5, 6, 8, 12]"
                  class="!w-24"
                />
              </div>
              <div class="flex items-center gap-2 pt-4">
                <ToggleSwitch
                  v-model="isActive"
                  @change="toggleCycleStatus"
                />
                <span class="text-xs font-bold uppercase text-slate-500">
                  {{ isActive ? $t('admin.cycle.active') : $t('admin.cycle.deactivated') }}
                </span>
              </div>
            </div>
          </div>
          <Button
            :label="$t('admin.cycle.saveApply')"
            icon="pi pi-save"
            severity="primary"
            @click="saveCycle"
          />
        </div>

        <div class="matrix-grid-wrapper overflow-x-auto">
          <div class="matrix-grid min-w-[700px]">
            <!-- Header Row -->
            <div class="grid grid-cols-[120px_repeat(7,1fr)] gap-2 mb-2">
              <div class="flex items-center justify-center font-black text-[10px] uppercase text-slate-400">
                {{ $t('admin.cycle.weekRange') }}
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
              v-for="(range, idx) in weekRanges"
              :key="idx"
              class="grid grid-cols-[120px_repeat(7,1fr)] gap-2 mb-2"
            >
              <div class="flex flex-col items-center justify-center bg-slate-100 rounded-lg p-2">
                <span class="font-black text-[10px] uppercase text-slate-400 leading-none mb-1">{{ $t('admin.cycle.week') }} {{ idx + 1 }}</span>
                <span class="font-bold text-[9px] text-slate-600">{{ range }}</span>
              </div>
              <div
                v-for="day in 7"
                :key="day"
                class="aspect-square rounded-lg border-2 border-slate-100 cursor-pointer transition-all hover:scale-105"
                :class="{ 'ring-2 ring-amber-400 ring-offset-1': selectedCategory && matrix[`${idx + 1}-${day}`] === selectedCategory.id }"
                :style="{ backgroundColor: getCellColor(idx + 1, day) }"
                @click="toggleCell(idx + 1, day)"
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
