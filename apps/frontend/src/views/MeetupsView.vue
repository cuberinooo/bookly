<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useMeetupStore } from '../store/useMeetupStore';
import { useTimeStore } from '../store/useTimeStore';
import { RsvpStatus } from '../app/enums/RsvpStatus';
import { useI18n } from 'vue-i18n';
import MeetupCard from '../components/MeetupCard.vue';
import MeetupForm from '../components/MeetupForm.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

const { t } = useI18n();
const toast = useToast();
const confirm = useConfirm();
const meetupStore = useMeetupStore();
const timeStore = useTimeStore();

const activeTab = ref<'active' | 'past' | 'joined' | 'cancelled'>('active');
const showCreateDialog = ref(false);
const editingMeetup = ref(null);
const submitting = ref(false);

const loadMeetups = async () => {
  await meetupStore.fetchMeetups(activeTab.value);
};

onMounted(() => {
  timeStore.init();
  loadMeetups();
});

onUnmounted(() => {
  timeStore.destroy();
});

const handleTabChange = () => {
  loadMeetups();
};

const handleRsvp = async (meetupId: number, status: RsvpStatus) => {
  try {
    await meetupStore.rsvp(meetupId, status);
    toast.add({ severity: 'success', summary: t('app.success'), detail: t('app.updated'), life: 3000 });
  } catch (e: any) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: e, life: 5000 });
  }
};

const handleCancelMeetup = (meetupId: number) => {
  confirm.require({
    message: t('meetup.cancelConfirm'),
    header: t('meetup.cancelHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    rejectProps: {
      label: t('app.cancel'),
      severity: 'primary',
    },
    accept: async () => {
      try {
        await meetupStore.cancelMeetup(meetupId);
        toast.add({ severity: 'success', summary: t('meetup.status.cancelled'), detail: t('meetup.status.cancelled'), life: 3000 });
      } catch (e: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: e, life: 5000 });
      }
    }
  });
};

const openCreate = () => {
  editingMeetup.value = null;
  showCreateDialog.value = true;
};

const openEdit = (meetup: any) => {
  editingMeetup.value = meetup;
  showCreateDialog.value = true;
};

const handleSubmit = async (data: any) => {
  submitting.value = true;
  try {
    if (editingMeetup.value) {
      await meetupStore.updateMeetup((editingMeetup.value as any).id, data);
      toast.add({ severity: 'success', summary: t('app.updated'), detail: t('app.updated'), life: 3000 });
    } else {
      await meetupStore.createMeetup(data);
      toast.add({ severity: 'success', summary: t('app.created'), detail: t('app.created'), life: 3000 });
    }
    showCreateDialog.value = false;
  } catch (e: any) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: e, life: 5000 });
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
      <div class="hidden md:block">
        <h1 class="text-3xl font-black mb-2">
          {{ t('meetup.communityMeetups') }}
        </h1>
        <p class="text-slate-500">
          {{ t('meetup.communitySubtitle') }}
        </p>
      </div>
      <Button
        icon="pi pi-plus"
        :label="t('meetup.organize')"
        class="p-button-primary w-full md:w-auto"
        @click="openCreate"
      />
    </div>

    <Tabs
      v-model:value="activeTab"
      class="mb-6"
      @update:value="handleTabChange"
    >
      <TabList>
        <Tab value="active">
          {{ t('meetup.tabs.upcoming') }}
        </Tab>
        <Tab value="joined">
          {{ t('meetup.tabs.myMeetups') }}
        </Tab>
        <Tab value="past">
          {{ t('meetup.tabs.past') }}
        </Tab>
        <Tab value="cancelled">
          {{ t('meetup.tabs.cancelled') }}
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel :value="activeTab">
          <div
            v-if="meetupStore.isLoading"
            class="flex justify-center py-12"
          >
            <ProgressSpinner />
          </div>

          <div
            v-else-if="meetupStore.meetupList.length === 0"
            class="text-center py-12 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200"
          >
            <i class="pi pi-calendar-minus text-4xl text-slate-300 mb-4" />
            <h3 class="text-xl font-bold text-slate-400">
              {{ t('meetup.noMeetupsFound') }}
            </h3>
            <p class="text-slate-400">
              {{ t('meetup.organizePrompt') }}
            </p>
            <Button
              :label="t('meetup.startSomething')"
              icon="pi pi-plus"
              class="p-button-text mt-4"
              @click="openCreate"
            />
          </div>

          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
          >
            <MeetupCard
              v-for="meetup in meetupStore.meetupList"
              :key="meetup.id"
              :meetup="meetup"
              @rsvp="status => handleRsvp(meetup.id, status)"
              @cancel="handleCancelMeetup(meetup.id)"
              @edit="openEdit(meetup)"
            />
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <Dialog
      v-model:visible="showCreateDialog"
      :header="editingMeetup ? t('meetup.editMeetup') : t('meetup.organize')"
      :modal="true"
      :style="{ width: '500px' }"
      class="p-fluid"
    >
      <MeetupForm
        :meetup="editingMeetup || undefined"
        :loading="submitting"
        @submit="handleSubmit"
        @cancel="showCreateDialog = false"
      />
    </Dialog>
  </div>
</template>

<style scoped lang="scss">
.container {
  max-width: 1200px;
}
</style>
