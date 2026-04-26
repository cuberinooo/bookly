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
  token: localStorage.getItem('token'),
  viewMode: (localStorage.getItem('viewMode') || 'trainer') as 'trainer' | 'member',

  setToken(token: string) {
    this.token = token;
    localStorage.setItem('token', token);
    this.parseUser();
  },

  logout() {
    this.token = null;
    this.user = null;
    localStorage.removeItem('token');
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
    } catch (e) {
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
