<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePushNotification } from '../composables/usePushNotification';
import { useAuthStore } from '../store/useAuthStore';
import { useI18n } from 'vue-i18n';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

const { t } = useI18n();
const authStore = useAuthStore();
const { isSupported, isSubscribed, showAddToHomeScreenPrompt, subscribe } = usePushNotification();

const visible = ref(false);
const loading = ref(false);

onMounted(() => {
    // Only show if user is logged in, push notifications are supported, not on iOS Safari and not subscribed yet, AND we haven't prompted them yet
    if (
        authStore.isLoggedIn &&
        isSupported.value &&
        !showAddToHomeScreenPrompt.value &&
        !isSubscribed.value
    ) {
        const hasPrompted = localStorage.getItem(`push_prompted_user_${authStore.user?.id}`);
        if (!hasPrompted) {
            // Delay showing slightly for a better user experience
            setTimeout(() => {
                visible.value = true;
            }, 2000);
        }
    }
});

const handleEnable = async () => {
    loading.value = true;
    try {
        await subscribe();
    } catch (err) {
        console.error(err);
    } finally {
        loading.value = false;
        dismiss();
    }
};

const dismiss = () => {
    if (authStore.user?.id) {
        localStorage.setItem(`push_prompted_user_${authStore.user.id}`, 'true');
    }
    visible.value = false;
};
</script>

<template>
  <Dialog
    v-model:visible="visible"
    :modal="true"
    :closable="false"
    class="w-full max-w-md"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <h3 class="text-lg primary-text font-bold m-0">
          {{ $t('pushNotifications.promptModal.title') }}
        </h3>
      </div>
    </template>

    <div class="py-2">
      <p class="text-sm primary-text mb-4 leading-relaxed">
        {{ $t('pushNotifications.promptModal.subtitle') }}
      </p>

      <ul class="space-y-3.5 pl-0 m-0 list-none text-xs text-white">
        <li class="flex items-start gap-2.5">
          <i class="pi pi-check text-emerald-500 mt-0.5 shrink-0" />
          <span>{{ $t('pushNotifications.promptModal.benefit1') }}</span>
        </li>
        <li class="flex items-start gap-2.5">
          <i class="pi pi-check text-emerald-500 mt-0.5 shrink-0" />
          <span>{{ $t('pushNotifications.promptModal.benefit2') }}</span>
        </li>
        <li class="flex items-start gap-2.5">
          <i class="pi pi-check text-emerald-500 mt-0.5 shrink-0" />
          <span>{{ $t('pushNotifications.promptModal.benefit3') }}</span>
        </li>
      </ul>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-3 w-full border-t border-slate-100 pt-3 mt-1">
        <Button
          :label="$t('pushNotifications.promptModal.laterBtn')"
          class="p-button-text p-button-secondary text-xs"
          :disabled="loading"
          @click="dismiss"
        />
        <Button
          :label="$t('pushNotifications.promptModal.enableBtn')"
          icon="pi pi-bell"
          class="p-button-primary text-xs"
          :loading="loading"
          @click="handleEnable"
        />
      </div>
    </template>
  </Dialog>
</template>
