import { reactive } from 'vue';

interface User {
  id: number;
  email: string;
  name: string;
  roles: string[];
  isActive?: boolean;
  mustChangePassword?: boolean;
}

export const authStore = reactive({
  user: null as User | null,
  token: null as string | null,
  initialized: false,
  viewMode: (localStorage.getItem('viewMode') || 'trainer') as 'trainer' | 'member',

  setToken(token: string) {
    this.token = token;
    this.parseUser();
  },

  logout() {
    this.token = null;
    this.user = null;
  },

  async init() {
    // This will be called on app mount to check if we have a valid refresh token cookie
    try {
      // We import api dynamically to avoid circular dependencies if any
      const { default: api } = await import('../services/api');
      const response = await api.post('/token/refresh');
      this.setToken(response.data.token);
    } catch (e) {
      this.logout();
    } finally {
      this.initialized = true;
    }
  },

  toggleViewMode() {
    this.viewMode = this.viewMode === 'trainer' ? 'member' : 'trainer';
    localStorage.setItem('viewMode', this.viewMode);
  },

  parseUser() {
    if (!this.token) return;
    try {
      const payload = JSON.parse(atob(this.token.split('.')[1]));
      this.user = {
        id: payload.id,
        email: payload.username || payload.email,
        name: payload.name || '',
        roles: payload.roles || [],
        isActive: payload.isActive,
        mustChangePassword: payload.mustChangePassword
      };
    } catch {
      this.logout();
    }
  },

  isLoggedIn() {
    return !!this.token;
  },

  isTrainer() {
    return this.user?.roles.includes('ROLE_TRAINER');
  },

  isAdmin() {
    return this.user?.roles.includes('ROLE_ADMIN');
  }
});

authStore.parseUser();
