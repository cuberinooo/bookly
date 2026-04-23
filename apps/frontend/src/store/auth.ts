import { reactive } from 'vue';

interface User {
  email: string;
  name: string;
  roles: string[];
}

export const authStore = reactive({
  user: null as User | null,
  token: localStorage.getItem('token'),
  
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
  
  parseUser() {
    if (!this.token) return;
    try {
      const payload = JSON.parse(atob(this.token.split('.')[1]));
      this.user = {
        email: payload.username || payload.email,
        name: payload.name || '',
        roles: payload.roles || []
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
  }
});

authStore.parseUser();
