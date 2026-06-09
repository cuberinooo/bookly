<script setup lang="ts">
import { onMounted } from 'vue';
import { usePushNotification } from '../composables/usePushNotification';

const {
    isSupported,
    isSubscribed,
    permission,
    loading,
    error,
    showAddToHomeScreenPrompt,
    checkSubscription,
    subscribe,
    unsubscribe
} = usePushNotification();

onMounted(() => {
    checkSubscription();
});

const handleToggle = async () => {
    if (isSubscribed.value) {
        await unsubscribe();
    } else {
        await subscribe();
    }
};
</script>

<template>
  <div class="settings-card phoenix-card p-6 mt-6 transition-all duration-300 hover:shadow-md border border-slate-100 rounded-xl bg-white">
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
          <i class="pi pi-bell text-2xl" />
        </div>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-base font-bold text-slate-900 m-0">{{ $t('pushNotifications.title') }}</h3>
            <!-- Inline Status Badge -->
            <span v-if="isSupported && !showAddToHomeScreenPrompt"
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold tracking-wider uppercase border"
                  :class="isSubscribed ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200'">
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="isSubscribed ? 'bg-emerald-500' : 'bg-slate-400'" />
              {{ isSubscribed ? $t('pushNotifications.subscribed') : $t('pushNotifications.notSubscribed') }}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-1 mb-0 leading-relaxed max-w-md">
            {{ $t('pushNotifications.subtitle') }}
          </p>
        </div>
      </div>

      <!-- Toggle Switch for supported devices -->
      <div v-if="isSupported && !showAddToHomeScreenPrompt" class="pt-1">
        <ToggleSwitch
          :model-value="isSubscribed"
          :disabled="loading || permission === 'denied'"
          @update:model-value="handleToggle"
        />
      </div>
    </div>

    <!-- UI/UX for unsupported browsers or iOS Safari (Shows Download Prompts) -->
    <div v-if="!isSupported || showAddToHomeScreenPrompt" class="mt-5 pt-4 border-t border-slate-100">
      <div class="flex items-start gap-2 text-slate-600 mb-4 bg-slate-50 p-3.5 rounded-lg border border-slate-100">
        <i class="pi pi-info-circle text-base mt-0.5 text-indigo-500 shrink-0" />
        <span class="text-xs leading-relaxed">
          {{ showAddToHomeScreenPrompt ? $t('pushNotifications.iosPromptTitle') : $t('pushNotifications.notSupported') }}
          {{ $t('pushNotifications.downloadPrompt') }}
        </span>
      </div>
      <div class="flex flex-wrap gap-2.5">
        <a href="#" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold transition-all shadow-sm hover:scale-[1.02] active:scale-[0.98]">
          <i class="pi pi-apple text-base" />
          <span>{{ $t('pushNotifications.appStore') }}</span>
        </a>
        <a href="#" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold transition-all shadow-sm hover:scale-[1.02] active:scale-[0.98]">
          <i class="pi pi-android text-base" />
          <span>{{ $t('pushNotifications.playStore') }}</span>
        </a>
      </div>
    </div>

    <!-- Permission Denied Warning -->
    <div v-else-if="permission === 'denied'" class="mt-4 p-3 bg-rose-50 border border-rose-100 text-rose-950 text-xs rounded-lg flex items-start gap-2.5">
      <i class="pi pi-ban text-base mt-0.5 text-rose-500 shrink-0" />
      <div>
        <span class="font-bold">{{ $t('pushNotifications.deniedTitle') }}</span>
        <p class="mt-1 mb-0 leading-relaxed opacity-85">
          {{ $t('pushNotifications.deniedDesc') }}
        </p>
      </div>
    </div>

    <!-- Error message display -->
    <div v-if="error" class="mt-4 p-3 bg-rose-50 border border-rose-100 text-rose-950 text-xs rounded-lg flex items-center gap-2">
      <i class="pi pi-times-circle text-base text-rose-500 shrink-0" />
      <span>{{ error }}</span>
    </div>
  </div>
</template>
<style scoped>

:deep(.settings-card) {
  padding: unset !important;
}

</style>
