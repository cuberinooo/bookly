<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

const props = defineProps<{
  company: {
    id: number;
    name: string;
    createdAt: string;
    customSmtpEnabled: boolean;
    smtpEmail: string | null;
    legalNotice: {
      representative: string | null;
      street: string | null;
      houseNumber: string | null;
      zipCode: string | null;
      city: string | null;
      email: string | null;
      phone: string | null;
    } | null;
    insights: {
      isPaymentActive: boolean;
      stripeAccountId: string | null;
      totalUsers: number;
      activeUsers: number;
      inactiveUsers: number;
      totalCourses: number;
      totalCourseSeries: number;
      totalBookings: number;
      upcomingBookings: number;
    };
  };
}>();

const emit = defineEmits<{
  (e: 'show-users'): void;
  (e: 'delete-company'): void;
}>();

const { t } = useI18n();

const getActiveRatio = (active: number, total: number) => {
  if (total <= 0) return 0;
  return Math.round((active / total) * 100);
};

const getDeleteTooltip = () => {
  if (props.company.insights.isPaymentActive) {
    return t('monitor.cannotDeleteActivePayment');
  }
  if (props.company.insights.totalUsers > 1) {
    return t('monitor.cannotDeleteMoreUsers');
  }
  return t('monitor.deleteCompany');
};
</script>

