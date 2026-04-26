<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const password = ref('');
const confirmPassword = ref('');
const loading = ref(false);

const passwordValidation = computed(() => {
    return {
        minLength: password.value.length >= 8,
        uppercase: /[A-Z]/.test(password.value),
        lowercase: /[a-z]/.test(password.value),
        number: /[0-9]/.test(password.value),
        special: /[^A-Za-z0-9]/.test(password.value),
        match: password.value === confirmPassword.value && password.value !== ''
    };
});

const isFormValid = computed(() => {
    const v = passwordValidation.value;
    return v.minLength && v.uppercase && v.lowercase && v.number && v.special && v.match;
});

async function submit() {
    if (!isFormValid.value) {
        if (password.value !== confirmPassword.value) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Passwords do not match', life: 5000 });
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Please meet all password requirements', life: 5000 });
        }
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
      <template #title>
        Set New Password
      </template>
      <template #content>
        <form
          class="flex flex-col gap-6 mt-4"
          @submit.prevent="submit"
        >
          <div class="flex flex-col">
            <label
              for="password"
              class="font-bold text-sm mb-2"
            >New Password</label>
            <Password
              id="password"
              v-model="password"
              toggle-mask
              required
              placeholder="••••••••"
              class="w-full"
              input-class="w-full"
            >
              <template #footer>
                <Divider />
                <p class="mt-2 font-bold text-xs uppercase tracking-wider">
                  Requirements
                </p>
                <ul class="pl-2 ml-2 mt-2 list-disc flex flex-col gap-1 text-xs">
                  <li :class="passwordValidation.minLength ? 'text-green-600' : 'text-slate-400'">
                    At least 8 characters
                  </li>
                  <li :class="passwordValidation.uppercase ? 'text-green-600' : 'text-slate-400'">
                    At least one uppercase
                  </li>
                  <li :class="passwordValidation.lowercase ? 'text-green-600' : 'text-slate-400'">
                    At least one lowercase
                  </li>
                  <li :class="passwordValidation.number ? 'text-green-600' : 'text-slate-400'">
                    At least one number
                  </li>
                  <li :class="passwordValidation.special ? 'text-green-600' : 'text-slate-400'">
                    At least one special character
                  </li>
                </ul>
              </template>
            </Password>
          </div>
          <div class="flex flex-col">
            <label
              for="confirmPassword"
              class="font-bold text-sm mb-2"
            >Confirm Password</label>
            <InputText 
              id="confirmPassword" 
              v-model="confirmPassword" 
              type="password" 
              required 
              placeholder="••••••••"
              :class="{ 'p-invalid': confirmPassword && !passwordValidation.match }"
            />
            <small
              v-if="confirmPassword && !passwordValidation.match"
              class="text-red-500 mt-1 font-bold"
            >Passwords do not match</small>
          </div>
          <Button 
            severity="primary" 
            type="submit" 
            label="Reset Password" 
            :loading="loading" 
            :disabled="!isFormValid"
            class="mt-2 py-3 font-bold uppercase tracking-widest text-sm" 
          />
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
