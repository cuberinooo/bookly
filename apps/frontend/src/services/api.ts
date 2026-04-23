import axios from 'axios';
import { authStore } from '../store/auth';
import router from '../router';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
});

api.interceptors.request.use((config) => {
  const token = authStore.token;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      authStore.logout();
      router.push({ name: 'login' });
    }
    return Promise.reject(error);
  }
);

export default api;
