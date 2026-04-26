<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import { authStore } from '../store/auth';
import { useRouter } from 'vue-router';
import { ref, computed } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const router = useRouter();
const menu = ref();
const toast = useToast();

const newPassword = ref('');
const confirmNewPassword = ref('');
const changingPassword = ref(false);

const passwordValidation = computed(() => {
    return {
        minLength: newPassword.value.length >= 8,
        uppercase: /[A-Z]/.test(newPassword.value),
        lowercase: /[a-z]/.test(newPassword.value),
        number: /[0-9]/.test(newPassword.value),
        special: /[^A-Za-z0-9]/.test(newPassword.value),
        match: newPassword.value === confirmNewPassword.value && newPassword.value !== ''
    };
});

const isPasswordFormValid = computed(() => {
    const v = passwordValidation.value;
    return v.minLength && v.uppercase && v.lowercase && v.number && v.special && v.match;
});

async function updatePassword() {
    changingPassword.value = true;
    try {
        await api.post('/user/change-password', { password: newPassword.value });
        toast.add({ severity: 'success', summary: 'Success', detail: 'Password updated successfully', life: 5000 });
        if (authStore.user) {
            authStore.user.mustChangePassword = false;
        }
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.error || 'Failed to update password', life: 5000 });
    } finally {
        changingPassword.value = false;
    }
}

const menuItems = ref([
    {
        label: 'My Account',
        items: [
            { label: 'Profile', icon: 'pi pi-user', command: () => router.push('/profile') },
            { label: 'Dashboard', icon: 'pi pi-th-large', command: () => router.push('/dashboard') },
            { label: 'Settings', icon: 'pi pi-cog', command: () => router.push('/settings') }
        ]
    },
    {
        label: 'Account Action',
        items: [
            { label: 'Logout', icon: 'pi pi-sign-out', command: () => logout() }
        ]
    }
]);

function toggleMenu(event: any) {
    menu.value.toggle(event);
}

