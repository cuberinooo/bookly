<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { downloadPlatformPrivacyPolicy } from '../services/download';

const { t } = useI18n();
const toast = useToast();

const settings = ref({
  operatorName: '',
  operatorCompany: '',
  operatorDetails: '',
  operatorStreet: '',
  operatorHouseNumber: '',
  operatorZipCode: '',
  operatorCity: '',
  operatorEmail: '',
  operatorPhone: '',
  profession: '',
  country: '',
  taxId: '',
  vatId: '',
  privacyPolicyPdfPath: ''
});

const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);

async function fetchSettings() {
  loading.value = true;
  try {
    const response = await api.get('/monitor/platform-settings');
    settings.value = {
      operatorName: response.data.operatorName || '',
      operatorCompany: response.data.operatorCompany || '',
      operatorDetails: response.data.operatorDetails || '',
      operatorStreet: response.data.operatorStreet || '',
      operatorHouseNumber: response.data.operatorHouseNumber || '',
      operatorZipCode: response.data.operatorZipCode || '',
      operatorCity: response.data.operatorCity || '',
      operatorEmail: response.data.operatorEmail || '',
      operatorPhone: response.data.operatorPhone || '',
      profession: response.data.profession || '',
      country: response.data.country || '',
      taxId: response.data.taxId || '',
      vatId: response.data.vatId || '',
      privacyPolicyPdfPath: response.data.privacyPolicyPdfPath || ''
    };
  } catch (e) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('app.error'), life: 5000 });
  } finally {
    loading.value = false;
  }
}

async function updateSettings() {
  saving.value = true;
  try {
    await api.patch('/monitor/platform-settings', settings.value);
    toast.add({ severity: 'success', summary: t('app.updated'), detail: t('profile.updateSuccess'), life: 5000 });
  } catch (e) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.updateError'), life: 5000 });
  } finally {
    saving.value = false;
  }
}

