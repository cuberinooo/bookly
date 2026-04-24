<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';

const email = ref('');
const password = ref('');
const loading = ref(false);
const router = useRouter();
const toast = useToast();

async function login() {
  loading.value = true;
  try {
    const response = await api.post('/login_check', {
      email: email.value,
      password: password.value,
    });
    authStore.setToken(response.data.token);
    toast.add({ severity: 'success', summary: 'Welcome back!', detail: 'Login successful', life: 3000 });
    router.push({ name: 'home' });
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Login Failed', detail: err.response?.data?.message || 'Check your credentials', life: 3000 });
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="auth-container">
    <Card class="auth-card">
      <template #title>Login</template>
      <template #content>
        <form @submit.prevent="login" class="flex flex-col gap-4 mt-4">
          <div class="field">
            <label for="email">Email</label>
            <InputText id="email" v-model="email" type="email" required placeholder="your@email.com" />
          </div>
          <div class="field">
            <label for="password">Password</label>
            <InputText id="password" v-model="password" type="password" required />
          </div>
          <Button severity="primary" type="submit" label="Sign In" :loading="loading" class="mt-2" />
        </form>
      </template>
      <template #footer>
        <p class="text-center text-sm">Don't have an account? <RouterLink to="/register" class="text-accent font-bold">Register here</RouterLink></p>
      </template>
    </Card>
  </div>
</template>

<style scoped lang="scss">
.auth-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 160px);
}
.auth-card {
    width: 100%;
    max-width: 400px;
}
.text-accent {
    color: var(--accent-color);
}
</style>
