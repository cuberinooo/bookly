import { reactive } from 'vue';
import api from '../services/api';


const defaults = {
  companyName: 'Bookly',
  showParticipantNames: true,
  isWaitlistVisible: true,
  bookingWindow: 'OFF',
  initialized: false,
};

export const settingsStore = reactive({
    ...defaults,

    async fetchSettings() {
        try {
            const response = await api.get('/settings');
            const adminResponse = await api.get('/admin-settings');
            this.companyName = adminResponse.data.name;
            this.showParticipantNames = response.data.showParticipantNames;
            this.isWaitlistVisible = response.data.isWaitlistVisible;
            this.bookingWindow = response.data.bookingWindow;
        } catch (e) {
            console.error('Failed to fetch global settings', e);
        } finally {
            this.initialized = true;
        }
    },
  reset() {
    Object.assign(this, defaults);
  }
});
