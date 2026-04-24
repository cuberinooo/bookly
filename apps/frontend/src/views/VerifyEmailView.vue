<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const verifying = ref(true);
const error = ref(false);

async function verify() {
    const token = route.query.token as string;
    if (!token) {
        error.value = true;
        verifying.value = false;
        return;
    }

    try {
        await api.post('/verify-email', { token });
        toast.add({ severity: 'success', summary: 'Verified', detail: 'Email verified! You can now login.', life: 5000 });
        router.push({ name: 'login' });
    } catch (err) {
        error.value = true;
    } finally {
        verifying.value = false;
    }
}

onMounted(verify);
</script>

<template>
  <div class="auth-container">
    <Card class="auth-card text-center">
      <template #title>Account Verification</template>
      <template #content>
        <div v-if="verifying" class="flex flex-col items-center gap-4 py-8">
            <i class="pi pi-spin pi-spinner text-4xl text-amber-500"></i>
            <p>Verifying your email address...</p>
        </div>
        <div v-else-if="error" class="flex flex-col items-center gap-4 py-8">
            <i class="pi pi-times-circle text-4xl text-red-500"></i>
            <p class="text-red-600 font-bold">Verification failed.</p>
            <p class="text-sm">The link may be invalid or expired.</p>
            <Button label="Back to Login" text @click="router.push('/login')" />
        </div>
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
