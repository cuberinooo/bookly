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
  <div class="settings-card phoenix-card p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600">
          <i class="pi pi-bell text-xl" />
        </div>
        <div>
          <h3 class="text-lg font-bold text-slate-900 m-0">
            {{ $t('pushNotifications.title') }}
          </h3>
          <p class="text-sm text-slate-500 m-0">
            {{ $t('pushNotifications.subtitle') }}
          </p>
        </div>
      </div>
      <div v-if="isSupported && !showAddToHomeScreenPrompt">
        <ToggleSwitch
          :model-value="isSubscribed"
          :disabled="loading || permission === 'denied'"
          @update:model-value="handleToggle"
        />
      </div>
    </div>

    <!-- Push Notification Status/Hints -->
    <div
      v-if="!isSupported"
      class="p-3 bg-amber-50 border-l-4 border-amber-500 text-amber-900 text-sm rounded"
    >
      <div class="flex items-center gap-2">
        <i class="pi pi-exclamation-triangle" />
        <span>{{ $t('pushNotifications.notSupported') }}</span>
      </div>
    </div>

    <div
      v-else-if="showAddToHomeScreenPrompt"
      class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-900 rounded"
    >
      <div class="flex items-start gap-3">
        <i class="pi pi-info-circle text-lg mt-0.5" />
        <div>
          <h4 class="font-bold text-sm mb-1">
            {{ $t('pushNotifications.iosPromptTitle') }}
          </h4>
          <p class="text-xs leading-relaxed mb-3">
            {{ $t('pushNotifications.iosPromptIntro') }}
          </p>
          <ol class="list-decimal list-inside text-xs space-y-1 pl-1">
            <li>Tap the <strong>Share</strong> button in Safari (at the bottom or top of the screen).</li>
            <li>Scroll down the share menu and select <strong>"Add to Home Screen"</strong>.</li>
            <li>Launch BooklyFit from your Home Screen and return to this settings screen to enable notifications.</li>
          </ol>
        </div>
      </div>
    </div>

    <div
      v-else-if="permission === 'denied'"
      class="p-3 bg-red-50 border-l-4 border-red-500 text-red-900 text-sm rounded"
    >
      <div class="flex items-start gap-2">
        <i class="pi pi-ban text-lg mt-0.5" />
        <div>
          <span class="font-bold">{{ $t('pushNotifications.deniedTitle') }}</span>
          <p class="text-xs mt-1">
            {{ $t('pushNotifications.deniedDesc') }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-else
      class="flex items-center gap-2 mt-2"
    >
      <span
        class="inline-block w-2.5 h-2.5 rounded-full"
        :class="isSubscribed ? 'bg-green-500' : 'bg-slate-300'"
      />
      <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
        {{ isSubscribed ? $t('pushNotifications.subscribed') : $t('pushNotifications.notSubscribed') }}
      </span>
    </div>

    <div
      v-if="error"
      class="p-3 bg-red-50 border-l-4 border-red-500 text-red-900 text-sm rounded mt-4"
    >
      <div class="flex items-center gap-2">
        <i class="pi pi-times-circle" />
        <span>{{ error }}</span>
      </div>
    </div>
  </div>
</template>
