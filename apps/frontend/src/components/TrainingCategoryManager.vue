<script setup lang="ts">
import { ref } from 'vue';
import { trainingCycleService, TrainingCategory } from '../services/training-cycle.service';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import ColorPicker from 'primevue/colorpicker';
import Dialog from 'primevue/dialog';

const props = defineProps<{
    categories: TrainingCategory[];
    selectedCategory: TrainingCategory | null;
}>();

const emit = defineEmits(['add', 'delete', 'select', 'update']);

const showCreateDialog = ref(false);
const showEditDialog = ref(false);

const form = ref({
    id: null as number | null,
    name: '',
    colorHex: 'ffc107',
    description: ''
});

function openCreate() {
    form.value = { id: null, name: '', colorHex: 'ffc107', description: '' };
    showCreateDialog.value = true;
}

function openEdit(cat: TrainingCategory) {
    form.value = {
        id: cat.id,
        name: cat.name,
        colorHex: cat.colorHex.replace('#', ''),
        description: cat.description || ''
    };
    showEditDialog.value = true;
}

function handleSave() {
    if (!form.value.name) return;

    const payload = {
        name: form.value.name,
        colorHex: `#${form.value.colorHex}`,
        description: form.value.description
    };

    if (form.value.id) {
        emit('update', { id: form.value.id, ...payload });
    } else {
        emit('add', payload);
    }

    showCreateDialog.value = false;
    showEditDialog.value = false;
}
</script>

<template>
  <aside class="flex flex-col gap-6">
    <div>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-black uppercase tracking-tight">
          Focus Categories
        </h3>
        <Button
          icon="pi pi-plus"
          size="small"
          severity="primary"
          rounded
          @click="openCreate"
        />
      </div>

      <div class="flex flex-col gap-2 mb-4">
        <div
          v-for="cat in categories"
          :key="cat.id"
          class="group flex items-center gap-3 p-2 rounded-lg border-2 cursor-pointer transition-all"
          :class="selectedCategory?.id === cat.id ? 'border-amber-400 bg-amber-50' : 'border-transparent bg-slate-50'"
          @click="$emit('select', cat)"
        >
          <div
            class="w-6 h-6 rounded-full shadow-sm"
            :style="{ backgroundColor: cat.colorHex }"
          />
          <div class="flex-1 min-w-0">
            <div class="font-bold text-sm truncate">
              {{ cat.name }}
            </div>
            <div
              v-if="cat.description"
              class="text-[10px] text-slate-500 truncate"
            >
              {{ cat.description }}
            </div>
          </div>
          <div class="flex gap-1">
            <Button
              icon="pi pi-pencil"
              variant="text"
              size="small"
              class="!p-1"
              @click.stop="openEdit(cat)"
            />
            <Button
              icon="pi pi-trash"
              variant="text"
              size="small"
              severity="danger"
              class="!p-1"
              @click.stop="$emit('delete', cat.id)"
            />
          </div>
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
    </div>

    <!-- Create/Edit Dialogs -->
    <Dialog
      v-model:visible="showCreateDialog"
      header="Create Category"
      modal
      class="w-full max-w-md"
    >
      <div class="flex flex-col gap-4 py-2">
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase text-slate-500">Name</label>
          <InputText v-model="form.name" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase text-slate-500">Color</label>
          <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-lg">
            <ColorPicker v-model="form.colorHex" />
            <span class="text-xs font-mono text-slate-600">#{{ form.colorHex }}</span>
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase text-slate-500">Description (displayed on course)</label>
          <Textarea
            v-model="form.description"
            rows="3"
            auto-resize
          />
        </div>
        <Button
          severity="primary"
          label="Create Category"
          class="mt-2"
          @click="handleSave"
        />
      </div>
    </Dialog>

    <Dialog
      v-model:visible="showEditDialog"
      header="Edit Category"
      modal
      class="w-full max-w-md"
    >
      <div class="flex flex-col gap-4 py-2">
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase text-slate-500">Name</label>
          <InputText v-model="form.name" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase text-slate-500">Color</label>
          <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-lg">
            <ColorPicker v-model="form.colorHex" />
            <span class="text-xs font-mono text-slate-600">#{{ form.colorHex }}</span>
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase text-slate-500">Description (displayed on course)</label>
          <Textarea
            v-model="form.description"
            rows="3"
            auto-resize
          />
        </div>
        <Button
          severity="primary"
          label="Save Changes"
          class="mt-2"
          @click="handleSave"
        />
      </div>
    </Dialog>
  </aside>
</template>
