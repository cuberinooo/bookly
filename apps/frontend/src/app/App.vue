<script setup lang="ts">
import {RouterLink, RouterView} from 'vue-router';
import {authStore} from '../store/auth';
import {settingsStore} from '../store/settings';
import {useRouter} from 'vue-router';
import {ref, computed, onMounted, watch, onUnmounted} from 'vue';
import api from '../services/api';
import {useToast} from 'primevue/usetoast';
import TheFooter from '../components/TheFooter.vue';
import TheMobileNav from '../components/TheMobileNav.vue';
import VersionUpdateToast from '../components/VersionUpdateToast.vue';
import mercureService from '../services/mercure';

const router = useRouter();
const menu = ref();
const toast = useToast();

const newPassword = ref('');
const newPasswordTouched = ref(false);
const confirmNewPassword = ref('');
const changingPassword = ref(false);

window.addEventListener('vite:preloadError', (event) => {
  // Prevent the error from crashing the app completely in the console
  event.preventDefault();

  // Reload the page automatically to fetch the new assets
  window.location.reload();
});

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

const isNewPasswordValid = computed(() => {
  const v = passwordValidation.value;
  return v.minLength && v.uppercase && v.lowercase && v.number && v.special;
});

const isPasswordFormValid = computed(() => {
  return isNewPasswordValid.value && passwordValidation.value.match;
});

const isAppReady = computed(() => {
  // 1. Always wait for Auth to check the session (cookies/refresh token)
  if (!authStore.initialized) return false;

  // 2. If the user is logged in, we MUST also wait for settings
  if (authStore.isLoggedIn()) {
    return settingsStore.initialized;
  }

  // 3. If not logged in, we are ready (to show login/register)
  return true;
});

async function updatePassword() {
  changingPassword.value = true;
  try {
    const response = await api.post('/user/change-password', {password: newPassword.value});
    toast.add({severity: 'success', summary: 'Success', detail: 'Password updated successfully', life: 5000});

    if (response.data.token) {
      authStore.setToken(response.data.token);
    } else if (authStore.user) {
      authStore.user.mustChangePassword = false;
    }
  } catch (e: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: e.response?.data?.error || 'Failed to update password',
      life: 5000
    });
  } finally {
    changingPassword.value = false;
  }
}

const dashboardLabel = computed(() => {
  return authStore.isTrainer() && authStore.viewMode === 'trainer' ? 'Dashboard' : 'My bookings';
});

const menuItems = computed(() => {
  const items = [
    {
      label: 'My Account',
      items: [
        {label: 'Profile', icon: 'pi pi-user', command: () => router.push('/profile')},
        {label: 'Settings', icon: 'pi pi-cog', command: () => router.push('/settings')}
      ]
    }
  ];

  if (authStore.isTrainer()) {
    items[0].items.push({
      label: 'Statistics',
      icon: 'pi pi-chart-bar',
      command: () => router.push('/statistics')
    });
  }

  items.push({
    label: 'Account Action',
    items: [
      {label: 'Logout', icon: 'pi pi-sign-out', command: () => logout()}
    ]
  });

  return items;
});

function toggleMenu(event: any) {
  menu.value.toggle(event);
}
const companyName = computed(() => settingsStore.companyName);

const profilePictureUrl = computed(() => {
  if (authStore.user?.profilePicture) {
    return `${import.meta.env.VITE_API_URL}/user/profile-picture/${authStore.user.id}?t=${authStore.user.profilePicture}`;
  }
  return null;
});

async function logout() {
  await authStore.logout();
  router.push({name: 'login'});
}

watch(
  () => authStore.isLoggedIn(),
  (isLoggedIn) => {
    if (!isLoggedIn) {
      settingsStore.reset();
      mercureService.stop();
    } else {
      settingsStore.fetchSettings();
      mercureService.init();
    }
  }
);

onMounted(async () => {
  // If we found a user, go get their specific company settings
  if (authStore.isLoggedIn()) {
    await settingsStore.fetchSettings();
    mercureService.init();
  }
});

</script>

