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
    initialized,
    fetchSettings,
    reset
  };
});
