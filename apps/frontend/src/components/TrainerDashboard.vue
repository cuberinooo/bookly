<script setup lang="ts">
import { ref } from 'vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import ManagedCoursesTable from './ManagedCoursesTable.vue';
import TrainingCycleBuilder from './TrainingCycleBuilder.vue';

const emit = defineEmits(['edit-course']);

const courseTable = ref<any>(null);

function refreshTable() {
    courseTable.value?.refresh();
}

defineExpose({ refreshTable });
</script>

<template>
  <div class="trainer-layout-wrapper">
    <Tabs value="0">
      <TabList class="mb-8">
        <Tab
          value="0"
          class="flex items-center gap-2"
        >
          <i class="pi pi-calendar" />
          <span class="font-bold uppercase tracking-tight font-barlow">Course Schedule</span>
        </Tab>
        <Tab
          value="1"
          class="flex items-center gap-2"
        >
          <i class="pi pi-sync" />
          <span class="font-bold uppercase tracking-tight font-barlow">Training Cycles</span>
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="0">
          <div class="main-content">
            <ManagedCoursesTable
              ref="courseTable"
              @edit="$emit('edit-course', $event)"
            />
          </div>
        </TabPanel>
        <TabPanel value="1">
          <TrainingCycleBuilder />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped lang="scss">
.trainer-layout-wrapper {
  /* Add any trainer-specific layout styles here */
}
</style>
