<script setup lang="ts">
import { useAuthStore } from '../store/useAuthStore';

defineProps<{
  description?: string;
}>();

const authStore = useAuthStore();
</script>

<template>
  <div
    v-if="authStore.user?.isActive === false"
    class="p-6 bg-red-50 border-2 border-red-200 rounded-2xl flex items-start gap-4 animate-fadein"
  >
    <div class="p-3 bg-red-100 rounded-xl text-red-600">
      <i class="pi pi-exclamation-triangle text-2xl" />
    </div>
    <div>
      <h3 class="text-lg font-bold text-red-900 mb-1">
        Account Currently Inactive
      </h3>
      <p class="text-red-700 font-medium leading-relaxed">
        <slot>
          {{ description || 'Your account has been set to inactive. You can still participate in meetups and log your personal bests, but you cannot book or unbook any training sessions at this time.' }}
        </slot>
      </p>
    </div>
  </div>
</template>
