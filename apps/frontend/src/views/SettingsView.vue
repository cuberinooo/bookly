<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { useAuthStore } from '../store/useAuthStore';
import { useI18n } from 'vue-i18n';
import TrainerSettingsForm from '../components/TrainerSettingsForm.vue';
import LegalSettingsForm from '../components/LegalSettingsForm.vue';
import CompanySettingsForm from '../components/CompanySettingsForm.vue';
import MailSettingsTab from '../components/MailSettingsTab.vue';
import SmtpSettingsTab from '../components/SmtpSettingsTab.vue';

const { t } = useI18n();
const authStore = useAuthStore();

const showOnboardingWarning = ref(false);
const STORAGE_KEY = 'settings_warning_dismissed';

onMounted(() => {
    if (authStore.isAdmin) {
        const dismissed = localStorage.getItem(STORAGE_KEY);
        if (!dismissed) {
            showOnboardingWarning.value = true;
        }
    }
});

function dismissWarning() {
    localStorage.setItem(STORAGE_KEY, 'true');
    showOnboardingWarning.value = false;
}

const activeTabs = computed(() => {
    const tabs = [];
    if (authStore.isAdmin) {
        tabs.push({ id: 'company', label: t('settings.tabs.identity'), component: CompanySettingsForm });
        tabs.push({ id: 'mail', label: t('settings.tabs.emailTemplates'), component: MailSettingsTab });
        tabs.push({ id: 'smtp', label: t('settings.tabs.smtp'), component: SmtpSettingsTab });
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
            class="mt-4 md:mt-6"
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

    <Dialog
      v-model:visible="showOnboardingWarning"
      :header="t('settings.onboardingWarning.header')"
      :modal="true"
      :closable="true"
      class="w-full max-w-lg"
      @hide="dismissWarning"
    >
      <div class="flex flex-col gap-6 py-4">
        <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-900 text-sm flex gap-3 rounded">
          <i class="pi pi-exclamation-triangle text-xl text-amber-500 flex-shrink-0 mt-0.5" />
          <div>
            <p class="font-bold mb-2 text-base">
              {{ t('settings.onboardingWarning.header') }}
            </p>
            <p class="leading-relaxed text-slate-700">
              {{ t('settings.onboardingWarning.body') }}
            </p>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3 w-full">
          <Button
            :label="t('settings.onboardingWarning.accept')"
            icon="pi pi-check"
            severity="primary"
            class="px-5 py-2.5 font-bold uppercase tracking-wider"
            @click="dismissWarning"
          />
        </div>
      </template>
    </Dialog>
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
