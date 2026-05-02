<script setup lang="ts">
import { computed } from 'vue';
import { authStore } from '../store/auth';
import TrainerSettingsForm from '../components/TrainerSettingsForm.vue';
import UserManagementTab from '../components/UserManagementTab.vue';
import LegalSettingsForm from '../components/LegalSettingsForm.vue';
import CompanySettingsForm from '../components/CompanySettingsForm.vue';
import WelcomeMailSettingsTab from '../components/WelcomeMailSettingsTab.vue';

const activeTabs = computed(() => {
    const tabs = [];
    if (authStore.isAdmin()) {
        tabs.push({ id: 'users', label: 'ATHLETES', component: UserManagementTab });
        tabs.push({ id: 'company', label: 'IDENTITY', component: CompanySettingsForm });
        tabs.push({ id: 'welcome', label: 'WELCOME MAIL', component: WelcomeMailSettingsTab });
        tabs.push({ id: 'legal', label: 'LEGAL & COMPLIANCE', component: LegalSettingsForm });
    }
    if (authStore.isTrainer()) {
        tabs.push({ id: 'system', label: 'SYSTEM & ALERTS', component: TrainerSettingsForm });
    }
    if (!authStore.isAdmin() && !authStore.isTrainer()) {
        tabs.push({ id: 'privacy', label: 'PRIVACY', component: null });
    }
    return tabs;
});
</script>

<template>
  <div class="settings-view max-w-6xl mx-auto py-12 px-4">
    <div class="mb-10">
      <h1 class="text-4xl font-extrabold tracking-tight">
        System Settings
      </h1>
      <p class="text-slate-600 mt-2 font-medium">
        Configure your athletic workspace and system preferences
      </p>
    </div>

    <Tabs :value="activeTabs[0]?.id">
      <TabList>
        <Tab
          v-for="tab in activeTabs"
          :key="tab.id"
          :value="tab.id"
          class="font-barlow font-bold"
        >
          {{ tab.label }}
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel
          v-for="tab in activeTabs"
          :key="tab.id"
          :value="tab.id"
        >
          <div
            v-if="tab.component"
            :class="tab.id === 'users' ? '' : 'max-w-4xl mt-6'"
          >
            <component :is="tab.component" />
          </div>

          <div
            v-else-if="tab.id === 'privacy'"
            class="phoenix-card p-10 text-center mt-6"
          >
            <i class="pi pi-shield text-4xl text-slate-300 mb-4" />
            <h2 class="text-xl font-bold text-slate-900 mb-2">
              Member Privacy
            </h2>
            <p class="text-slate-600">
              Your privacy is managed by the course trainers. You can update your personal profile information in the Profile section.
            </p>
            <Button
              label="Go to Profile"
              icon="pi pi-user"
              variant="text"
              class="mt-4"
              @click="$router.push('/profile')"
            />
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped lang="scss">
.settings-view {
    h1 { font-family: 'Barlow Condensed', sans-serif; }
    .font-barlow { font-family: 'Barlow Condensed', sans-serif; }
}
</style>
