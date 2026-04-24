<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const name = ref('');
const email = ref('');
const password = ref('');
const role = ref('ROLE_MEMBER');
const loading = ref(false);
const router = useRouter();
const toast = useToast();

const roleOptions = [
    { label: 'Member', value: 'ROLE_MEMBER' },
    { label: 'Trainer', value: 'ROLE_TRAINER' }
];

async function register() {
  loading.value = true;
  try {
    await api.post('/register', {
      name: name.value,
      email: email.value,
      password: password.value,
      role: role.value,
    });
    toast.add({ severity: 'success', summary: 'Success', detail: 'Account created! Please login.', life: 4000 });
    router.push({ name: 'login' });
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Registration failed', life: 4000 });
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="auth-container">
    <Card class="auth-card">
      <template #title>Create Account</template>
      <template #content>
        <form @submit.prevent="register" class="flex flex-col gap-4 mt-4">
          <div class="field">
            <label for="name">Full Name</label>
            <InputText id="name" v-model="name" required placeholder="John Doe" />
          </div>
          <div class="field">
            <label for="email">Email</label>
            <InputText id="email" v-model="email" type="email" required placeholder="your@email.com" />
          </div>
          <div class="field">
            <label for="password">Password</label>
            <InputText id="password" v-model="password" type="password" required />
          </div>
          <div class="field">
            <label for="role">Account Type</label>
            <Select v-model="role" :options="roleOptions" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <Button type="submit" severity="primary" label="Join the Phoenix" :loading="loading" class="mt-2" />
        </form>
      </template>
      <template #footer>
        <p class="text-center text-sm">Already have an account? <RouterLink to="/login" class="text-accent font-bold">Login here</RouterLink></p>
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
