<script setup lang="ts">
import StripeSettingsTab from '../components/StripeSettingsTab.vue';
</script>

<template>
  <div class="payments-view max-w-6xl mx-auto py-6 md:py-12 px-2 md:px-4">
    <div class="mb-6 md:mb-10">
      <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">
        Payment & Billing
      </h1>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        Configure Stripe integration and membership pricing
      </p>
    </div>

    <div class="mt-4 md:mt-6">
      <StripeSettingsTab />
    </div>
  </div>
</template>

<style scoped lang="scss">
.payments-view {
    h1 { font-family: 'Barlow Condensed', sans-serif; }
}
</style>
