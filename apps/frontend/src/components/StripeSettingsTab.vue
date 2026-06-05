<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useSettingsStore } from '../store/useSettingsStore';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
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
const paymentEnabled = ref(settingsStore.paymentEnabled);
const billingCycleAnchorDay = ref(settingsStore.billingCycleAnchorDay || 0);

const billingDayOptions = computed(() => [
    { label: t('app.immediate'), value: 0 },
    { label: t('app.firstOfMonth'), value: 1 },
    { label: t('app.fifteenthOfMonth'), value: 15 },
    { label: t('app.lastOfMonth'), value: 28 },
]);

const fetchPrices = async () => {
    if (!stripeOnboardingComplete.value) return;

    isLoadingPrices.value = true;
    try {
        const response = await api.get('/stripe/prices');
        setupFee.value = response.data.setupFee;
        monthlyFee.value = response.data.monthlyFee;
        yearlyFeeEnabled.value = response.data.yearlyFeeEnabled;
        paymentEnabled.value = response.data.paymentEnabled ?? false;
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
        toast.add({ severity: 'info', summary: t('app.copied'), detail: t('settings.stripe.copyAccountSuccess'), life: 2000 });
    }
};

const openStripeDashboard = () => {
    if (stripeAccountId.value) {
        window.open(`https://dashboard.stripe.com/${stripeAccountId.value}`, '_blank');
    }
};

const setupStripe = async () => {
    isSettingUp.value = true;
    try {
        const response = await api.post('/stripe/onboard');
        window.location.href = response.data.url;
    } catch (error) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('settings.stripe.onboardFailed'), life: 3000 });
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
            paymentEnabled: paymentEnabled.value,
            billingCycleAnchorDay: billingCycleAnchorDay.value,
        });
        stripePriceSetupFeeId.value = response.data.setupFeePriceId;
        stripePriceMembershipId.value = response.data.monthlyFeePriceId;
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('settings.stripe.saveSuccess'), life: 3000 });
        // Refresh settings store
        await settingsStore.fetchSettings();
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: error.response?.data?.error || t('settings.stripe.saveFailed'), life: 5000 });
        // Refresh to get consistent state
        fetchPrices();
    } finally {
        isSaving.value = false;
    }
};

const formatCurrency = (value: number | null) => {
    if (value === null) return '—';
    return new Intl.NumberFormat(t('app.language') === 'English' ? 'en-US' : 'de-DE', {
        style: 'currency',
        currency: 'EUR'
    }).format(value);
};

const calcCardFee = (amount: number | null) => {
    if (!amount) return 0;
    return Math.round((amount * 0.014 + 0.25) * 100) / 100;
};

const calcSepaFee = (amount: number | null) => {
    if (!amount) return 0;
    const rawFee = amount * 0.008 + 0.25;
    return Math.round(Math.min(rawFee, 5.00) * 100) / 100;
};

</script>

