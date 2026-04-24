<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import { authStore } from '../store/auth';
import { useRouter } from 'vue-router';

const router = useRouter();

function logout() {
  authStore.logout();
  router.push({ name: 'home' });
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
        <RouterLink to="/">Courses</RouterLink>
        <template v-if="authStore.isLoggedIn()">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
          <RouterLink to="/profile">Profile</RouterLink>
          <Button @click="logout" label="Logout" icon="pi pi-sign-out" variant="text" class="logout-btn" />
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
