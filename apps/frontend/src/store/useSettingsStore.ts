import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';
import { useAuthStore } from './useAuthStore';

export const useSettingsStore = defineStore('settings', () => {
  const companyName = ref('BooklyFit');
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
  const homepageUrl = ref('');
  const companyLogoPath = ref('');
  const initialized = ref(false);

  const companyLogoUrl = computed(() => {
    if (companyLogoPath.value) {
      const authStore = useAuthStore();
      const tokenParam = authStore.token ? `&token=${encodeURIComponent(authStore.token)}` : '';
      const apiBaseUrl = import.meta.env.VITE_API_URL.replace(/\/api$/, '');
      return `${apiBaseUrl}/uploads/${companyLogoPath.value}?t=${encodeURIComponent(companyLogoPath.value)}${tokenParam}`;
    }
    return '';
  });

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
      homepageUrl.value = adminResponse.data.homepageUrl || '';
      companyLogoPath.value = adminResponse.data.companyLogoPath || '';
    } catch (e) {
      console.error('Failed to fetch global settings', e);
    } finally {
      initialized.value = true;
    }
  }

  function reset() {
    companyName.value = 'BooklyFit';
    showParticipantNames.value = true;
    isWaitlistVisible.value = true;
    bookingWindow.value = 'OFF';
    welcomeMailMarkdown.value = '';
    welcomeMailAttachments.value = [];
    homepageUrl.value = '';
    companyLogoPath.value = '';
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
    homepageUrl,
    companyLogoPath,
    companyLogoUrl,
    initialized,
    fetchSettings,
    reset
  };
});
