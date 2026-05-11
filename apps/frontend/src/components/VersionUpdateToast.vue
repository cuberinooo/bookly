<script setup lang="ts">
import { useVersionPolling } from '@/services/version-polling';
import Button from 'primevue/button';

/**
 * VersionUpdateToast
 * Displays a non-intrusive notification when a new version of the app is available.
 */
const { isNewVersionAvailable, refreshApp } = useVersionPolling();
</script>

<template>
  <Transition name="slide-up">
    <div v-if="isNewVersionAvailable" class="version-update-toast" role="alert">
      <div class="toast-inner">
        <div class="toast-content">
          <i class="pi pi-info-circle toast-icon" />
          <div class="toast-text">
            <span class="message">A new version of the app is available.</span>
            <span class="sub-message">Please refresh to update.</span>
          </div>
        </div>
        <Button 
          label="Refresh" 
          size="small" 
          severity="primary" 
          icon="pi pi-refresh"
          @click="refreshApp" 
        />
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.version-update-toast {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 10000; /* Above most elements */
  width: 90%;
  max-width: 450px;
}

.toast-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1rem 1.25rem;
  background-color: var(--p-surface-900);
  color: var(--p-surface-0);
  border-radius: var(--p-content-border-radius);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  border: 1px solid var(--p-surface-700);
}

.toast-content {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.toast-icon {
  font-size: 1.25rem;
  color: var(--p-primary-color);
}

.toast-text {
  display: flex;
  flex-direction: column;
}

.message {
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.25;
}

.sub-message {
  font-size: 0.75rem;
  opacity: 0.7;
}

/* Animations */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
  transform: translate(-50%, 100%);
}

@media (max-width: 768px) {
  .version-update-toast {
    bottom: 1rem;
  }
  
  .toast-inner {
    padding: 0.75rem 1rem;
    gap: 1rem;
  }
  
  .sub-message {
    display: none;
  }
}
</style>
