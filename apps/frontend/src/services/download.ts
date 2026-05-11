import api from "./api";
import {authStore} from "../store/auth";

export async function downloadPrivacyPolicy(companyName?: string) {
  try {
    const urlParams = companyName ? `?companyName=${encodeURIComponent(companyName)}` : '';
    
    const headers: any = {};
    if (authStore.token) {
        headers.Authorization = `Bearer ${authStore.token}`;
    }

    const response = await api.get(`/admin-settings/privacy-policy/download${urlParams}`, {
      responseType: 'blob',
      headers
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

export async function downloadWelcomeAttachment(path: string, fileName: string) {
  try {
    const headers: any = {};
    if (authStore.token) {
        headers.Authorization = `Bearer ${authStore.token}`;
    }

    const response = await api.get(`/admin-settings/welcome-attachment/download`, {
      params: { path },
      responseType: 'blob',
      headers
    });

    const blob = new Blob([response.data], { type: response.headers['content-type'] });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', fileName);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error('Download failed', e);
  }
}