function logout() {
  authStore.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <Toast position="bottom-right" />
  <ConfirmDialog />
  <header class="main-header">
    <nav class="nav-container">
      <div class="brand">
        <RouterLink to="/">
          PHOENIX ATHLETICS
        </RouterLink>
      </div>
      <div class="nav-links">
        <template v-if="authStore.isTrainer()">
          <div
            v-tooltip.bottom="authStore.viewMode === 'trainer' ? 'Switch to Member View' : 'Switch to Trainer View'"
            class="mode-switcher"
          >
            <span :class="{ active: authStore.viewMode === 'trainer' }">TRAINER</span>
            <ToggleSwitch
              :model-value="authStore.viewMode === 'member'"
              @update:model-value="authStore.toggleViewMode()"
            />
            <span :class="{ active: authStore.viewMode === 'member' }">MEMBER</span>
          </div>
        </template>
        <RouterLink to="/">
          Courses
        </RouterLink>
        <template v-if="authStore.isLoggedIn()">
          <RouterLink to="/dashboard">
            Dashboard
          </RouterLink>
          <div class="profile-dropdown-wrapper">
            <span
              v-if="authStore.user"
              class="user-name"
            >{{ authStore.user.name }}</span>
            <Button
              type="button"
              icon="pi pi-user"
              severity="secondary"
              rounded
              class="profile-btn"
              @click="toggleMenu"
            />
            <Menu
              ref="menu"
              :model="menuItems"
              :popup="true"
            />
          </div>
        </template>
        <template v-else>
          <RouterLink to="/login">
            Login
          </RouterLink>
          <RouterLink to="/register">
            Register
          </RouterLink>
        </template>
      </div>
    </nav>
  </header>

  <main class="container">
    <RouterView />
  </main>

  <Dialog
    v-if="authStore.user"
    v-model:visible="authStore.user.mustChangePassword"
    header="Action Required: Update Password"
    :modal="true"
    :closable="false"
    class="w-full max-w-md"
  >
    <div class="flex flex-col gap-6 py-4">
      <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-900 text-sm mb-2">
        <p class="font-bold mb-1">
          Security Update Required
        </p>
        <p>Your account was created with a temporary password. Please set a new secure password to continue.</p>
      </div>

      <div class="flex flex-col gap-2">
        <label class="font-bold text-sm uppercase tracking-wider text-slate-500">New Password</label>
        <Password
          v-model="newPassword"
          toggle-mask
          placeholder="••••••••"
          class="w-full"
          input-class="w-full"
        >
          <template #footer>
            <Divider />
            <p class="mt-2 font-bold text-xs uppercase tracking-wider">
              Requirements
            </p>
            <ul class="pl-2 ml-2 mt-2 list-disc flex flex-col gap-1 text-xs">
              <li :class="passwordValidation.minLength ? 'text-green-600' : 'text-slate-400'">
                At least 8 characters
              </li>
              <li :class="passwordValidation.uppercase ? 'text-green-600' : 'text-slate-400'">
                At least one uppercase
              </li>
              <li :class="passwordValidation.lowercase ? 'text-green-600' : 'text-slate-400'">
                At least one lowercase
              </li>
              <li :class="passwordValidation.number ? 'text-green-600' : 'text-slate-400'">
                At least one number
              </li>
              <li :class="passwordValidation.special ? 'text-green-600' : 'text-slate-400'">
                At least one special character
              </li>
            </ul>
          </template>
        </Password>
      </div>

      <div class="flex flex-col gap-2">
        <label class="font-bold text-sm uppercase tracking-wider text-slate-500">Confirm New Password</label>
        <InputText
          v-model="confirmNewPassword"
          type="password"
          placeholder="••••••••"
          :class="{ 'p-invalid': confirmNewPassword && !passwordValidation.match }"
        />
        <small
          v-if="confirmNewPassword && !passwordValidation.match"
          class="text-red-500 font-bold"
        >Passwords do not match</small>
      </div>
    </div>
    <template #footer>
      <Button
        label="Update Password & Continue"
        severity="primary"
        class="w-full py-3"
        :loading="changingPassword"
        :disabled="!isPasswordFormValid"
        @click="updatePassword"
      />
    </template>
  </Dialog>
</template>

<style scoped lang="scss">
.main-header {
  // background-color: #0F172A; // Now handled by global .main-header in styles.scss
  box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.nav-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1400px;
  margin: 0 auto;
}

.brand a {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.75rem;
  font-weight: 900;
  color: white;
  text-decoration: none;
  letter-spacing: 0.15em;
  transition: color 0.2s;

  &:hover {
    color: var(--primary-color);
  }

  @media (max-width: 768px) {
    font-size: 1.25rem;
    letter-spacing: 0.1em;
  }
}

.nav-links {
  display: flex;
  gap: 2rem;
  align-items: center;

  .mode-switcher {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.4rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-right: 1rem;

    span {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 0.75rem;
      font-weight: 800;
      color: rgba(255, 255, 255, 0.4);
      letter-spacing: 0.05em;
      transition: all 0.2s;

      &.active {
        color: var(--primary-color);
      }
    }

    :deep(.p-toggleswitch) {
      width: 2.5rem;
      height: 1.25rem;

      .p-toggleswitch-slider {
        background: rgba(255, 255, 255, 0.2);
        &:before {
          width: 0.85rem;
          height: 0.85rem;
          margin-top: -0.425rem;
        }
      }

      &.p-toggleswitch-checked .p-toggleswitch-slider {
        background: var(--primary-color);
        &:before {
          background: #000;
        }
      }
    }
  }

  .profile-dropdown-wrapper {
      display: flex;
      align-items: center;
      gap: 0.75rem;

      .user-name {
          color: white;
          font-family: 'Barlow Condensed', sans-serif;
          font-weight: 700;
          font-size: 0.9rem;
          text-transform: uppercase;
          letter-spacing: 0.05em;

          @media (max-width: 768px) {
              display: none;
          }
      }
  }

  @media (max-width: 768px) {
    gap: 1rem;
    
    .mode-switcher {
        margin-right: 0;
        padding: 0.4rem 0.75rem;
        span { display: none; }
    }
    
    a {
        font-size: 0.85rem;
    }
  }

  .profile-btn {
      background: rgba(255,255,255,0.1) !important;
      border: 1px solid rgba(255,255,255,0.2) !important;
      color: white !important;
      width: 40px;
      height: 40px;
      &:hover {
          background: var(--primary-color) !important;
          color: #000 !important;
          border-color: var(--primary-color) !important;
      }
  }

  a {
    color: #f8fafc;
    text-decoration: none;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.95rem;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 0.08em;
    padding: 0.5rem 0;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;

    &:hover {
      color: var(--primary-color);
    }

    &.router-link-active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }
  }

  .logout-btn {
    color: #94a3b8 !important;
    font-size: 0.85rem;
    padding: 0.5rem 1rem !important;
    &:hover {
        color: #ef4444 !important;
    }
  }
}
</style>
