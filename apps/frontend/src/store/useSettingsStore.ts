import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';

export const useSettingsStore = defineStore('settings', () => {
  const companyName = ref('Bookly');
  const showParticipantNames = ref(true);
  const isWaitlistVisible = ref(true);
  const bookingWindow = ref('OFF');
  const welcomeMailMarkdown = ref('');
  const welcomeMailAttachments = ref<{ path: string; fileName: string }[]>([]);
  const stripeOnboardingComplete = ref(false);
  const stripeAccountId = ref('');
  const stripePriceSetupFeeId = ref('');
  const stripePriceMembershipId = ref('');
  const billingCycleAnchorDay = ref(0);
  const yearlyFeeEnabled = ref(true);
  const paymentEnabled = ref(false);
  const initialized = ref(false);

  async function fetchSettings() {
    try {
      const [response, adminResponse] = await Promise.all([
        api.get('/settings'),
        api.get('/admin-settings')
      ]);

      companyName.value = adminResponse.data.name;
      showParticipantNames.value = response.data.showParticipantNames;
      isWaitlistVisible.value = response.data.isWaitlistVisible;
      bookingWindow.value = response.data.bookingWindow;
      welcomeMailMarkdown.value = adminResponse.data.welcomeMailMarkdown || '';
      welcomeMailAttachments.value = adminResponse.data.welcomeMailAttachments || [];
      stripeOnboardingComplete.value = adminResponse.data.stripeOnboardingComplete || false;
      stripeAccountId.value = adminResponse.data.stripeAccountId || '';
      stripePriceSetupFeeId.value = adminResponse.data.stripePriceSetupFeeId || '';
      stripePriceMembershipId.value = adminResponse.data.stripePriceMembershipId || '';
      billingCycleAnchorDay.value = adminResponse.data.billingCycleAnchorDay || 0;
      yearlyFeeEnabled.value = adminResponse.data.yearlyFeeEnabled ?? true;
      paymentEnabled.value = adminResponse.data.paymentEnabled ?? false;
    } catch (e) {
      console.error('Failed to fetch global settings', e);
    } finally {
      initialized.value = true;
    }
  }

  function reset() {
    companyName.value = 'Bookly';
    showParticipantNames.value = true;
    isWaitlistVisible.value = true;
    bookingWindow.value = 'OFF';
    welcomeMailMarkdown.value = '';
    welcomeMailAttachments.value = [];
    initialized.value = false;
  }

  return {
    companyName,
    showParticipantNames,
    isWaitlistVisible,
    bookingWindow,
    welcomeMailMarkdown,
    welcomeMailAttachments,
    stripeOnboardingComplete,
    stripeAccountId,
    stripePriceSetupFeeId,
    stripePriceMembershipId,
    billingCycleAnchorDay,
    yearlyFeeEnabled,
    paymentEnabled,
    initialized,
    fetchSettings,
    reset
  };
});