<template>
  <div class="phoenix-card p-6 md:p-10 max-w-4xl mx-auto">
    <div class="mb-8">
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900 flex items-center gap-3">
        <i class="pi pi-credit-card text-2xl text-amber-500" />
        {{ t('settings.stripe.title') }}
      </h2>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        {{ t('settings.stripe.subtitle') }}
      </p>
    </div>

    <div
      v-if="!stripeOnboardingComplete"
      class="text-center py-10"
    >
      <div class="mb-6">
        <i class="pi pi-stripe text-6xl text-indigo-600" />
      </div>
      <h3 class="text-lg font-bold text-slate-900 mb-2">
        {{ t('settings.stripe.connectTitle') }}
      </h3>
      <p class="text-slate-600 mb-6 max-w-md mx-auto">
        {{ t('settings.stripe.connectDesc') }}
      </p>
      <Button
        :label="t('settings.stripe.connectBtn')"
        severity="primary"
        icon="pi pi-external-link"
        :loading="isSettingUp"
        size="large"
        @click="setupStripe"
      />
    </div>

    <div
      v-else
      class="space-y-10"
    >
      <!-- Connection Status -->
      <div class="bg-green-50 text-green-800 p-4 rounded-lg flex items-center justify-between border border-green-200">
        <div class="flex items-center gap-3">
          <i class="pi pi-check-circle text-xl" />
          <span class="font-semibold">{{ t('settings.stripe.connected') }}</span>
        </div>
        <div
          v-if="stripeAccountId"
          class="flex items-center gap-3"
        >
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono bg-white px-2 py-1 rounded border border-green-200">{{ stripeAccountId }}</span>
            <Button
              v-tooltip="t('settings.stripe.copyAccount')"
              icon="pi pi-copy"
              variant="text"
              size="small"
              @click="copyAccountId"
            />
          </div>
          <Button
            :label="t('settings.stripe.dashboardBtn')"
            icon="pi pi-external-link"
            size="small"
            severity="secondary"
            outlined
            @click="openStripeDashboard"
          />
        </div>
      </div>

      <!-- Payment Toggle Section -->
      <section class="text-white p-6 rounded-2xl shadow-xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-500">
              <i class="pi pi-power-off text-2xl" />
            </div>
            <div>
              <h3 class="text-lg font-black uppercase tracking-widest leading-none mb-1">
                {{ t('settings.stripe.enablePayments') }}
              </h3>
              <p class="text-xs text-slate-400 font-medium">
                {{ t('settings.stripe.enablePaymentsNote') }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <span
              class="text-sm font-bold uppercase tracking-tighter"
              :class="paymentEnabled ? 'text-amber-500' : 'text-slate-500'"
            >
              {{ paymentEnabled ? t('settings.stripe.active') : t('settings.stripe.disabled') }}
            </span>
            <ToggleSwitch v-model="paymentEnabled" />
          </div>
        </div>
        <div
          v-if="!paymentEnabled"
          class="mt-4 p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl flex items-start gap-3"
        >
          <i class="pi pi-info-circle text-amber-500 mt-0.5" />
          <p class="text-[10px] text-amber-200/80 leading-relaxed italic">
            {{ t('settings.stripe.paymentDisabledWarning') }}
          </p>
        </div>
      </section>

      <!-- Membership Pricing Section -->
      <section>
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-bold text-slate-900 border-l-4 border-amber-500 pl-3">
            {{ t('settings.stripe.pricing') }}
          </h3>
          <Button
            v-tooltip="t('settings.stripe.refreshPrices')"
            icon="pi pi-refresh"
            variant="text"
            size="small"
            :loading="isLoadingPrices"
            @click="fetchPrices"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
              <label class="font-bold text-sm text-slate-700 uppercase tracking-tight">{{ t('settings.stripe.yearlyFee') }}</label>
              <ToggleSwitch v-model="yearlyFeeEnabled" />
            </div>
            <div
              class="p-inputgroup"
              :class="{ 'opacity-50 pointer-events-none': !yearlyFeeEnabled }"
            >
              <InputNumber
                v-model="setupFee"
                mode="currency"
                currency="EUR"
                locale="de-DE"
                placeholder="e.g. 50.00"
              />
              <span class="p-inputgroup-addon bg-slate-50">
                <i
                  v-if="stripePriceSetupFeeId"
                  v-tooltip="t('settings.stripe.priceCreated')"
                  class="pi pi-verified text-green-500"
                />
                <i
                  v-else
                  v-tooltip="t('settings.stripe.priceWillBeCreated')"
                  class="pi pi-info-circle text-amber-500"
                />
              </span>
            </div>
            <small class="text-slate-500 leading-snug italic">{{ t('settings.stripe.yearlyFeeNote') }}</small>
          </div>

          <div class="flex flex-col gap-3">
            <label class="font-bold text-sm text-slate-700 uppercase tracking-tight">{{ t('settings.stripe.monthlyFee') }}</label>
            <div class="p-inputgroup">
              <InputNumber
                v-model="monthlyFee"
                mode="currency"
                currency="EUR"
                locale="de-DE"
                placeholder="e.g. 29.99"
              />
              <span class="p-inputgroup-addon bg-slate-50">
                <i
                  v-if="stripePriceMembershipId"
                  v-tooltip="t('settings.stripe.priceCreated')"
                  class="pi pi-verified text-green-500"
                />
                <i
                  v-else
                  v-tooltip="t('settings.stripe.priceWillBeCreated')"
                  class="pi pi-info-circle text-amber-500"
                />
              </span>
            </div>
            <small class="text-slate-500 leading-snug italic">{{ t('settings.stripe.monthlyFeeNote') }}</small>
          </div>
        </div>

        <!-- Stripe Fee Breakdown Card -->
        <div v-if="monthlyFee || (yearlyFeeEnabled && setupFee)" class="mt-8 p-6 bg-slate-50 rounded-2xl border border-slate-200">
          <h4 class="text-sm font-extrabold uppercase tracking-wider text-slate-700 mb-4 flex items-center gap-2">
            <i class="pi pi-info-circle text-amber-500" />
            {{ t('settings.stripe.feeBreakdownTitle') }}
          </h4>
          <p class="text-xs text-slate-500 mb-6">
            {{ t('settings.stripe.feeExplanation') }}
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Monthly Fee Breakdown -->
            <div v-if="monthlyFee" class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
              <div class="flex items-center gap-2 mb-3">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500" />
                <span class="font-bold text-xs uppercase tracking-wider text-slate-600">{{ t('settings.stripe.monthlyAbo') }}</span>
              </div>
              <div class="space-y-2.5 text-xs text-slate-600">
                <div class="flex justify-between">
                  <span>{{ t('settings.stripe.grossAmount') }}</span>
                  <span class="font-bold text-slate-900">{{ formatCurrency(monthlyFee) }}</span>
                </div>
                <div class="border-t border-dashed border-slate-100 pt-2 flex justify-between">
                  <span class="flex items-center gap-1">
                    {{ t('settings.stripe.creditCardFee') }}
                    <small class="text-slate-400 font-mono">(1.4% + €0.25)</small>
                  </span>
                  <span class="text-red-500">- {{ formatCurrency(calcCardFee(monthlyFee)) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="flex items-center gap-1">
                    {{ t('settings.stripe.sepaFee') }}
                    <small class="text-slate-400 font-mono">(0.8% + €0.25)</small>
                  </span>
                  <span class="text-red-500">- {{ formatCurrency(calcSepaFee(monthlyFee)) }}</span>
                </div>
                <div class="border-t border-slate-200 pt-3 flex justify-between font-bold text-sm">
                  <span>{{ t('settings.stripe.estPayout') }}</span>
                  <div class="text-right">
                    <div class="text-green-600" v-tooltip="t('settings.stripe.viaCreditCard')">
                      {{ formatCurrency(monthlyFee - calcCardFee(monthlyFee)) }} <span class="text-[9px] text-slate-400 font-normal">CC</span>
                    </div>
                    <div class="text-green-600 text-xs font-normal mt-0.5" v-tooltip="t('settings.stripe.viaSepa')">
                      {{ formatCurrency(monthlyFee - calcSepaFee(monthlyFee)) }} <span class="text-[9px] text-slate-400 font-normal">SEPA</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Yearly/Setup Fee Breakdown -->
            <div v-if="yearlyFeeEnabled && setupFee" class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
              <div class="flex items-center gap-2 mb-3">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500" />
                <span class="font-bold text-xs uppercase tracking-wider text-slate-600">{{ t('settings.stripe.yearlySetup') }}</span>
              </div>
              <div class="space-y-2.5 text-xs text-slate-600">
                <div class="flex justify-between">
                  <span>{{ t('settings.stripe.grossAmount') }}</span>
                  <span class="font-bold text-slate-900">{{ formatCurrency(setupFee) }}</span>
                </div>
                <div class="border-t border-dashed border-slate-100 pt-2 flex justify-between">
                  <span class="flex items-center gap-1">
                    {{ t('settings.stripe.creditCardFee') }}
                    <small class="text-slate-400 font-mono">(1.4% + €0.25)</small>
                  </span>
                  <span class="text-red-500">- {{ formatCurrency(calcCardFee(setupFee)) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="flex items-center gap-1">
                    {{ t('settings.stripe.sepaFee') }}
                    <small class="text-slate-400 font-mono">(0.8% + €0.25)</small>
                  </span>
                  <span class="text-red-500">- {{ formatCurrency(calcSepaFee(setupFee)) }}</span>
                </div>
                <div class="border-t border-slate-200 pt-3 flex justify-between font-bold text-sm">
                  <span>{{ t('settings.stripe.estPayout') }}</span>
                  <div class="text-right">
                    <div class="text-green-600" v-tooltip="t('settings.stripe.viaCreditCard')">
                      {{ formatCurrency(setupFee - calcCardFee(setupFee)) }} <span class="text-[9px] text-slate-400 font-normal">CC</span>
                    </div>
                    <div class="text-green-600 text-xs font-normal mt-0.5" v-tooltip="t('settings.stripe.viaSepa')">
                      {{ formatCurrency(setupFee - calcSepaFee(setupFee)) }} <span class="text-[9px] text-slate-400 font-normal">SEPA</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Billing Cycle Configuration -->
      <section>
        <h3 class="text-lg font-bold text-slate-900 border-l-4 border-indigo-500 pl-3 mb-6">
          {{ t('settings.stripe.billingSchedule') }}
        </h3>
        <div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <label class="font-bold text-sm text-slate-900 block mb-1">{{ t('settings.stripe.billingAlignment') }}</label>
            <p class="text-xs text-slate-600 mb-0">
              {{ t('settings.stripe.billingAlignmentNote') }}
            </p>
            <Select
              v-model="billingCycleAnchorDay"
              :options="billingDayOptions"
              option-label="label"
              option-value="value"
              :placeholder="t('settings.stripe.selectBillingDay')"
              class="w-full md:w-64"
            />
          </div>
        </div>
      </section>

      <!-- Save Action -->
      <div class="flex justify-end pt-6 border-t border-slate-100">
        <Button
          :label="t('settings.stripe.saveBtn')"
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

