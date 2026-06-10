<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { useAuthStore } from '../store/useAuthStore';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const authStore = useAuthStore();
const toast = useToast();
const confirm = useConfirm();

const isNotProd = import.meta.env.MODE !== 'production';

const isLoading = ref(true);
const isUpgrading = ref(false);
const isCancelling = ref(false);
const subscription = ref<any>(null);
const prices = ref<any>(null);
const invoices = ref<any[]>([]);

const fetchAboData = async () => {
    isLoading.value = true;
    try {
        const [subRes, priceRes, invoiceRes] = await Promise.all([
            api.get('/stripe/my-subscription'),
            api.get('/stripe/prices'),
            api.get('/stripe/my-invoices')
        ]);
        subscription.value = subRes.data;
        prices.value = priceRes.data;
        invoices.value = invoiceRes.data;
    } catch (error) {
        console.error('Failed to fetch abo data', error);
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.actions.loadFailed'), life: 3000 });
    } finally {
        isLoading.value = false;
    }
};

const downloadInvoice = (url: string) => {
    if (url) {
        window.open(url, '_blank');
    }
};

const confirmCancellation = () => {
    confirm.require({
        message: t('profile.actions.cancelConfirm'),
        header: t('profile.actions.cancelHeader'),
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectProps: {
          label: t('profile.actions.staySubscribed'),
          severity: 'secondary',
          text: true
        },
        accept: () => {
            cancelSubscription();
        }
    });
};

const isReactivating = ref(false);

const reactivateSubscription = async () => {
    isReactivating.value = true;
    try {
        await api.post('/stripe/my-subscription/reactivate');
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('profile.actions.reactivateSuccess'), life: 5000 });
        await fetchAboData();
    } catch (e: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: e.response?.data?.error || t('profile.actions.reactivateFailed'), life: 3000 });
    } finally {
        isReactivating.value = false;
    }
};

const cancellationDetails = ref<any>(null);
const showCancelSuccessDialog = ref(false);

const cancelSubscription = async () => {
    isCancelling.value = true;
    try {
        const response = await api.delete('/stripe/my-subscription');
        cancellationDetails.value = response.data;
        showCancelSuccessDialog.value = true;

        toast.add({ severity: 'success', summary: t('app.success'), detail: t('profile.actions.cancelSuccess'), life: 5000 });
        await fetchAboData();
    } catch (e: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: e.response?.data?.error || t('profile.actions.cancelFailed'), life: 3000 });
    } finally {
        isCancelling.value = false;
    }
};

const upgrade = async () => {
    isUpgrading.value = true;
    try {
        const response = await api.post('/stripe/checkout');
        window.location.href = response.data.url;
    } catch (e: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: e.response?.data?.error || t('profile.actions.checkoutFailed'), life: 3000 });
    } finally {
        isUpgrading.value = false;
    }
};

