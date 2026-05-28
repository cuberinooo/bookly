<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import CourseForm from './CourseForm.vue';
import ParticipantsDialog from './ParticipantsDialog.vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    visible: boolean;
    course: any;
    submitting?: boolean;
}>();

const emit = defineEmits(['update:visible', 'save', 'cancel', 'delete', 'refresh']);

const activeTab = ref(0);
const toast = useToast();

// Reset tab to 0 (Participants) when opening an existing course,
// or to 1 (Edit/Details) when creating a new course.
watch(() => props.visible, (newVal) => {
    if (newVal) {
        activeTab.value = props.course?.id ? 0 : 1;
    }
});

const isNewCourse = computed(() => !props.course?.id);

async function removeParticipant(bookingId: number) {
    try {
        await api.delete(`/courses/${props.course.id}/bookings/${bookingId}`);
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('course.participantRemoved'), life: 5000 });
        emit('refresh');
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('course.removeParticipantFailed'), life: 5000 });
    }
}

function handleSave(formData: any, transferAll: boolean) {
    emit('save', formData, transferAll);
}

function close() {
    emit('update:visible', false);
}
</script>

<template>
  <Dialog
    :visible="visible"
    :header="isNewCourse ? t('course.launchNew') : t('course.manageWorkout', { title: course?.title })"
    :modal="true"
    class="w-full max-w-2xl"
    :class="{ 'max-w-lg': isNewCourse }"
    :pt="{ content: { class: 'p-0' } }"
    @update:visible="close"
  >
    <div class="min-h-[400px]">
      <Tabs
        v-model:value="activeTab"
      >
        <TabList class="bg-slate-50 border-b-2 border-slate-200">
          <Tab
            v-if="!isNewCourse"
            :value="0"
            class="font-['Barlow_Condensed'] font-black text-sm tracking-wide py-4 px-6 text-slate-500 hover:bg-slate-100 transition-colors"
          >
            <div class="flex items-center">
              <i class="pi pi-users mr-2" />
              {{ t('course.participants').toUpperCase() }}
            </div>
          </Tab>
          <Tab
            :value="1"
            class="font-['Barlow_Condensed'] font-black text-sm tracking-wide py-4 px-6 text-slate-500 hover:bg-slate-100 transition-colors"
          >
            <div class="flex items-center">
              <i class="pi pi-pencil mr-2" />
              {{ t('course.details').toUpperCase() }}
            </div>
          </Tab>
        </TabList>
        <TabPanels class="p-6">
          <TabPanel
            v-if="!isNewCourse"
            :value="0"
          >
            <ParticipantsDialog
              v-if="course"
              :visible="true"
              :course="course"
              embedded
              @remove-participant="removeParticipant"
            />
          </TabPanel>
          <TabPanel :value="1">
            <CourseForm
              :course="course"
              :loading="submitting"
              @save="handleSave"
              @cancel="close"
              @delete="$emit('delete', course)"
            />
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>
  </Dialog>
</template>

<style lang="scss" scoped>
:deep(.p-tablist-tab-list) {
  border-bottom-width: 0 !important;
  background-color: var(--bg-primary-color) !important;
}

:deep(.p-tabpanels) {
  background-color: var(--bg-primary-color) !important;
}


:deep(.p-tab-active) {
  color: var(--primary-color) !important;
  border-bottom: 3px solid var(--primary-color) !important;
  background-color: var(--bg-primary-color) !important;
}
</style>
