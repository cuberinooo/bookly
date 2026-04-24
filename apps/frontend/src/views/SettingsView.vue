<script setup lang="ts">
import { authStore } from '../store/auth';
import TrainerSettingsForm from '../components/TrainerSettingsForm.vue';
</script>

<template>
  <div class="settings-view max-w-4xl mx-auto py-12 px-4">
    <div class="mb-10">
        <h1 class="text-4xl font-extrabold tracking-tight">System Settings</h1>
        <p class="text-slate-600 mt-2 font-medium">Configure your athletic workspace and privacy preferences</p>
    </div>

    <div v-if="authStore.isTrainer()">
        <TrainerSettingsForm />
    </div>
    
    <div v-else class="phoenix-card p-10 text-center">
        <i class="pi pi-shield text-4xl text-slate-300 mb-4"></i>
        <h2 class="text-xl font-bold text-slate-900 mb-2">Member Privacy</h2>
        <p class="text-slate-600">Your privacy is managed by the course trainers. You can update your personal profile information in the Profile section.</p>
        <Button label="Go to Profile" icon="pi pi-user" @click="$router.push('/profile')" variant="text" class="mt-4" />
    </div>
  </div>
</template>

<style scoped lang="scss">
.settings-view {
    h1 { font-family: 'Barlow Condensed', sans-serif; }
}
</style>
