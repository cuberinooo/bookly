<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import StripeSettingsTab from '../components/StripeSettingsTab.vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useSettingsStore } from '../store/useSettingsStore';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();
const settingsStore = useSettingsStore();
const subscriptions = ref<any[]>([]);
const isLoadingSubscriptions = ref(false);

const activeTab = ref('config');
const stripeAccountId = computed(() => settingsStore.stripeAccountId);

const fetchSubscriptions = async () => {
    isLoadingSubscriptions.value = true;
    try {
        const response = await api.get('/stripe/subscriptions');
        subscriptions.value = response.data;
    } catch (error) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('payments.fetchFailed'), life: 3000 });
    } finally {
        isLoadingSubscriptions.value = false;
    }
};

const getStatusSeverity = (status: string) => {
    switch (status) {
        case 'active': return 'success';
        case 'trialing': return 'info';
        case 'past_due': return 'danger';
        case 'canceled': return 'secondary';
        case 'unpaid': return 'danger';
        case 'incomplete': return 'warn';
        default: return 'info';
    }
};

const formatDate = (timestamp: number) => {
    if (!timestamp || timestamp <= 0) return 'N/A';
    return new Date(timestamp * 1000).toLocaleDateString();
};

const openStripeCustomer = (customerId: string) => {
    if (stripeAccountId.value) {
        window.open(`https://dashboard.stripe.com/${stripeAccountId.value}/customers/${customerId}`, '_blank');
    } else {
        toast.add({ severity: 'warn', summary: t('payments.missingAccountId'), detail: t('payments.missingAccountIdNote'), life: 3000 });
    }
};

onMounted(() => {
    fetchSubscriptions();
});
</script>