<template>
  <Toast position="bottom-right" />
  <ConfirmDialog />

  <div
    v-if="!isAppReady"
    class="loading-overlay"
  >
    <div class="spinner" />
    <p class="loading-text">
      {{ companyName }}
    </p>
  </div>

  <div v-else>
    <header class="main-header">
      <nav class="nav-container">
        <div class="brand">
          <RouterLink :to="authStore.isLoggedIn() ? '/' : '/login'">
            {{ companyName }}
          </RouterLink>
        </div>
        <div class="nav-links">
          <RouterLink
            v-if="authStore.isLoggedIn()"
            to="/"
            class="desktop-only"
          >
            Courses
          </RouterLink>
          <template v-if="authStore.isLoggedIn()">
            <RouterLink
              to="/dashboard"
              class="desktop-only"
            >
              {{ dashboardLabel }}
            </RouterLink>
            <RouterLink
              to="/meetups"
              class="desktop-only"
            >
              Meetups
            </RouterLink>
            <div class="profile-dropdown-wrapper">
              <Button
                type="button"
                severity="secondary"
                rounded
                class="profile-btn"
                @click="toggleMenu"
              >
                <img
                  v-if="profilePictureUrl"
                  :src="profilePictureUrl"
                  alt="Profile"
                  class="profile-image-small"
                >
                <i
                  v-else
                  class="pi pi-user"
                />

                <!-- Role Indicator Badge -->
                <div
                  v-if="authStore.isTrainer()"
                  class="role-badge"
                  :class="authStore.viewMode"
                >
                  <i :class="authStore.viewMode === 'trainer' ? 'pi pi-star-fill' : 'pi pi-user'" />
                </div>
              </Button>
              <Menu
                ref="menu"
                :model="menuItems"
                :popup="true"
              >
                <template #start>
                  <div
                    v-if="authStore.user"
                    class="menu-user-info"
                  >
                    <span class="p-2 menu-user-name">{{ authStore.user.name }}</span>
                    <div
                      v-if="authStore.isTrainer()"
                      class="toggle-container"
                    >
                      <ToggleButton
                        on-label="Trainer Mode"
                        off-label="Member Mode"
                        on-icon="pi pi-star-fill"
                        off-icon="pi pi-user"
                        :model-value="authStore.viewMode === 'trainer'"
                        @update:model-value="authStore.toggleViewMode()"
                      />
                    </div>
                  </div>
                </template>
              </Menu>
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
      <router-view v-slot="{ Component }">
        <transition
          name="fade-slide"
          mode="out-in"
        >
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <TheFooter v-if="authStore.isLoggedIn()" />

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
          <label
            for="newPassword"
            class="font-bold text-sm uppercase tracking-wider text-slate-500"
          >New Password</label>
          <Password
            v-model="newPassword"
            input-id="newPassword"
            toggle-mask
            placeholder="••••••••"
            class="w-full"
            input-class="w-full"
            :class="{ 'p-invalid': newPasswordTouched && !isNewPasswordValid }"
            @blur="newPasswordTouched = true"
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
          <ul
            v-if="newPasswordTouched && !isNewPasswordValid"
            class="mt-2 flex flex-col gap-1 text-xs font-bold"
          >
            <li
              v-if="!passwordValidation.minLength"
              class="text-red-500"
            >
              • At least 8 characters
            </li>
            <li
              v-if="!passwordValidation.uppercase"
              class="text-red-500"
            >
              • At least one uppercase
            </li>
            <li
              v-if="!passwordValidation.lowercase"
              class="text-red-500"
            >
              • At least one lowercase
            </li>
            <li
              v-if="!passwordValidation.number"
              class="text-red-500"
            >
              • At least one number
            </li>
            <li
              v-if="!passwordValidation.special"
              class="text-red-500"
            >
              • At least one special character
            </li>
          </ul>
        </div>

        <div class="flex flex-col gap-2">
          <label
            for="confirmNewPassword"
            class="font-bold text-sm uppercase tracking-wider text-slate-500"
          >Confirm New Password</label>
          <InputText
            id="confirmNewPassword"
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
    <VersionUpdateToast />
    <TheMobileNav />
  </div>
</template>

<style scoped lang="scss">
/* Essential: Prevents browser bounce physics from fighting your pull-to-refresh */
html, body, #app {
  overscroll-behavior-y: contain;
}

.desktop-only {
  @media (max-width: 768px) {
    display: none !important;
  }
}

.main-header {
  position: sticky;
  top: 0;
  z-index: 1001;
  background-color: rgba(15, 23, 42, 0.9) !important; // Semi-transparent slate
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.container {
  /* No extra top padding needed if using sticky on the header itself,
     but we ensure the header doesn't overlap content in a jarring way */
}

.nav-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1400px;
  margin: 0 auto;
}

:deep(.p-togglebutton) {
  color: var(--primary-color) !important; /* This should match #ffc107 */
  border-width: 1px !important; /* Make the border slightly thicker to highlight the animation */
  animation: borderGlow 2s infinite ease-in-out !important; /* Smooth, slow infinite loop */
}

@keyframes borderGlow {
  0% {
    border-color: #ffc107; /* Starting color (amber/gold) */
    box-shadow: 0 0 5px rgba(255, 193, 7, 0.5); /* Subtle inner glow */
  }
  50% {
    border-color: #ff9800; /* Midpoint: a warm, deep orange, which complements amber perfectly */
    box-shadow: 0 0 10px rgba(255, 152, 0, 0.7); /* Slightly stronger glow at the peak */
  }
  100% {
    border-color: #ffc107; /* Return to start */
    box-shadow: 0 0 5px rgba(255, 193, 7, 0.5); /* Back to subtle glow */
  }
}
.toggle-container {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
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

  @media (max-width: 768px) {
    gap: 1rem;

    a {
      font-size: 0.85rem;
    }
  }

  .profile-btn {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 16px 16px 0px 16px !important;
    color: white !important;
    width: 40px;
    height: 40px;
    padding: 0 !important;
    overflow: hidden;

    &:hover {
      background: var(--primary-color) !important;
      color: #000 !important;
      border-color: var(--primary-color) !important;
    }

    .profile-image-small {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .role-badge {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 16px;
      height: 16px;
      background: #475569; // Neutral slate
      border: 2px solid #0f172a; // Match header bg
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

      i {
        font-size: 8px;
        color: white;
      }

      &.trainer {
        background: var(--bg-primary-color);
        i { color: white; }
        box-shadow: 0 0 8px rgba(255, 193, 7, 0.4);
      }

      &.member {
        background: var(--bg-primary-color);
        i { color: white; }
      }
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

.menu-user-info {
  padding: 0.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;

  .menu-user-name {
    text-align: center;
    color: #64748b;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 1rem;
  }
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: #0f172a;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  z-index: 9999;

  .spinner {
    width: 50px;
    height: 50px;
    border: 3px solid rgba(255, 255, 255, 0.1);
    border-top: 3px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1.5rem;
  }

  .loading-text {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 1.5rem;
    font-weight: 900;
    color: white;
    letter-spacing: 0.3em;
    animation: pulse 2s ease-in-out infinite;
  }
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Route Transitions */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease-out;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
</style>
