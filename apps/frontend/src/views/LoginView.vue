<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../store/useAuthStore';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const email = ref('');
const password = ref('');
const loading = ref(false);
const resending = ref(false);
const showResend = ref(false);
const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

async function login() {
  loading.value = true;
  showResend.value = false;
  try {
    const response = await api.post('/login_check', {
      email: email.value,
      password: password.value,
    });
    authStore.setToken(response.data.token);
    toast.add({ severity: 'success', summary: t('auth.welcomeBack'), detail: t('auth.loginSuccessful'), life: 5000 });
    router.push({ name: 'home' });
  } catch (err: any) {
    const message = err.response?.data?.message || t('auth.invalidCredentials');
    if (message.includes('verified')) {
        showResend.value = true;
    }
    toast.add({ severity: 'error', summary: t('auth.loginFailed'), detail: message, life: 5000 });
  } finally {
    loading.value = false;
  }
}

async function resendVerification() {
    resending.value = true;
    try {
        await api.post('/resend-verification', { email: email.value });
        toast.add({ severity: 'info', summary: t('auth.emailSent'), detail: t('auth.verificationLinkResent'), life: 5000 });
        showResend.value = false;
    } catch (err) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('auth.couldNotResendEmail'), life: 5000 });
    } finally {
        resending.value = false;
    }
}
</script>

<template>
  <div class="auth-container">
    <Card class="auth-card">
      <template #title>
        {{ t('auth.login') }}
      </template>
      <template #content>
        <form
          class="flex flex-col gap-4 mt-4"
          @submit.prevent="login"
        >
          <div class="field">
            <label for="email">{{ t('auth.email') }}</label>
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
              >{{ t('auth.password') }}</label>
              <RouterLink
                to="/forgot-password"
                class="text-xs text-accent font-bold uppercase tracking-tight"
              >
                {{ t('auth.forgotPassword') }}
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
            :label="t('auth.login')"
            :loading="loading"
            class="mt-2"
          />

          <div
            v-if="showResend"
            class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-2"
          >
            <p class="text-xs text-amber-800 mb-2 font-medium">
              {{ t('auth.didNotGetEmail') }}
            </p>
            <Button
              :label="t('auth.resendVerification')"
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
          {{ t('auth.dontHaveAccount') }} <RouterLink
            to="/register"
            class="text-accent font-bold"
          >
            {{ t('auth.registerHere') }}
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
