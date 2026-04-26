<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import { authStore } from '../store/auth';
import { useRouter } from 'vue-router';
import { ref } from 'vue';

const router = useRouter();
const menu = ref();

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
        <RouterLink to="/">PHOENIX ATHLETICS</RouterLink>
      </div>
      <div class="nav-links">
        <template v-if="authStore.isTrainer()">
          <div class="mode-switcher" v-tooltip.bottom="authStore.viewMode === 'trainer' ? 'Switch to Member View' : 'Switch to Trainer View'">
            <span :class="{ active: authStore.viewMode === 'trainer' }">TRAINER</span>
            <ToggleSwitch :modelValue="authStore.viewMode === 'member'" @update:modelValue="authStore.toggleViewMode()" />
            <span :class="{ active: authStore.viewMode === 'member' }">MEMBER</span>
          </div>
        </template>
        <RouterLink to="/">Courses</RouterLink>
        <template v-if="authStore.isLoggedIn()">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
          <div class="profile-dropdown-wrapper">
              <Button type="button" @click="toggleMenu" icon="pi pi-user" severity="secondary" rounded class="profile-btn" />
              <Menu ref="menu" :model="menuItems" :popup="true" />
          </div>
        </template>
        <template v-else>
          <RouterLink to="/login">Login</RouterLink>
          <RouterLink to="/register">Register</RouterLink>
        </template>
      </div>
    </nav>
  </header>

  <main class="container">
    <RouterView />
  </main>
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

    :deep(.p-inputswitch) {
      width: 2.5rem;
      height: 1.25rem;

      .p-inputswitch-slider {
        background: rgba(255, 255, 255, 0.2);
        &:before {
          width: 0.85rem;
          height: 0.85rem;
          margin-top: -0.425rem;
        }
      }

      &.p-inputswitch-checked .p-inputswitch-slider {
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
