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
  <header>
    <nav>
      <RouterLink to="/">Home</RouterLink>
      <RouterLink v-if="authStore.isLoggedIn()" to="/dashboard">Dashboard</RouterLink>
      <RouterLink v-if="!authStore.isLoggedIn()" to="/login">Login</RouterLink>
      <RouterLink v-if="!authStore.isLoggedIn()" to="/register">Register</RouterLink>
      <a v-if="authStore.isLoggedIn()" href="#" @click.prevent="logout">Logout ({{ authStore.user?.name }})</a>
    </nav>
  </header>
  <div class="container">
    <RouterView />
  </div>
</template>

<style scoped lang="scss">
header {
  line-height: 1.5;
  width: 100%;
  background: #333;
  color: white;
  padding: 1rem;
}

nav {
  display: flex;
  gap: 1rem;
  max-width: 1200px;
  margin: 0 auto;
}

nav a {
  color: white;
  text-decoration: none;
  &:hover {
    text-decoration: underline;
  }
}

.container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}
</style>

<style lang="scss">
body {
    margin: 0;
    font-family: Arial, sans-serif;
}
</style>
