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
  <Toast />
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
          <Button @click="logout" label="Logout" icon="pi pi-sign-out" class="p-button-text p-button-secondary" />
        </template>
        <template v-else>
          <RouterLink to="/login">Login</RouterLink>
          <RouterLink to="/register">Register</RouterLink>
        </template>
      </div>
    </nav>
  </header>

  <div class="container">
    <RouterView />
  </div>
</template>

<style scoped lang="scss">
.main-header {
  background-color: #0F172A;
  color: white;
  padding: 1rem 2rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.nav-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
}

.brand a {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.5rem;
  font-weight: 800;
  color: white;
  text-decoration: none;
  letter-spacing: 0.1em;
}

.nav-links {
  display: flex;
  gap: 1.5rem;
  align-items: center;

  a {
    color: white;
    text-decoration: none;
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.05em;

    &:hover {
      color: #0369A1;
    }

    &.router-link-active {
        color: #0369A1;
        border-bottom: 2px solid #0369A1;
    }
  }
}
</style>

<style lang="scss">
// Global overrides for PrimeVue in our athletic style
.p-button {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600;
    text-transform: uppercase;
}
</style>
