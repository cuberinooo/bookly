<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '../store/useAuthStore';
import { useI18n } from 'vue-i18n';
import TrainerSettingsForm from '../components/TrainerSettingsForm.vue';
import UserManagementTab from '../components/UserManagementTab.vue';
import LegalSettingsForm from '../components/LegalSettingsForm.vue';
import CompanySettingsForm from '../components/CompanySettingsForm.vue';
import MailSettingsTab from '../components/MailSettingsTab.vue';

const { t } = useI18n();
const authStore = useAuthStore();

const activeTabs = computed(() => {
    const tabs = [];
    if (authStore.isAdmin) {
        tabs.push({ id: 'users', label: t('settings.tabs.athletes'), component: UserManagementTab });
        tabs.push({ id: 'company', label: t('settings.tabs.identity'), component: CompanySettingsForm });
        tabs.push({ id: 'mail', label: t('settings.tabs.emailTemplates'), component: MailSettingsTab });
        tabs.push({ id: 'legal', label: t('settings.tabs.legal'), component: LegalSettingsForm });
    }
    if (authStore.isTrainer) {
        tabs.push({ id: 'system', label: t('settings.tabs.system'), component: TrainerSettingsForm });
    }
    if (!authStore.isAdmin && !authStore.isTrainer) {
        tabs.push({ id: 'privacy', label: t('settings.tabs.privacy'), component: null });
    }
    return tabs;
});
</script>

<template>
  <div class="settings-view max-w-6xl mx-auto py-6 md:py-12 px-2 md:px-4">
    <div class="mb-6 md:mb-10">
      <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">
        {{ t('settings.title') }}
      </h1>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        {{ t('settings.subtitle') }}
      </p>
    </div>

    <Tabs
      :value="activeTabs[0]?.id"
      class="settings-tabs"
    >
      <TabList class="overflow-x-auto whitespace-nowrap scrollbar-hide">
        <Tab
          v-for="tab in activeTabs"
          :key="tab.id"
          :value="tab.id"
          class="font-barlow font-bold text-xs md:text-sm"
        >
          {{ tab.label }}
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel
          v-for="tab in activeTabs"
          :key="tab.id"
          :value="tab.id"
          class="px-0"
        >
          <div
            v-if="tab.component"
            :class="tab.id === 'users' ? '' : 'mt-4 md:mt-6'"
          >
            <component :is="tab.component" />
          </div>

          <div
            v-else-if="tab.id === 'privacy'"
            class="phoenix-card p-6 md:p-10 text-center mt-6"
          >
            <i class="pi pi-shield text-4xl text-slate-300 mb-4" />
            <h2 class="text-lg md:text-xl font-bold text-slate-900 mb-2">
              {{ t('settings.memberPrivacyTitle') }}
            </h2>
            <p class="text-sm md:text-base text-slate-600">
              {{ t('settings.memberPrivacyNote') }}
            </p>
            <Button
              :label="t('settings.goToProfile')"
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

    :deep(.p-tablist-content) {
      border-bottom: 2px solid var(--border-color);
    }
}

.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
  &::-webkit-scrollbar {
    display: none;
  }
}
</style>