const manageSubscription = async () => {
    isUpgrading.value = true;
    try {
        const response = await api.post('/stripe/portal-session');
        window.location.href = response.data.url;
    } catch (e: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: e.response?.data?.error || t('profile.actions.portalFailed'), life: 3000 });
    } finally {
        isUpgrading.value = false;
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

const features = computed(() => [
    { title: t('profile.benefits.features.unlimited.title'), desc: t('profile.benefits.features.unlimited.desc') },
    { title: t('profile.benefits.features.pb.title'), desc: t('profile.benefits.features.pb.desc') },
    { title: t('profile.benefits.features.rankings.title'), desc: t('profile.benefits.features.rankings.desc') },
    { title: t('profile.benefits.features.stats.title'), desc: t('profile.benefits.features.stats.desc') },
]);

onMounted(fetchAboData);
</script>

<template>
  <div class="user-abo-tab max-w-4xl mx-auto py-4">
    <div
      v-if="isLoading"
      class="space-y-6"
    >
      <Skeleton
        height="200px"
        border-radius="16px"
      />
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Skeleton
          height="300px"
          border-radius="16px"
        />
        <Skeleton
          height="300px"
          border-radius="16px"
        />
      </div>
    </div>

    <div
      v-else
      class="space-y-8"
    >
      <!-- Current Subscription Status -->
      <div
        v-if="subscription && subscription.status !== 'inactive'"
        class="phoenix-card p-6 md:p-8 border-l-8 bg-opacity-30"
        :class="subscription.cancel_at_period_end ? 'border-amber-500 bg-amber-50' : 'border-green-500 bg-green-50'"
      >
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
          <div class="flex items-center gap-4">
            <div
              class="w-14 h-14 rounded-2xl flex items-center justify-center"
              :class="subscription.cancel_at_period_end ? 'bg-amber-100 text-amber-600' : 'bg-green-100 text-green-600'"
            >
              <i
                :class="subscription.cancel_at_period_end ? 'pi pi-exclamation-circle' : 'pi pi-check-circle'"
                class="text-2xl"
              />
            </div>
            <div>
              <h3 class="text-xl font-black uppercase tracking-tight text-slate-900">
                {{ subscription.cancel_at_period_end ? t('profile.subscriptionStatus.cancelling') : t('profile.subscriptionStatus.active') }}
              </h3>
              <div class="flex items-center gap-2 mt-1">
                <Tag
                  :value="subscription.display_status.toUpperCase()"
                  :severity="getStatusSeverity(subscription.display_status)"
                />
                <span class="text-sm text-slate-500 font-medium">
                  {{ subscription.cancel_at_period_end ? t('profile.subscriptionStatus.accessUntil') : t('profile.subscriptionStatus.renewsOn') }} {{ formatDate(subscription.current_period_end) }}
                </span>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <Button
              v-if="!subscription.cancel_at_period_end && (subscription.status === 'active' || subscription.status === 'trialing')"
              :label="t('profile.subscriptionStatus.cancelBtn')"
              icon="pi pi-times-circle"
              severity="danger"
              text
              size="small"
              :loading="isCancelling"
              @click="confirmCancellation"
            />
            <Button
              v-if="subscription.cancel_at_period_end"
              :label="t('profile.subscriptionStatus.reactivateBtn')"
              icon="pi pi-refresh"
              severity="success"
              text
              size="small"
              :loading="isReactivating"
              @click="reactivateSubscription"
            />
            <Button
              v-if="isNotProd"
              :label="t('profile.subscriptionStatus.manageStripe')"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
              size="small"
              :loading="isUpgrading"
              @click="manageSubscription"
            />
          </div>
        </div>
      </div>

      <div
        v-else
        class="phoenix-card p-6 md:p-8 border-l-8 border-amber-500 bg-amber-50/30"
      >
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-amber-100 flex items-center justify-center text-amber-600">
            <i class="pi pi-bolt text-2xl" />
          </div>
          <div>
            <h3 class="text-xl font-black uppercase tracking-tight text-slate-900">
              {{ t('profile.trial.title') }}
            </h3>
            <p class="text-sm text-slate-600 font-medium mt-1">
              {{ t('profile.trial.subtitle') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Membership Pricing & Features -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
        <!-- Feature Highlights -->
        <div class="phoenix-card p-6 md:p-8 flex flex-col">
          <h3 class="text-lg font-black uppercase tracking-widest text-slate-900 mb-6 flex items-center gap-2">
            <i class="pi pi-star-fill text-amber-500" />
            {{ t('profile.benefits.title') }}
          </h3>
          <ul class="space-y-6 flex-1">
            <li
              v-for="feature in features"
              :key="feature.title"
              class="flex gap-4"
            >
              <div class="mt-1 w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                <i class="pi pi-check text-[10px] text-blue-600 font-bold" />
              </div>
              <div>
                <p class="font-bold text-slate-900 text-sm">
                  {{ feature.title }}
                </p>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                  {{ feature.desc }}
                </p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Pricing Card -->
        <div class="phoenix-card p-6 md:p-8 border-2 border-slate-900 flex flex-col relative overflow-hidden">
          <h3 class="text-lg font-black uppercase tracking-widest text-slate-900 mb-2">
            {{ t('profile.pricing.title') }}
          </h3>
          <p class="text-xs text-slate-500 mb-6 font-medium">
            {{ t('profile.pricing.subtitle') }}
          </p>

          <div class="flex items-baseline gap-1 mb-8">
            <span class="text-4xl font-black text-slate-900">{{ prices?.monthlyFee?.toFixed(2) }}€</span>
            <span class="text-slate-500 font-bold">{{ t('profile.pricing.perMonth') }}</span>
          </div>

          <div class="space-y-4 mb-8">
            <div
              v-if="prices?.yearlyFeeEnabled && prices?.setupFee"
              class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200"
            >
              <div class="flex flex-col">
                <span class="text-xs font-bold text-slate-900">{{ t('profile.pricing.yearlyFee') }}</span>
                <span class="text-[10px] text-slate-500">{{ t('profile.pricing.yearlyFeeNote') }}</span>
              </div>
              <span class="font-black text-slate-900">{{ prices.setupFee.toFixed(2) }}€</span>
            </div>

            <div class="flex items-center justify-between p-3 bg-blue-50/50 rounded-xl border border-blue-100">
              <div class="flex flex-col">
                <span class="text-xs font-bold text-blue-900">{{ t('profile.pricing.monthlyMember') }}</span>
                <span class="text-[10px] text-blue-600">{{ t('profile.pricing.monthlyMemberNote') }}</span>
              </div>
              <span class="font-black text-blue-900">{{ prices?.monthlyFee?.toFixed(2) }}€</span>
            </div>
          </div>

          <div class="mt-auto">
            <Button
              v-if="subscription?.status !== 'active'"
              :label="t('profile.pricing.upgradeBtn')"
              severity="primary"
              icon="pi pi-bolt"
              class="w-full upgrade-btn py-4"
              :loading="isUpgrading"
              @click="upgrade"
            />
            <Button
              v-else
              :label="t('profile.pricing.isMember')"
              icon="pi pi-verified"
              class="w-full py-4"
              disabled
              severity="primary"
            />
            <p class="text-[10px] text-center text-slate-400 mt-4 leading-relaxed">
              {{ t('profile.pricing.securePayment') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Billing History / Invoices -->
      <div v-if="invoices && invoices.length > 0" class="phoenix-card p-6 md:p-8 space-y-6">
        <h3 class="text-lg font-black uppercase tracking-widest text-slate-900 flex items-center gap-2">
          <i class="pi pi-receipt text-slate-500" />
          {{ t('profile.invoices.header') }}
        </h3>

        <DataTable
          :value="invoices"
          responsiveLayout="scroll"
          class="w-full text-sm"
          :paginator="invoices.length > 5"
          :rows="5"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
        >
          <Column field="number" :header="t('profile.invoices.description')" class="font-bold text-slate-700">
            <template #body="slotProps">
              {{ slotProps.data.number || slotProps.data.id }}
            </template>
          </Column>

          <Column field="description" :header="t('profile.invoices.title')" class="text-slate-800">
            <template #body="slotProps">
              {{ slotProps.data.description }}
            </template>
          </Column>

          <Column field="amount_paid" :header="t('profile.invoices.price')" class="font-bold text-slate-900">
            <template #body="slotProps">
              {{ slotProps.data.amount_paid.toFixed(2) }}{{ slotProps.data.currency === 'EUR' ? '€' : ' ' + slotProps.data.currency }}
            </template>
          </Column>

          <Column field="created" :header="t('profile.invoices.date')" class="text-slate-500">
            <template #body="slotProps">
              {{ formatDate(slotProps.data.created) }}
            </template>
          </Column>

          <Column headerStyle="width: 5rem; text-align: center" bodyStyle="text-align: center; overflow: visible">
            <template #body="slotProps">
              <Button
                v-if="slotProps.data.invoice_pdf"
                icon="pi pi-download"
                severity="secondary"
                text
                rounded
                v-tooltip.top="t('profile.invoices.download')"
                @click="downloadInvoice(slotProps.data.invoice_pdf)"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Cancellation Success Dialog -->
    <Dialog
      v-model:visible="showCancelSuccessDialog"
      modal
      :header="t('profile.actions.cancelHeader')"
      :style="{ width: '450px' }"
    >
      <div class="flex flex-col items-center text-center p-4">
        <div
          class="w-16 h-16 rounded-2xl flex items-center justify-center mb-6"
          :class="cancellationDetails?.cancellation_type === 'period_end' ? 'bg-amber-100 text-amber-600' : 'bg-rose-100 text-rose-600'"
        >
          <i
            :class="cancellationDetails?.cancellation_type === 'period_end' ? 'pi pi-exclamation-circle' : 'pi pi-times-circle'"
            class="text-3xl"
          />
        </div>

        <h4 class="text-xl primary-text uppercase tracking-tight mb-3">
          {{ cancellationDetails?.cancellation_type === 'immediate'
            ? t('profile.actions.immediateCancelTitle')
            : t('profile.actions.periodEndCancelTitle') }}
        </h4>

        <p class="text-slate-600 text-sm leading-relaxed mb-6 font-medium">
          <span v-if="cancellationDetails?.cancellation_type === 'immediate'">
            {{ t('profile.actions.immediateCancelDesc') }}
          </span>
          <span v-else>
            {{ t('profile.actions.periodEndCancelDesc') }}
            <strong class="primary-text ml-1">{{ formatDate(cancellationDetails?.ends_at) }}</strong>.
            <span class="block mt-2">{{ t('profile.actions.periodEndCancelDescEnd') }}</span>
          </span>
        </p>

        <Button
          :label="t('app.close')"
          severity="secondary"
          class="w-full py-3 font-bold uppercase tracking-wider text-xs"
          outlined
          @click="showCancelSuccessDialog = false"
        />
      </div>
    </Dialog>
  </div>
</template>

<style scoped lang="scss">
.user-abo-tab {
    .font-barlow { font-family: 'Barlow Condensed', sans-serif; }

    .upgrade-btn {
        border: none;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        font-family: 'Barlow Condensed', sans-serif;
        &:hover { background: #1e293b; }
    }
}
</style>