async function onUpload(event: any) {
  const file = event.files[0];
  if (!file) return;

  uploading.value = true;
  const formData = new FormData();
  formData.append('file', file);

  try {
    const response = await api.post('/monitor/platform-settings/privacy-policy', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    settings.value.privacyPolicyPdfPath = response.data.path;
    toast.add({ severity: 'success', summary: t('app.uploaded'), detail: t('profile.updateSuccess'), life: 5000 });
  } catch (e: any) {
    const message = e.response?.data?.error || t('profile.uploadFailed');
    toast.add({ severity: 'error', summary: t('app.error'), detail: message, life: 5000 });
  } finally {
    uploading.value = false;
  }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="platform-settings-tab">
    <div
      v-if="loading"
      class="flex justify-center py-8"
    >
      <i class="pi pi-spin pi-spinner text-3xl text-primary" />
    </div>

    <div
      v-else
      class="settings-grid flex flex-col gap-8 mt-4 md:mt-6"
    >
      <div class="settings-card phoenix-card bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
        <h3 class="settings-title text-lg font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100">
          {{ t('platformLegal.title') }}
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="operatorName">{{ t('settings.representative') }}</label>
            <InputText id="operatorName" v-model="settings.operatorName" placeholder="Kubilay Anil" />
          </div>

          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="operatorCompany">{{ t('monitor.companyName') }}</label>
            <InputText id="operatorCompany" v-model="settings.operatorCompany" placeholder="IT-Dienstleistungen Kubilay Anil" />
          </div>

          <div class="field flex flex-col gap-2 md:col-span-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="operatorDetails">{{ t('settings.contentMarkdown') }} (Details / Beschreibung)</label>
            <Textarea id="operatorDetails" v-model="settings.operatorDetails" rows="4" placeholder="Entwicklung, Vertrieb und Betrieb..." />
          </div>

          <div class="md:col-span-2 grid grid-cols-4 gap-4">
            <div class="field flex flex-col gap-2 col-span-3">
              <label class="font-bold uppercase text-xs text-slate-500" for="street">{{ t('settings.street') }}</label>
              <InputText id="street" v-model="settings.operatorStreet" placeholder="Kreuzstr." />
            </div>
            <div class="field flex flex-col gap-2 col-span-1">
              <label class="font-bold uppercase text-xs text-slate-500" for="houseNumber">{{ t('settings.number') }}</label>
              <InputText id="houseNumber" v-model="settings.operatorHouseNumber" placeholder="19" />
            </div>
          </div>

          <div class="md:col-span-2 grid grid-cols-4 gap-4">
            <div class="field flex flex-col gap-2 col-span-1">
              <label class="font-bold uppercase text-xs text-slate-500" for="zipCode">{{ t('settings.zipCode') }}</label>
              <InputText id="zipCode" v-model="settings.operatorZipCode" placeholder="89160" />
            </div>
            <div class="field flex flex-col gap-2 col-span-3">
              <label class="font-bold uppercase text-xs text-slate-500" for="city">{{ t('settings.city') }}</label>
              <InputText id="city" v-model="settings.operatorCity" placeholder="Dornstadt" />
            </div>
          </div>

          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="email">{{ t('auth.email') }}</label>
            <InputText id="email" v-model="settings.operatorEmail" placeholder="kubilay.anil@codingcube.de" />
          </div>

          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="phone">{{ t('settings.phone') }}</label>
            <InputText id="phone" v-model="settings.operatorPhone" placeholder="01627895106" />
          </div>

          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="profession">Berufsbezeichnung</label>
            <InputText id="profession" v-model="settings.profession" placeholder="Softwareentwickler" />
          </div>

          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="country">Land</label>
            <InputText id="country" v-model="settings.country" placeholder="Deutschland" />
          </div>

          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="taxId">{{ t('settings.taxId') }} <span class="text-xs text-slate-400 normal-case font-normal">({{ t('settings.optional') }})</span></label>
            <InputText id="taxId" v-model="settings.taxId" />
          </div>

          <div class="field flex flex-col gap-2">
            <label class="font-bold uppercase text-xs text-slate-500" for="vatId">{{ t('settings.vatId') }} <span class="text-xs text-slate-400 normal-case font-normal">({{ t('settings.optional') }})</span></label>
            <InputText id="vatId" v-model="settings.vatId" />
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <Button
            severity="primary"
            :label="t('settings.saveLegalNotice')"
            icon="pi pi-save"
            :loading="saving"
            @click="updateSettings"
          />
        </div>
      </div>

      <div class="settings-card phoenix-card bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
        <h3 class="settings-title text-lg font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100">
          {{ t('settings.privacyTitle') }}
        </h3>
        <div class="flex flex-col gap-4">
          <p class="text-sm text-slate-600">
            {{ t('settings.privacyNote') }}
          </p>

          <div
            v-if="settings.privacyPolicyPdfPath"
            class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <i class="pi pi-file-pdf text-red-500 text-2xl" />
              <div>
                <span class="font-bold text-slate-700">{{ t('settings.currentDocument') }}</span>
                <p class="text-xs text-slate-500">
                  {{ settings.privacyPolicyPdfPath }}
                </p>
              </div>
            </div>
            <Button
              icon="pi pi-download"
              severity="secondary"
              variant="text"
              rounded
              @click="downloadPlatformPrivacyPolicy()"
            />
          </div>
          <FileUpload
            mode="basic"
            name="file"
            accept="application/pdf"
            :auto="true"
            custom-upload
            :choose-label="settings.privacyPolicyPdfPath ? t('settings.replacePdf') : t('settings.uploadPdf')"
            :disabled="uploading"
            class="w-full"
            @uploader="onUpload"
          />
          <div
            v-if="uploading"
            class="text-center mt-2"
          >
            <i class="pi pi-spin pi-spinner mr-2" /> {{ t('settings.uploading') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.platform-settings-tab {
  .settings-title {
    font-family: 'Barlow Condensed', sans-serif;
  }
  .field {
    label {
      font-family: 'Barlow Condensed', sans-serif;
    }
  }
}
</style>
