<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useSettingsStore } from '../store/useSettingsStore';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const settingsStore = useSettingsStore();
const toast = useToast();

const isSettingUp = ref(false);
const isSaving = ref(false);
const isLoadingPrices = ref(false);

const stripeOnboardingComplete = computed(() => settingsStore.stripeOnboardingComplete);
const stripeAccountId = computed(() => settingsStore.stripeAccountId);

const stripePriceSetupFeeId = ref(settingsStore.stripePriceSetupFeeId || '');
const stripePriceMembershipId = ref(settingsStore.stripePriceMembershipId || '');
const setupFee = ref<number | null>(null);
const monthlyFee = ref<number | null>(null);
const yearlyFeeEnabled = ref(settingsStore.yearlyFeeEnabled);
const billingCycleAnchorDay = ref(settingsStore.billingCycleAnchorDay || 0);

const billingDayOptions = [
    { label: 'Immediate (No alignment)', value: 0 },
    { label: '1st of the month', value: 1 },
    { label: '15th of the month', value: 15 },
    { label: 'Last day of month', value: 28 },
];

const fetchPrices = async () => {
    if (!stripeOnboardingComplete.value) return;

    isLoadingPrices.value = true;
    try {
        const response = await api.get('/stripe/prices');
        setupFee.value = response.data.setupFee;
        monthlyFee.value = response.data.monthlyFee;
        yearlyFeeEnabled.value = response.data.yearlyFeeEnabled;
        billingCycleAnchorDay.value = response.data.billingCycleAnchorDay || 0;
    } catch (error) {
        console.error('Failed to fetch prices', error);
    } finally {
        isLoadingPrices.value = false;
    }
};

onMounted(() => {
    fetchPrices();
});

const copyAccountId = () => {
    if (stripeAccountId.value) {
        navigator.clipboard.writeText(stripeAccountId.value);
        toast.add({ severity: 'info', summary: 'Copied', detail: 'Account ID copied to clipboard', life: 2000 });
    }
};

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
            yearlyFeeEnabled: yearlyFeeEnabled.value,
            billingCycleAnchorDay: billingCycleAnchorDay.value,
        });
        stripePriceSetupFeeId.value = response.data.setupFeePriceId;
        stripePriceMembershipId.value = response.data.monthlyFeePriceId;
        toast.add({ severity: 'success', summary: 'Success', detail: 'Settings saved and synced with Stripe', life: 3000 });
        // Refresh settings store
        await settingsStore.fetchSettings();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save settings.', life: 3000 });
    } finally {
        isSaving.value = false;
    }
};

</script>

<template>
  <div class="phoenix-card p-6 md:p-10 max-w-4xl mx-auto">
    <div class="mb-8">
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900 flex items-center gap-3">
        <i class="pi pi-credit-card text-2xl text-amber-500" />
        Stripe Integration
      </h2>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        Configure your payment processing, membership fees, and billing cycles.
      </p>
    </div>

    <div v-if="!stripeOnboardingComplete" class="text-center py-10">
      <div class="mb-6">
        <i class="pi pi-stripe text-6xl text-indigo-600"></i>
      </div>
      <h3 class="text-lg font-bold text-slate-900 mb-2">Connect with Stripe</h3>
      <p class="text-slate-600 mb-6 max-w-md mx-auto">
        Set up payments via Stripe to easily collect memberships and fees from your athletes.
      </p>
      <Button
        label="Set up Payments via Stripe"
        icon="pi pi-external-link"
        :loading="isSettingUp"
        @click="setupStripe"
        size="large"
      />
    </div>

    <div v-else class="space-y-10">
      <!-- Connection Status -->
      <div class="bg-green-50 text-green-800 p-4 rounded-lg flex items-center justify-between border border-green-200">
        <div class="flex items-center gap-3">
          <i class="pi pi-check-circle text-xl"></i>
          <span class="font-semibold">Stripe account successfully connected!</span>
        </div>
        <div v-if="stripeAccountId" class="flex items-center gap-2">
          <span class="text-xs font-mono bg-white px-2 py-1 rounded border border-green-200">{{ stripeAccountId }}</span>
          <Button icon="pi pi-copy" variant="text" size="small" @click="copyAccountId" v-tooltip="'Copy Account ID'" />
        </div>
      </div>

      <!-- Membership Pricing Section -->
      <section>
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-bold text-slate-900 border-l-4 border-amber-500 pl-3">Membership Pricing</h3>
          <Button
            icon="pi pi-refresh"
            variant="text"
            size="small"
            :loading="isLoadingPrices"
            @click="fetchPrices"
            v-tooltip="'Refresh prices from Stripe'"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
              <label class="font-bold text-sm text-slate-700 uppercase tracking-tight">Yearly Admin Fee (€)</label>
              <ToggleSwitch v-model="yearlyFeeEnabled" />
            </div>
            <div class="p-inputgroup" :class="{ 'opacity-50 pointer-events-none': !yearlyFeeEnabled }">
              <InputNumber v-model="setupFee" mode="currency" currency="EUR" locale="de-DE" placeholder="e.g. 50.00" />
              <span class="p-inputgroup-addon bg-slate-50">
                <i v-if="stripePriceSetupFeeId" class="pi pi-verified text-green-500" v-tooltip="'Price created in Stripe'" />
                <i v-else class="pi pi-info-circle text-amber-500" v-tooltip="'Price will be created on save'" />
              </span>
            </div>
            <small class="text-slate-500 leading-snug italic">Recurring annual fee charged upon registration and every 12 months.</small>
          </div>

          <div class="flex flex-col gap-3">
            <label class="font-bold text-sm text-slate-700 uppercase tracking-tight">Monthly Membership (€)</label>
            <div class="p-inputgroup">
              <InputNumber v-model="monthlyFee" mode="currency" currency="EUR" locale="de-DE" placeholder="e.g. 29.99" />
              <span class="p-inputgroup-addon bg-slate-50">
                <i v-if="stripePriceMembershipId" class="pi pi-verified text-green-500" v-tooltip="'Price created in Stripe'" />
                <i v-else class="pi pi-info-circle text-amber-500" v-tooltip="'Price will be created on save'" />
              </span>
            </div>
            <small class="text-slate-500 leading-snug italic">Standard recurring monthly subscription fee for all members.</small>
          </div>
        </div>
      </section>

      <!-- Billing Cycle Configuration -->
      <section>
        <h3 class="text-lg font-bold text-slate-900 border-l-4 border-indigo-500 pl-3 mb-6">Billing Schedule</h3>
        <div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
              <label class="font-bold text-sm text-slate-900 block mb-1">Monthly Billing Alignment</label>
              <p class="text-xs text-slate-600 mb-0">Select a specific day of the month for all member subscriptions. New members will be prorated until this day.</p>
            <Select
              v-model="billingCycleAnchorDay"
              :options="billingDayOptions"
              option-label="label"
              option-value="value"
              placeholder="Select Billing Day"
              class="w-full md:w-64"
            />
          </div>
        </div>
      </section>

      <!-- Save Action -->
      <div class="flex justify-end pt-6 border-t border-slate-100">
        <Button
          label="Save & Sync Preferences"
          icon="pi pi-save"
          severity="primary"
          class="px-8"
          :loading="isSaving"
          @click="savePrices"
        />
      </div>
    </div>
  </div>
</template>
