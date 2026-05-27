<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import StripeSettingsTab from '../components/StripeSettingsTab.vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useSettingsStore } from '../store/useSettingsStore';

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
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch subscriptions', life: 3000 });
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
        toast.add({ severity: 'warn', summary: 'Missing Account ID', detail: 'Stripe Account ID is not configured.', life: 3000 });
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
        Payment & Billing Dashboard
      </h1>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        Manage subscriptions, billing cycles, and Stripe integration
      </p>
    </div>

    <Tabs v-model:value="activeTab" class="payments-tabs">
      <TabList>
        <Tab value="config" class="font-barlow font-bold">
          <i class="pi pi-cog mr-2" /> CONFIGURATION
        </Tab>
        <Tab value="subscribers" class="font-barlow font-bold" @click="fetchSubscriptions">
          <i class="pi pi-users mr-2" /> SUBSCRIBERS
        </Tab>
        <Tab value="analytics" class="font-barlow font-bold">
          <i class="pi pi-chart-line mr-2" /> ANALYTICS
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
                <h3 class="text-xl font-bold text-slate-900">Active Subscriptions</h3>
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
                <Column field="customer.name" header="Customer">
                  <template #body="{ data }">
                    <div class="flex flex-col">
                      <span v-if="data.customer.name" class="font-bold text-slate-900">{{ data.customer.name }}</span>
                      <span class="text-xs text-slate-500">{{ data.customer.email }}</span>
                      <span class="text-[9px] font-mono text-slate-400 mt-1 uppercase">{{ data.customer.id }}</span>
                    </div>
                  </template>
                </Column>
                <Column field="localUser.name" header="Mapped Athlete">
                  <template #body="{ data }">
                    <div v-if="data.localUser" class="flex items-center gap-2">
                      <i class="pi pi-user text-slate-400 text-xs" />
                      <span class="text-sm font-medium">{{ data.localUser.name }}</span>
                    </div>
                    <Tag v-else value="Unmapped" severity="secondary" />
                  </template>
                </Column>
                <Column field="status" header="Status">
                  <template #body="{ data }">
                    <Tag :value="data.status.toUpperCase()" :severity="getStatusSeverity(data.status)" />
                  </template>
                </Column>
                <Column field="latest_invoice.amount_paid" header="Last Payment">
                  <template #body="{ data }">
                    <div v-if="data.latest_invoice" class="flex flex-col">
                      <div class="flex items-center justify-between gap-4">
                        <span class="font-bold">{{ data.latest_invoice.amount_paid.toFixed(2) }} {{ data.latest_invoice.currency.toUpperCase() }}</span>
                        <span class="text-[10px] text-slate-400">{{ formatDate(data.latest_invoice.created) }}</span>
                      </div>
                      <Tag
                        :value="data.latest_invoice.status.toUpperCase()"
                        :severity="data.latest_invoice.status === 'paid' ? 'success' : 'danger'"
                        class="text-[10px] scale-90 origin-left mt-1"
                      />
                    </div>
                    <span v-else class="text-slate-400 italic">No invoice</span>
                  </template>
                </Column>
                <Column field="current_period_end" header="Renewal Date">
                  <template #body="{ data }">
                    <span class="text-sm">{{ formatDate(data.current_period_end) }}</span>
                  </template>
                </Column>
                <Column header="Actions">
                  <template #body="{ data }">
                    <Button
                      icon="pi pi-external-link"
                      label="Manage"
                      text
                      size="small"
                      @click="openStripeCustomer(data.customer.id)"
                    />
                  </template>
                </Column>
                <template #empty>
                  <div class="py-12 text-center">
                    <i class="pi pi-info-circle text-4xl text-slate-200 mb-4" />
                    <p class="text-slate-500 font-medium">No active subscriptions found in Stripe.</p>
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
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Subscribers</p>
                <p class="text-2xl font-black text-slate-900">{{ subscriptions.length }}</p>
              </div>
            </div>

            <div class="phoenix-card p-6 flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
                <i class="pi pi-euro text-green-600 text-xl" />
              </div>
              <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Estimated MRR</p>
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
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Failed Payments</p>
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
