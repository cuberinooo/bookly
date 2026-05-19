<script setup lang="ts">
import { ref } from 'vue';
import { TrainingCategory } from '../services/training-cycle.service';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ColorPicker from 'primevue/colorpicker';

const props = defineProps<{
    categories: TrainingCategory[];
    selectedCategory: TrainingCategory | null;
}>();

const emit = defineEmits(['add', 'delete', 'select']);

const newCategoryName = ref('');
const newCategoryColor = ref('ff0000');

function addCategory() {
    if (!newCategoryName.value) return;
    emit('add', {
        name: newCategoryName.value,
        colorHex: `#${newCategoryColor.value}`
    });
    newCategoryName.value = '';
}
</script>

<template>
  <aside class="flex flex-col gap-6">
    <div>
      <h3 class="text-lg font-black uppercase tracking-tight mb-4">
        Focus Categories
      </h3>
      <div class="flex flex-col gap-2 mb-4">
        <div
          v-for="cat in categories"
          :key="cat.id"
          class="flex items-center gap-3 p-2 rounded-lg border-2 cursor-pointer transition-all"
          :class="selectedCategory?.id === cat.id ? 'border-amber-400 bg-amber-50' : 'border-transparent bg-slate-50'"
          @click="$emit('select', cat)"
        >
          <div
            class="w-6 h-6 rounded-full"
            :style="{ backgroundColor: cat.colorHex }"
          />
          <span class="flex-1 font-bold text-sm text-primary">{{ cat.name }}</span>
          <Button
            icon="pi pi-trash"
            variant="text"
            size="small"
            severity="danger"
            @click.stop="$emit('delete', cat.id)"
          />
        </div>

        <div
          class="flex items-center gap-3 p-2 rounded-lg border-2 cursor-pointer transition-all"
          :class="!selectedCategory ? 'border-slate-400 bg-slate-100' : 'border-transparent'"
          @click="$emit('select', null)"
        >
          <i class="pi pi-eraser text-slate-400" />
          <span class="font-bold text-sm text-slate-400">Eraser Tool</span>
        </div>
      </div>

      <div class="p-4 bg-slate-900 rounded-xl">
        <label class="text-[10px] font-black text-amber-400 uppercase mb-2 block">Create New</label>
        <div class="flex flex-col gap-3">
          <InputText
            v-model="newCategoryName"
            placeholder="Category Name"
            size="small"
            class="!bg-slate-800 !text-white !border-slate-700"
          />
          <div class="flex items-center gap-2">
            <ColorPicker v-model="newCategoryColor" />
            <Button
              label="Add"
              size="small"
              class="flex-1"
              severity="primary"
              @click="addCategory"
            />
          </div>
        </div>
      </div>
    </div>
  </aside>
</template>