<template>
  <div class="p-6 border-t border-slate-100 rounded-b-2xl">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

      <!-- Card 1: Legal / Contact info -->
      <div class="bg-primary p-5 rounded-2xl border border-slate-100 shadow-sm transition-all duration-300 hover:shadow-md hover:border-slate-200">
        <div class="flex items-center gap-2 mb-3">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 primary-text flex items-center justify-center">
            <i class="pi pi-shield text-sm" />
          </div>
          <h3 class="text-xs font-bold primary-text uppercase tracking-wider">
            {{ t('monitor.legalNotice') }}
          </h3>
        </div>

        <div v-if="company.legalNotice" class="text-sm primary-text space-y-2">
          <div v-if="company.legalNotice.representative" class="font-semibold text-slate-800 border-b border-slate-50 pb-1 flex items-center gap-2">
            <i class="pi pi-user text-slate-400 text-xs" />
            {{ company.legalNotice.representative }}
          </div>
          <div v-if="company.legalNotice.street" class="flex items-start gap-2">
            <i class="pi pi-map-marker text-slate-400 text-xs mt-1" />
            <div>
              <p>{{ company.legalNotice.street }} {{ company.legalNotice.houseNumber }}</p>
              <p>{{ company.legalNotice.zipCode }} {{ company.legalNotice.city }}</p>
            </div>
          </div>
          <div class="pt-2 border-t border-slate-50 space-y-1.5 text-xs font-medium text-slate-500">
            <div v-if="company.legalNotice.email" class="flex items-center gap-2 hover:text-indigo-600 transition-colors duration-200">
              <i class="pi pi-envelope text-slate-400" />
              <span class="truncate block max-w-[180px]">{{ company.legalNotice.email }}</span>
            </div>
            <div v-if="company.legalNotice.phone" class="flex items-center gap-2 hover:text-indigo-600 transition-colors duration-200">
              <i class="pi pi-phone text-slate-400" />
              <span>{{ company.legalNotice.phone }}</span>
            </div>
          </div>
        </div>
        <div v-else class="text-sm text-slate-400 italic py-6 text-center">
          N/A
        </div>
      </div>

      <!-- Card 2: User insights -->
      <div class="bg-primary p-5 rounded-2xl border border-slate-100 shadow-sm transition-all duration-300 hover:shadow-md hover:border-slate-200">
        <div class="flex items-center gap-2 mb-3">
          <div class="w-8 h-8 rounded-lg bg-emerald-50 primary-text flex items-center justify-center">
            <i class="pi pi-users text-sm" />
          </div>
          <h3 class="text-xs font-bold primary-text uppercase tracking-wider">
            {{ t('monitor.totalUsers') }}
          </h3>
        </div>

        <div class="flex flex-col h-[calc(100%-2.5rem)] justify-between gap-4">
          <div>
            <div class="flex items-baseline gap-2">
              <span class="text-4xl font-extrabold text-slate-800">
                {{ company.insights.totalUsers }}
              </span>
              <span class="text-xs font-semibold text-slate-400 uppercase">
                {{ t('monitor.totalUsers') }}
              </span>
            </div>

            <div class="mt-4 space-y-1">
              <div class="flex items-center justify-between text-xs font-medium text-slate-500">
                <span>Active Ratio</span>
                <span class="font-bold text-emerald-600">
                  {{ getActiveRatio(company.insights.activeUsers, company.insights.totalUsers) }}%
                </span>
              </div>
              <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden flex">
                <div
                  class="h-full bg-emerald-500 transition-all duration-500"
                  :style="{ width: getActiveRatio(company.insights.activeUsers, company.insights.totalUsers) + '%' }"
                />
                <div
                  class="h-full bg-slate-300 transition-all duration-500"
                  :style="{ width: getActiveRatio(company.insights.inactiveUsers, company.insights.totalUsers) + '%' }"
                />
              </div>
            </div>
          </div>

          <div class="flex justify-between items-center text-xs font-medium text-slate-500 border-t border-slate-50 pt-3">
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500" />
              {{ company.insights.activeUsers }} {{ t('monitor.active') }}
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-slate-300" />
              {{ company.insights.inactiveUsers }} {{ t('monitor.inactive') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Card 3: Courses & Series -->
      <div class="bg-primary p-5 rounded-2xl border border-slate-100 shadow-sm transition-all duration-300 hover:shadow-md hover:border-slate-200">
        <div class="flex items-center gap-2 mb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 primary-text flex items-center justify-center">
            <i class="pi pi-bookmark text-sm" />
          </div>
          <h3 class="text-xs font-bold primary-text uppercase tracking-wider">
            Courses & Bookings
          </h3>
        </div>

        <div class="grid grid-cols-2 gap-3 h-[calc(100%-2.5rem)] flex-wrap">
          <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100/50 flex flex-col justify-between">
            <div>
              <span class="text-xs font-bold text-blue-500 block uppercase tracking-wider">Courses</span>
              <span class="text-2xl font-black text-blue-900 mt-1 block">
                {{ company.insights.totalCourses }}
              </span>
            </div>
            <span class="text-[10px] text-blue-600/70 font-semibold block mt-1">
              {{ company.insights.totalCourseSeries }} Series
            </span>
          </div>

          <div class="bg-purple-50/50 p-3 rounded-xl border border-purple-100/50 flex flex-col justify-between">
            <div>
              <span class="text-xs font-bold text-purple-500 block uppercase tracking-wider">Bookings</span>
              <span class="text-2xl font-black text-purple-900 mt-1 block">
                {{ company.insights.totalBookings }}
              </span>
            </div>
            <span v-if="company.insights.upcomingBookings > 0" class="items-center mt-1 primary-text rounded font-bold">
              <i class="pi pi-clock text-[9px]" />
              {{ company.insights.upcomingBookings }} Upcom.
            </span>
            <span v-else class="text-[10px] text-purple-600/70 font-semibold block mt-1">
              0 Upcoming
            </span>
          </div>
        </div>
      </div>

      <!-- Card 4: Settings & Action -->
      <div class="bg-primary p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col justify-between transition-all duration-300 hover:shadow-md hover:border-slate-200">
        <div>
          <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-slate-100 primary-text flex items-center justify-center">
              <i class="pi pi-cog text-sm" />
            </div>
            <h3 class="text-xs font-bold primary-text uppercase tracking-wider">
              System Settings
            </h3>
          </div>

          <div class="space-y-3">
            <!-- Custom SMTP Status -->
            <div class="flex items-center justify-between text-xs py-1 border-b border-slate-50">
              <span class="font-medium text-slate-500">{{ t('monitor.customSmtpStatus') }}</span>
              <div class="flex flex-col items-end gap-1">
                <Tag
                  :value="company.customSmtpEnabled ? t('monitor.enabled') : t('monitor.disabled')"
                  :severity="company.customSmtpEnabled ? 'success' : 'secondary'"
                  class="text-[10px]"
                />
                <span v-if="company.smtpEmail" class="text-[10px] text-slate-400 font-mono max-w-[120px] truncate" :title="company.smtpEmail">
                  {{ company.smtpEmail }}
                </span>
              </div>
            </div>

            <!-- Stripe Status -->
            <div class="flex items-center justify-between text-xs py-1">
              <span class="font-medium text-slate-500">{{ t('monitor.paymentsStatus') }}</span>
              <div class="flex flex-col items-end gap-1">
                <Tag
                  :value="company.insights.isPaymentActive ? t('monitor.active') : t('monitor.inactive')"
                  :severity="company.insights.isPaymentActive ? 'success' : 'secondary'"
                  class="text-[10px]"
                />
                <span v-if="company.insights.stripeAccountId" class="text-[10px] text-slate-400 font-mono max-w-[120px] truncate" :title="company.insights.stripeAccountId">
                  {{ company.insights.stripeAccountId }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-50 mt-4 flex flex-col gap-2">
          <Button
            icon="pi pi-users"
            :label="t('monitor.showUsers')"
            class="p-button-sm w-full p-button-outlined"
            @click="emit('show-users')"
          />
          <Button
            icon="pi pi-trash"
            :label="t('monitor.deleteCompany')"
            class="p-button-sm w-full p-button-danger p-button-outlined"
            :disabled="company.insights.isPaymentActive || company.insights.totalUsers > 1"
            :title="getDeleteTooltip()"
            @click="emit('delete-company')"
          />
        </div>
      </div>

    </div>
  </div>
</template>