<template>
  <div class="payments-view max-w-7xl mx-auto py-6 md:py-12 px-2 md:px-4">
    <div class="mb-6 md:mb-10">
      <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">
        {{ t('payments.title') }}
      </h1>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        {{ t('payments.subtitle') }}
      </p>
    </div>

    <Tabs
      v-model:value="activeTab"
      class="payments-tabs"
    >
      <TabList>
        <Tab
          value="config"
          class="font-barlow font-bold"
        >
          <i class="pi pi-cog mr-2" /> {{ t('payments.tabs.config') }}
        </Tab>
        <Tab
          value="subscribers"
          class="font-barlow font-bold"
          @click="fetchSubscriptions"
        >
          <i class="pi pi-users mr-2" /> {{ t('payments.tabs.subscribers') }}
        </Tab>
        <Tab
          value="analytics"
          class="font-barlow font-bold"
        >
          <i class="pi pi-chart-line mr-2" /> {{ t('payments.tabs.analytics') }}
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="config">
          <div class="mt-6">
            <StripeSettingsTab />
          </div>
        </TabPanel>

        <TabPanel value="subscribers">
          <div class="mt-6">
            <div class="phoenix-card p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-slate-900">
                  {{ t('payments.activeSubscriptions') }}
                </h3>
                <Button
                  icon="pi pi-refresh"
                  rounded
                  text
                  :loading="isLoadingSubscriptions"
                  @click="fetchSubscriptions"
                />
              </div>

              <DataTable
                :value="subscriptions"
                :loading="isLoadingSubscriptions"
                striped-rows
                class="p-datatable-sm"
                responsive-layout="scroll"
              >
                <Column
                  field="customer.name"
                  :header="t('payments.customer')"
                >
                  <template #body="{ data }">
                    <div class="flex flex-col">
                      <span
                        v-if="data.customer.name"
                        class="font-bold text-slate-900"
                      >{{ data.customer.name }}</span>
                      <span class="text-xs text-slate-500">{{ data.customer.email }}</span>
                      <span class="text-[9px] font-mono text-slate-400 mt-1 uppercase">{{ data.customer.id }}</span>
                    </div>
                  </template>
                </Column>
                <Column
                  field="localUser.name"
                  :header="t('payments.mappedAthlete')"
                >
                  <template #body="{ data }">
                    <div
                      v-if="data.localUser"
                      class="flex items-center gap-2"
                    >
                      <i class="pi pi-user text-slate-400 text-xs" />
                      <span class="text-sm font-medium">{{ data.localUser.name }}</span>
                    </div>
                    <Tag
                      v-else
                      :value="t('payments.unmapped')"
                      severity="secondary"
                    />
                  </template>
                </Column>
                <Column :header="t('payments.activePlans')">
                  <template #body="{ data }">
                    <div class="flex flex-col gap-1">
                      <div
                        v-for="sub in data.subscriptions"
                        :key="sub.id"
                        class="flex items-center gap-2"
                      >
                        <span class="text-[10px] font-bold text-slate-600 bg-green-400/10 px-2 py-0.5 rounded uppercase">{{ sub.plan_name }}</span>
                        <i
                          v-if="sub.cancel_at_period_end"
                          v-tooltip="t('payments.cancellingAtPeriodEnd')"
                          class="pi pi-clock text-[10px] text-amber-500"
                        />
                      </div>
                    </div>
                  </template>
                </Column>
                <Column
                  field="overall_status"
                  :header="t('payments.status')"
                >
                  <template #body="{ data }">
                    <Tag
                      :value="data.overall_status.toUpperCase()"
                      :severity="getStatusSeverity(data.overall_status)"
                    />
                  </template>
                </Column>
                <Column :header="t('payments.lastPayments')">
                  <template #body="{ data }">
                    <div class="flex flex-col gap-2">
                      <div
                        v-for="sub in data.subscriptions"
                        :key="sub.id"
                        class="flex flex-col bg-green-400/10 p-2 rounded border border-slate-100"
                      >
                        <div class="flex items-center justify-between gap-4">
                          <span class="text-[10px] font-bold text-slate-500 uppercase">{{ sub.plan_name }}</span>
                          <span
                            v-if="sub.last_invoice"
                            class="text-[10px] text-slate-400 font-mono"
                          >{{ formatDate(sub.last_invoice.created) }}</span>
                        </div>
                        <div
                          v-if="sub.last_invoice"
                          class="flex items-center justify-between mt-1"
                        >
                          <span class="text-sm font-black text-slate-900">{{ sub.last_invoice.amount_paid.toFixed(2) }} {{ sub.last_invoice.currency.toUpperCase() }}</span>
                          <Tag
                            :value="sub.last_invoice.status.toUpperCase()"
                            :severity="sub.last_invoice.status === 'paid' ? 'success' : 'danger'"
                            class="text-[9px] scale-90 origin-right"
                          />
                        </div>
                        <span
                          v-else
                          class="text-[10px] text-slate-400 italic"
                        >{{ t('payments.noPaymentYet') }}</span>
                      </div>
                    </div>
                  </template>
                </Column>
                <Column
                  field="next_renewal"
                  :header="t('payments.nextRenewal')"
                >
                  <template #body="{ data }">
                    <span class="text-sm">{{ formatDate(data.next_renewal) }}</span>
                  </template>
                </Column>
                <Column :header="t('payments.actions')">
                  <template #body="{ data }">
                    <Button
                      icon="pi pi-external-link"
                      :label="t('payments.manage')"
                      text
                      size="small"
                      @click="openStripeCustomer(data.customer.id)"
                    />
                  </template>
                </Column>
                <template #empty>
                  <div class="py-12 text-center">
                    <i class="pi pi-info-circle text-4xl text-slate-200 mb-4" />
                    <p class="text-slate-500 font-medium">
                      {{ t('payments.noSubscriptionsFound') }}
                    </p>
                  </div>
                </template>
              </DataTable>
            </div>
          </div>
        </TabPanel>

        <TabPanel value="analytics">
          <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="phoenix-card p-6 flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                <i class="pi pi-users text-blue-600 text-xl" />
              </div>
              <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                  {{ t('payments.totalSubscribers') }}
                </p>
                <p class="text-2xl font-black text-slate-900">
                  {{ subscriptions.length }}
                </p>
              </div>
            </div>

            <div class="phoenix-card p-6 flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
                <i class="pi pi-euro text-green-600 text-xl" />
              </div>
              <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                  {{ t('payments.estimatedMrr') }}
                </p>
                <p class="text-2xl font-black text-slate-900">
                  {{ subscriptions.reduce((acc, sub) => acc + (sub.status === 'active' ? (sub.latest_invoice?.amount_paid || 0) : 0), 0).toFixed(2) }} €
                </p>
              </div>
            </div>

            <div class="phoenix-card p-6 flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center">
                <i class="pi pi-exclamation-triangle text-amber-600 text-xl" />
              </div>
              <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                  {{ t('payments.failedPayments') }}
                </p>
                <p class="text-2xl font-black text-slate-900">
                  {{ subscriptions.filter(sub => sub.status === 'past_due' || sub.status === 'unpaid').length }}
                </p>
              </div>
            </div>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped lang="scss">
.payments-view {
    h1 { font-family: 'Barlow Condensed', sans-serif; }
    .font-barlow { font-family: 'Barlow Condensed', sans-serif; }

    :deep(.p-tablist-content) {
      border-bottom: 2px solid var(--border-color);
    }
}
</style>
