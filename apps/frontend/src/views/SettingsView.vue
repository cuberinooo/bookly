<script setup lang="ts">
import { authStore } from '../store/auth';
import TrainerSettingsForm from '../components/TrainerSettingsForm.vue';
import UserManagementTab from '../components/UserManagementTab.vue';
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

    <Tabs value="0">
      <TabList>
        <Tab
          v-if="authStore.isAdmin()"
          value="0"
          class="font-barlow font-bold"
        >
          USER MANAGEMENT
        </Tab>
        <Tab
          v-if="authStore.isTrainer()"
          :value="authStore.isAdmin() ? '1' : '0'"
          class="font-barlow font-bold"
        >
          TRAINER PREFERENCES
        </Tab>
        <Tab
          v-if="!authStore.isAdmin() && !authStore.isTrainer()"
          value="0"
          class="font-barlow font-bold"
        >
          PRIVACY
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel
          v-if="authStore.isAdmin()"
          value="0"
        >
          <UserManagementTab />
        </TabPanel>
            
        <TabPanel
          v-if="authStore.isTrainer()"
          :value="authStore.isAdmin() ? '1' : '0'"
        >
          <div class="max-w-4xl mt-6">
            <TrainerSettingsForm />
          </div>
        </TabPanel>

        <TabPanel
          v-if="!authStore.isAdmin() && !authStore.isTrainer()"
          value="0"
        >
          <div class="phoenix-card p-10 text-center mt-6">
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
