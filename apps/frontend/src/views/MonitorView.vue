<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import api from '../services/api';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import CompanyMonitorDetails from '../components/CompanyMonitorDetails.vue';
import PlatformSettingsTab from '../components/PlatformSettingsTab.vue';

const activeTab = ref('companies');

const { t } = useI18n();
const toast = useToast();

const companies = ref<any[]>([]);
const isLoading = ref(false);

const selectedCompany = ref<any>(null);
const showConfirmDialog = ref(false);
const showUsersDialog = ref(false);
const companyUsers = ref<any[]>([]);
const isLoadingUsers = ref(false);
const usersSearchQuery = ref('');
const expandedRows = ref<any[]>([]);
const showDeleteDialog = ref(false);
const isDeletingCompany = ref(false);

const totalCompaniesCount = computed(() => companies.value.length);
const totalUsersCount = computed(() => companies.value.reduce((acc, c) => acc + (c.insights?.totalUsers || 0), 0));
const totalCoursesCount = computed(() => companies.value.reduce((acc, c) => acc + (c.insights?.totalCourses || 0), 0));
const totalBookingsCount = computed(() => companies.value.reduce((acc, c) => acc + (c.insights?.totalBookings || 0), 0));

const fetchCompanies = async () => {
  isLoading.value = true;
  try {
    const response = await api.get('/monitor/companies');
    companies.value = response.data;
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('app.error'),
      detail: t('app.error'),
      life: 5000
    });
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchCompanies();
});

const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString();
};

const handleShowUsersRequest = (company: any) => {
  selectedCompany.value = company;
  showConfirmDialog.value = true;
};

const confirmAccess = async () => {
  showConfirmDialog.value = false;
  showUsersDialog.value = true;
  isLoadingUsers.value = true;
  companyUsers.value = [];
  usersSearchQuery.value = '';

  try {
    const response = await api.get(`/monitor/companies/${selectedCompany.value.id}/users`);
    companyUsers.value = response.data;
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('app.error'),
      detail: t('app.error'),
      life: 5000
    });
    showUsersDialog.value = false;
  } finally {
    isLoadingUsers.value = false;
  }
};

const getFilteredUsers = () => {
  if (!usersSearchQuery.value) return companyUsers.value;
  const query = usersSearchQuery.value.toLowerCase();
  return companyUsers.value.filter(
    (u) => u.name.toLowerCase().includes(query) || u.email.toLowerCase().includes(query)
  );
};

const handleDeleteRequest = (company: any) => {
  selectedCompany.value = company;
  showDeleteDialog.value = true;
};

const confirmDeleteCompany = async () => {
  if (!selectedCompany.value) return;
  isDeletingCompany.value = true;
  try {
    await api.delete(`/monitor/companies/${selectedCompany.value.id}`);
    toast.add({
      severity: 'success',
      summary: t('monitor.deleteSuccess'),
      detail: t('monitor.deleteSuccess'),
      life: 5000
    });
    showDeleteDialog.value = false;
    await fetchCompanies();
  } catch (error: any) {
    const errorMsg = error.response?.data?.error || t('app.error');
    toast.add({
      severity: 'error',
      summary: t('app.error'),
      detail: errorMsg,
      life: 5000
    });
  } finally {
    isDeletingCompany.value = false;
  }
};
</script>

