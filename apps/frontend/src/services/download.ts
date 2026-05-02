import api from "./api";
import {authStore} from "../store/auth";

export async function downloadPrivacyPolicy() {
  try {
    const response = await api.get('/admin-settings/privacy-policy/download', {
      responseType: 'blob',
      headers: {
        // Force the header here just in case the interceptor misses it
        Authorization: `Bearer ${authStore.token}`
      }
    });

    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'privacy-policy.pdf');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error('Download failed', e);
  }
}
