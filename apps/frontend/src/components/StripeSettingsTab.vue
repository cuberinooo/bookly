<script setup lang="ts">
import { ref, computed } from 'vue';
import { useSettingsStore } from '../store/useSettingsStore';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const settingsStore = useSettingsStore();
const toast = useToast();

const isSettingUp = ref(false);
const isSaving = ref(false);

const stripeOnboardingComplete = computed(() => settingsStore.stripeOnboardingComplete);

const stripePriceSetupFeeId = ref(settingsStore.stripePriceSetupFeeId || '');
const stripePriceMembershipId = ref(settingsStore.stripePriceMembershipId || '');
const setupFee = ref<number | null>(null);
const monthlyFee = ref<number | null>(null);

const setupStripe = async () => {
    isSettingUp.value = true;
    try {
        const response = await api.post('/stripe/onboard');
        window.location.href = response.data.url;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to start Stripe onboarding.', life: 3000 });
        isSettingUp.value = false;
    }
};

const savePrices = async () => {
    isSaving.value = true;
    try {
        const response = await api.post('/stripe/prices', {
            setupFee: setupFee.value,
            monthlyFee: monthlyFee.value,
        });
        stripePriceSetupFeeId.value = response.data.setupFeePriceId;
        stripePriceMembershipId.value = response.data.monthlyFeePriceId;
        toast.add({ severity: 'success', summary: 'Success', detail: 'Prices saved successfully', life: 3000 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save prices.', life: 3000 });
    } finally {
        isSaving.value = false;
    }
};

</script>

<template>
  <div class="phoenix-card p-6 md:p-10 max-w-3xl">
    <div class="mb-8">
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900 flex items-center gap-3">
        <i class="pi pi-credit-card text-2xl text-amber-500" />
        Stripe Integration
      </h2>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        Configure your payment processing and membership fees.
      </p>
    </div>

    <div v-if="!stripeOnboardingComplete" class="text-center py-10">
      <div class="mb-6">
        <i class="pi pi-stripe text-6xl text-indigo-600"></i>
      </div>
      <h3 class="text-lg font-bold text-slate-900 mb-2">Connect with Stripe</h3>
      <p class="text-slate-600 mb-6 max-w-md mx-auto">
        Set up payments via Stripe to easily collect setup fees and monthly memberships from your athletes.
      </p>
      <Button
        label="Set up Payments via Stripe"
        icon="pi pi-external-link"
        :loading="isSettingUp"
        @click="setupStripe"
        size="large"
      />
    </div>

    <div v-else>
      <div class="bg-green-50 text-green-800 p-4 rounded-lg mb-8 flex items-center gap-3 border border-green-200">
        <i class="pi pi-check-circle text-xl"></i>
        <span class="font-semibold">Stripe account successfully connected!</span>
      </div>

      <h3 class="text-lg font-bold text-slate-900 mb-4">Membership Pricing</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="flex flex-col gap-2">
          <label class="font-bold text-sm text-slate-700">Setup Fee (€)</label>
          <InputNumber v-model="setupFee" mode="currency" currency="EUR" locale="de-DE" placeholder="e.g. 50.00" />
          <small class="text-slate-500">One-time fee charged upon upgrading from trial.</small>
          <div v-if="stripePriceSetupFeeId" class="text-xs text-indigo-600 mt-1">Price ID: {{ stripePriceSetupFeeId }}</div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="font-bold text-sm text-slate-700">Monthly Membership (€)</label>
          <InputNumber v-model="monthlyFee" mode="currency" currency="EUR" locale="de-DE" placeholder="e.g. 29.99" />
          <small class="text-slate-500">Recurring monthly subscription fee.</small>
          <div v-if="stripePriceMembershipId" class="text-xs text-indigo-600 mt-1">Price ID: {{ stripePriceMembershipId }}</div>
        </div>
      </div>

      <div class="flex justify-end pt-4 border-t border-slate-100">
        <Button
          label="Save Prices"
          icon="pi pi-save"
          :loading="isSaving"
          @click="savePrices"
        />
      </div>
    </div>
  </div>
</template>
