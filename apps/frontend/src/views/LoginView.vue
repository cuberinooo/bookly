<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';

const email = ref('');
const password = ref('');
const loading = ref(false);
const resending = ref(false);
const showResend = ref(false);
const router = useRouter();
const toast = useToast();

async function login() {
  loading.value = true;
  showResend.value = false;
  try {
    const response = await api.post('/login_check', {
      email: email.value,
      password: password.value,
    });
    authStore.setToken(response.data.token);
    toast.add({ severity: 'success', summary: 'Welcome back!', detail: 'Login successful', life: 5000 });
    router.push({ name: 'home' });
  } catch (err: any) {
    const message = err.response?.data?.message || 'Check your credentials';
    if (message.includes('verified')) {
        showResend.value = true;
    }
    toast.add({ severity: 'error', summary: 'Login Failed', detail: message, life: 5000 });
  } finally {
    loading.value = false;
  }
}

async function resendVerification() {
    resending.value = true;
    try {
        await api.post('/resend-verification', { email: email.value });
        toast.add({ severity: 'info', summary: 'Email Sent', detail: 'A new verification link has been sent to your email.', life: 5000 });
        showResend.value = false;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not resend email', life: 5000 });
    } finally {
        resending.value = false;
    }
}
</script>

<template>
  <div class="auth-container">
    <Card class="auth-card">
      <template #title>
        Login
      </template>
      <template #content>
        <form
          class="flex flex-col gap-4 mt-4"
          @submit.prevent="login"
        >
          <div class="field">
            <label for="email">Email</label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              required
              placeholder="your@email.com"
            />
          </div>
          <div class="field">
            <div class="flex justify-between items-center mb-1">
              <label
                for="password"
                class="mb-0"
              >Password</label>
              <RouterLink
                to="/forgot-password"
                class="text-xs text-accent font-bold uppercase tracking-tight"
              >
                Forgot password?
              </RouterLink>
            </div>
            <InputText
              id="password"
              v-model="password"
              type="password"
              required
            />
          </div>
          <Button
            severity="primary"
            type="submit"
            label="Sign In"
            :loading="loading"
            class="mt-2"
          />
          
          <div
            v-if="showResend"
            class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-2"
          >
            <p class="text-xs text-amber-800 mb-2 font-medium">
              Didn't get the email? We can send it again.
            </p>
            <Button 
              label="Resend Verification Link" 
              size="small" 
              severity="warn" 
              variant="text"
              class="w-full text-xs font-bold" 
              :loading="resending" 
              @click="resendVerification" 
            />
          </div>
        </form>
      </template>
      <template #footer>
        <p class="text-center text-sm">
          Don't have an account? <RouterLink
            to="/register"
            class="text-accent font-bold"
          >
            Register here
          </RouterLink>
        </p>
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