<template>
  <div class="monitor-view max-w-7xl mx-auto py-6 md:py-12 px-2 md:px-4 animate-fade-in">
    <!-- Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-800">
          {{ t('monitor.title') }}
        </h1>
        <p class="text-sm md:text-base text-slate-500 mt-2 font-medium">
          {{ t('monitor.subtitle') }}
        </p>
      </div>
      <Button
        v-if="activeTab === 'companies'"
        icon="pi pi-refresh"
        :label="t('app.refresh')"
        :loading="isLoading"
        severity="secondary"
        outlined
        @click="fetchCompanies"
      />
    </div>

    <Tabs
      v-model:value="activeTab"
      class="monitor-tabs"
    >
      <TabList class="overflow-x-auto whitespace-nowrap scrollbar-hide mb-6">
        <Tab
          value="companies"
          class="font-barlow font-bold text-xs md:text-sm"
        >
          {{ t('monitor.tabs.companies') }}
        </Tab>
        <Tab
          value="settings"
          class="font-barlow font-bold text-xs md:text-sm"
        >
          {{ t('monitor.tabs.settings') }}
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel
          value="companies"
          class="px-0"
        >
          <!-- Summary Statistics Bento Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="p-6 bg-white border border-slate-100 rounded-2xl shadow-sm flex items-center justify-between transition-all duration-300 hover:shadow-md hover:border-slate-200">
              <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Companies</span>
                <span class="text-3xl font-black text-slate-800 mt-1 block">{{ totalCompaniesCount }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center">
                <i class="pi pi-building text-xl" />
              </div>
            </div>
            <div class="p-6 bg-white border border-slate-100 rounded-2xl shadow-sm flex items-center justify-between transition-all duration-300 hover:shadow-md hover:border-slate-200">
              <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Users</span>
                <span class="text-3xl font-black text-slate-800 mt-1 block">{{ totalUsersCount }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-green-50 text-green-500 flex items-center justify-center">
                <i class="pi pi-users text-xl" />
              </div>
            </div>
            <div class="p-6 bg-white border border-slate-100 rounded-2xl shadow-sm flex items-center justify-between transition-all duration-300 hover:shadow-md hover:border-slate-200">
              <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Courses</span>
                <span class="text-3xl font-black text-slate-800 mt-1 block">{{ totalCoursesCount }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-500 flex items-center justify-center">
                <i class="pi pi-bookmark text-xl" />
              </div>
            </div>
            <div class="p-6 bg-white border border-slate-100 rounded-2xl shadow-sm flex items-center justify-between transition-all duration-300 hover:shadow-md hover:border-slate-200">
              <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Bookings</span>
                <span class="text-3xl font-black text-slate-800 mt-1 block">{{ totalBookingsCount }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                <i class="pi pi-calendar text-xl" />
              </div>
            </div>
          </div>

          <!-- Main Companies Data Table -->
          <div class="card p-4 bg-white rounded-2xl shadow-sm border border-slate-100">
            <DataTable
              v-model:expanded-rows="expandedRows"
              :value="companies"
              :loading="isLoading"
              responsive-layout="scroll"
              class="p-datatable-sm"
              data-key="id"
              paginator
              :rows="10"
              paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
              current-page-report-template="{first} to {last} of {totalRecords}"
            >
              <template #empty>
                <div class="text-center py-6 text-slate-500 font-medium">
                  {{ t('monitor.noCompaniesFound') }}
                </div>
              </template>

              <!-- Expander Column -->
              <Column
                expander
                class="w-[3rem]"
              />

              <!-- Company Profile -->
              <Column :header="t('monitor.companyName')">
                <template #body="{ data }">
                  <div>
                    <span class="font-bold text-slate-900 block">{{ data.name }}</span>
                    <span class="text-xs text-slate-400 block mt-0.5">
                      ID: {{ data.id }} • {{ t('monitor.createdAt') }}: {{ formatDate(data.createdAt) }}
                    </span>
                  </div>
                </template>
              </Column>

              <!-- SMTP Configuration Badge Only -->
              <Column :header="t('monitor.customSmtpStatus')">
                <template #body="{ data }">
                  <Tag
                    :value="data.customSmtpEnabled ? t('monitor.enabled') : t('monitor.disabled')"
                    :severity="data.customSmtpEnabled ? 'success' : 'secondary'"
                  />
                </template>
              </Column>

              <!-- Stripe Payments Badge Only -->
              <Column :header="t('monitor.paymentsStatus')">
                <template #body="{ data }">
                  <Tag
                    :value="data.insights.isPaymentActive ? t('monitor.active') : t('monitor.inactive')"
                    :severity="data.insights.isPaymentActive ? 'success' : 'secondary'"
                  />
                </template>
              </Column>

              <!-- Quick Stats Summary -->
              <Column header="Quick Stats">
                <template #body="{ data }">
                  <div class="flex gap-2">
                    <span
                      class="inline-flex items-center gap-1 text-xs text-slate-500 font-semibold border border-slate-200/60 px-2 py-0.5 rounded-full"
                      title="Total Users"
                    >
                      <i class="pi pi-users text-[10px]" />
                      {{ data.insights.totalUsers }}
                    </span>
                    <span
                      class="inline-flex items-center gap-1 text-xs text-slate-500 font-semibold border border-slate-200/60 px-2 py-0.5 rounded-full"
                      title="Total Bookings"
                    >
                      <i class="pi pi-calendar text-[10px]" />
                      {{ data.insights.totalBookings }}
                    </span>
                  </div>
                </template>
              </Column>

              <!-- Row Expansion Details -->
              <template #expansion="{ data }">
                <CompanyMonitorDetails 
                  :company="data"
                  @show-users="handleShowUsersRequest(data)"
                  @delete-company="handleDeleteRequest(data)"
                />
              </template>
            </DataTable>
          </div>
        </TabPanel>

        <TabPanel
          value="settings"
          class="px-0"
        >
          <PlatformSettingsTab />
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- GDPR Confirmation Dialog -->
    <Dialog
      v-model:visible="showConfirmDialog"
      :header="t('monitor.confirmTitle')"
      modal
      :style="{ width: '90vw', maxWidth: '500px' }"
    >
      <div class="space-y-4">
        <div class="flex items-start gap-3 p-3 bg-amber-50 text-amber-800 border border-amber-200 rounded-xl">
          <i class="pi pi-exclamation-triangle text-xl mt-0.5 flex-shrink-0" />
          <p class="text-sm font-medium leading-relaxed">
            {{ t('monitor.sensitivePersonalDataWarning') }}
          </p>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2 mt-4">
          <Button
            :label="t('app.cancel' as any)"
            class="p-button-text"
            @click="showConfirmDialog = false"
          />
          <Button
            :label="t('monitor.confirmButton')"
            severity="danger"
            @click="confirmAccess"
          />
        </div>
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog
      v-model:visible="showDeleteDialog"
      :header="t('monitor.deleteConfirmTitle')"
      modal
      :style="{ width: '90vw', maxWidth: '500px' }"
    >
      <div class="space-y-4">
        <div class="flex items-start gap-3 p-3 bg-red-50 text-red-800 border border-red-200 rounded-xl">
          <i class="pi pi-exclamation-triangle text-xl mt-0.5 flex-shrink-0" />
          <p class="text-sm font-medium leading-relaxed">
            {{ t('monitor.deleteConfirmMessage', { name: selectedCompany?.name || '' }) }}
          </p>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2 mt-4">
          <Button
            :label="t('app.cancel' as any)"
            class="p-button-text"
            :disabled="isDeletingCompany"
            @click="showDeleteDialog = false"
          />
          <Button
            :label="t('monitor.deleteCompany')"
            severity="danger"
            :loading="isDeletingCompany"
            @click="confirmDeleteCompany"
          />
        </div>
      </template>
    </Dialog>

    <!-- Company Users List Dialog -->
    <Dialog
      v-model:visible="showUsersDialog"
      :header="`${t('monitor.userDetails')} - ${selectedCompany?.name || ''}`"
      modal
      :style="{ width: '90vw', maxWidth: '600px' }"
    >
      <div class="space-y-4">
        <div class="flex items-center gap-2">
          <span class="p-input-icon-left w-full">
            <i class="pi pi-search" />
            <InputText
              v-model="usersSearchQuery"
              placeholder="Search by name or email..."
              class="p-inputtext-sm w-full"
            />
          </span>
        </div>

        <DataTable
          :value="getFilteredUsers()"
          :loading="isLoadingUsers"
          responsive-layout="scroll"
          class="p-datatable-sm"
          paginator
          :rows="8"
        >
          <template #empty>
            <div class="text-center py-6 text-slate-500 font-medium">
              {{ t('monitor.noUsersFound') }}
            </div>
          </template>

          <Column
            field="name"
            :header="t('monitor.name')"
          >
            <template #body="{ data }">
              <span class="font-semibold text-slate-800">{{ data.name }}</span>
            </template>
          </Column>

          <Column
            field="email"
            :header="t('monitor.email')"
          >
            <template #body="{ data }">
              <span class="text-slate-600">{{ data.email }}</span>
            </template>
          </Column>
        </DataTable>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2 mt-4">
          <Button
            :label="t('app.cancel' as any)"
            class="p-button-text"
            @click="showUsersDialog = false"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped lang="scss">
.monitor-view {
  h1 {
    font-family: 'Barlow Condensed', sans-serif;
  }
}
</style>
