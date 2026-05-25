import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export interface User {
  id: number;
  email: string;
  name: string;
  roles: string[];
  isActive?: boolean;
  isPublic?: boolean;
  mustChangePassword?: boolean;
  profilePicture?: string;
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const token = ref<string | null>(null);
  const initialized = ref(false);
  const viewMode = ref<'trainer' | 'member'>((localStorage.getItem('viewMode') || 'trainer') as 'trainer' | 'member');

  function setToken(newToken: string) {
    token.value = newToken;
    parseUser();
  }

  async function logout() {
    try {
      const { default: api } = await import('../services/api');
      await api.post('/logout'); // Assuming backend invalidates the cookie here
    } catch (e) {
      console.error('Logout failed', e);
    } finally {
      token.value = null;
      user.value = null;
    }
  }

  async function init() {
    try {
      const { default: api } = await import('../services/api');

      // We no longer read from localStorage or send a body payload.
      // The browser automatically attaches the HTTP-only refresh token cookie.
      const response = await api.post('/token/refresh');

      setToken(response.data.token);
    } catch {
      if (isLoggedIn.value) {
        await logout();
      }
    } finally {
      initialized.value = true;
    }
  }

  function toggleViewMode() {
    viewMode.value = viewMode.value === 'trainer' ? 'member' : 'trainer';
    localStorage.setItem('viewMode', viewMode.value);
  }

  function parseUser() {
    if (!token.value) return;
    try {
      const payload = JSON.parse(atob(token.value.split('.')[1]));
      user.value = {
        id: payload.id,
        email: payload.username || payload.email,
        name: payload.name || '',
        roles: payload.roles || [],
        isActive: payload.isActive,
        isPublic: payload.isPublic,
        mustChangePassword: payload.mustChangePassword,
        profilePicture: payload.profilePicture
      };
    } catch {
      logout();
    }
  }

  const isLoggedIn = computed(() => !!token.value);
  const isTrainer = computed(() => user.value?.roles.includes('ROLE_TRAINER') ?? false);
  const isTrial = computed(() => user.value?.roles.includes('ROLE_TRIAL') ?? false);
  const isAdmin = computed(() => user.value?.roles.includes('ROLE_ADMIN') ?? false);

  // Initial parse if token exists
  if (token.value) {
    parseUser();
  }

  return {
    user,
    token,
    initialized,
    viewMode,
    setToken,
    logout,
    init,
    toggleViewMode,
    parseUser,
    isLoggedIn,
    isTrainer,
    isTrial,
    isAdmin
  };
});
