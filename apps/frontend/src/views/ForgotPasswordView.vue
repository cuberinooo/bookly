<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const email = ref('');
const loading = ref(false);
const submitted = ref(false);
const router = useRouter();
const toast = useToast();

async function submit() {
    loading.value = true;
    try {
        await api.post('/forgot-password', { email: email.value });
        submitted.value = true;
    } catch (err: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not process request', life: 5000 });
    } finally {
        loading.value = false;
    }
}
</script>

<template>
  <div class="auth-container">
    <Card class="auth-card">
      <template #title>Reset Password</template>
      <template #content>
        <div v-if="submitted" class="text-center py-4">
            <i class="pi pi-check-circle text-4xl text-green-500 mb-4 block"></i>
            <p class="font-bold">Request received!</p>
            <p class="text-sm text-slate-600 mt-2">If your email is in our system, you will receive a reset link shortly.</p>
            <Button label="Back to Login" text class="mt-4" @click="router.push('/login')" />
        </div>
        <form v-else @submit.prevent="submit" class="flex flex-col gap-4 mt-4">
          <p class="text-sm text-slate-600">Enter your email address and we'll send you a link to reset your password.</p>
          <div class="field">
            <label for="email">Email</label>
            <InputText id="email" v-model="email" type="email" required placeholder="your@email.com" />
          </div>
          <Button severity="primary" type="submit" label="Send Reset Link" :loading="loading" class="mt-2" />
        </form>
      </template>
      <template #footer v-if="!submitted">
        <p class="text-center text-sm">Remembered your password? <RouterLink to="/login" class="text-accent font-bold">Sign In</RouterLink></p>
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
