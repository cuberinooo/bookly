<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const name = ref('');
const email = ref('');
const password = ref('');
const role = ref();
const loading = ref(false);
const router = useRouter();
const toast = useToast();

const roleOptions = ref([]);

async function fetchRoles() {
    try {
        const response = await api.get('/register/roles');
        roleOptions.value = response.data;
    } catch (err) {
        console.error('Failed to fetch roles', err);
    }
}

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

onMounted(fetchRoles);
</script>

<template>
  <div class="min-h-[80vh] flex items-center justify-center bg-white px-4 py-12">
    <div class="phoenix-card w-full max-w-md">
      <div class="text-center mb-10">
        <h1 class="text-3xl font-extrabold tracking-tight">Join the Phoenix</h1>
        <p class="text-slate-600 mt-2 font-medium">Start your athletic transformation</p>
      </div>

      <form @submit.prevent="register" class="flex flex-col gap-6">
        <div class="flex flex-col">
          <label for="name" class="form-label-base">Full Name</label>
          <InputText
            id="name"
            v-model="name"
            required
            placeholder="Coach Carter"
          />
        </div>

        <div class="flex flex-col">
          <label for="email" class="form-label-base">Email Address</label>
          <InputText
            id="email"
            v-model="email"
            type="email"
            required
            placeholder="athlete@phoenix.com"
          />
        </div>

        <div class="flex flex-col">
          <label for="password" class="form-label-base">Password</label>
          <InputText
            id="password"
            v-model="password"
            type="password"
            required
            placeholder="••••••••"
          />
        </div>

        <div class="flex flex-col">
          <label for="role" class="form-label-base">Account Type</label>
          <Select
            v-model="role"
            :options="roleOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>

        <Button
          type="submit"
          severity="primary"
          label="Create Account"
          :loading="loading"
          class="btn-primary w-full py-4 text-lg"
        />
      </form>

      <div class="mt-8 pt-6 border-t border-slate-50 text-center">
        <p class="font-medium text-slate-600">
          Already an athlete?
          <RouterLink to="/login" class="text-accent hover:brightness-90 font-bold underline-offset-4 hover:underline transition-all">Login here</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.p-select-label) {
  color: unset;
}
/* No component-level styling needed; everything is global */
</style>
