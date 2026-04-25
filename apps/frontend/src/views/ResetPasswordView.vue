<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const password = ref('');
const confirmPassword = ref('');
const loading = ref(false);

async function submit() {
    if (password.value !== confirmPassword.value) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Passwords do not match', life: 5000 });
        return;
    }

    const token = route.query.token as string;
    if (!token) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Invalid or missing token', life: 5000 });
        return;
    }

    loading.value = true;
    try {
        await api.post('/reset-password', { 
            token, 
            password: password.value 
        });
        toast.add({ severity: 'success', summary: 'Success', detail: 'Password updated! Please login with your new password.', life: 5000 });
        router.push({ name: 'login' });
    } catch (err: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Failed to reset password', life: 5000 });
    } finally {
        loading.value = false;
    }
}
</script>

<template>
  <div class="auth-container">
    <Card class="auth-card">
      <template #title>Set New Password</template>
      <template #content>
        <form @submit.prevent="submit" class="flex flex-col gap-4 mt-4">
          <div class="field">
            <label for="password">New Password</label>
            <InputText id="password" v-model="password" type="password" required />
          </div>
          <div class="field">
            <label for="confirmPassword">Confirm Password</label>
            <InputText id="confirmPassword" v-model="confirmPassword" type="password" required />
          </div>
          <Button severity="primary" type="submit" label="Reset Password" :loading="loading" class="mt-2" />
        </form>
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
</style>
