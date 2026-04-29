<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const settings = ref({
    legalNoticeCompanyName: '',
    legalNoticeRepresentative: '',
    legalNoticeStreet: '',
    legalNoticeHouseNumber: '',
    legalNoticeZipCode: '',
    legalNoticeCity: '',
    legalNoticeEmail: '',
    legalNoticePhone: '',
    legalNoticeTaxId: '',
    legalNoticeVatId: '',
    privacyPolicyPdfPath: ''
});
const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/settings');
        settings.value = {
            legalNoticeCompanyName: response.data.legalNoticeCompanyName || '',
            legalNoticeRepresentative: response.data.legalNoticeRepresentative || '',
            legalNoticeStreet: response.data.legalNoticeStreet || '',
            legalNoticeHouseNumber: response.data.legalNoticeHouseNumber || '',
            legalNoticeZipCode: response.data.legalNoticeZipCode || '',
            legalNoticeCity: response.data.legalNoticeCity || '',
            legalNoticeEmail: response.data.legalNoticeEmail || '',
            legalNoticePhone: response.data.legalNoticePhone || '',
            legalNoticeTaxId: response.data.legalNoticeTaxId || '',
            legalNoticeVatId: response.data.legalNoticeVatId || '',
            privacyPolicyPdfPath: response.data.privacyPolicyPdfPath || ''
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load settings', life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/settings', settings.value);
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Legal information saved', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update settings', life: 5000 });
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
        const response = await api.post('/settings/privacy-policy', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        settings.value.privacyPolicyPdfPath = response.data.path;
        toast.add({ severity: 'success', summary: 'Uploaded', detail: 'Privacy Policy PDF updated successfully', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to upload PDF', life: 5000 });
    } finally {
        uploading.value = false;
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="legal-settings-form">
    <div v-if="loading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
    </div>

    <div v-else class="settings-grid flex flex-col gap-8">
      <div class="settings-card phoenix-card">
        <h3 class="settings-title">Legal Notice (Impressum)</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="field">
            <label class="secondary-text" for="companyName">Company Name / Name</label>
            <InputText id="companyName" v-model="settings.legalNoticeCompanyName" placeholder="Max Mustermann" />
          </div>

          <div class="field">
            <label class="secondary-text" for="representative">Representative</label>
            <InputText id="representative" v-model="settings.legalNoticeRepresentative" placeholder="Max Mustermann" />
          </div>

          <div class="md:col-span-2 grid grid-cols-4 gap-4">
            <div class="field col-span-3">
              <label class="secondary-text" for="street">Street</label>
              <InputText id="street" v-model="settings.legalNoticeStreet" placeholder="Heideweg" />
            </div>
            <div class="field col-span-1">
              <label class="secondary-text" for="houseNumber">Number</label>
              <InputText id="houseNumber" v-model="settings.legalNoticeHouseNumber" placeholder="13" />
            </div>
          </div>

          <div class="md:col-span-2 grid grid-cols-4 gap-4">
            <div class="field col-span-1">
              <label class="secondary-text" for="zipCode">Zip Code (PLZ)</label>
              <InputText id="zipCode" v-model="settings.legalNoticeZipCode" placeholder="33659" />
            </div>
            <div class="field col-span-3">
              <label class="secondary-text" for="city">Location (City)</label>
              <InputText id="city" v-model="settings.legalNoticeCity" placeholder="Bielefeld" />
            </div>
          </div>

          <div class="field">
            <label class="secondary-text" for="email">Email</label>
            <InputText id="email" v-model="settings.legalNoticeEmail" placeholder="hello@codingcube.de" />
          </div>

          <div class="field">
            <label class="secondary-text" for="phone">Phone</label>
            <InputText id="phone" v-model="settings.legalNoticePhone" placeholder="+49 (0) 176 47325434" />
          </div>

          <div class="field">
            <label class="secondary-text" for="taxId">Tax ID (Steuernummer) <span class="text-xs text-slate-400 normal-case font-normal">(Optional)</span></label>
            <InputText id="taxId" v-model="settings.legalNoticeTaxId" />
          </div>

          <div class="field">
            <label class="secondary-text" for="vatId">VAT ID (USt-IdNr.) <span class="text-xs text-slate-400 normal-case font-normal">(Optional)</span></label>
            <InputText id="vatId" v-model="settings.legalNoticeVatId" placeholder="DE365333557" />
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <Button severity="primary" label="Save Legal Notice" icon="pi pi-save" :loading="saving" @click="updateSettings" />
        </div>
      </div>

      <div class="settings-card phoenix-card">
        <h3 class="settings-title">Privacy Policy (Datenschutz)</h3>
        <div class="flex flex-col gap-4">
          <p class="text-sm text-slate-600">
            Upload the official Privacy Policy PDF that users can download from the footer.
          </p>

          <div v-if="settings.privacyPolicyPdfPath" class="p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
            <div class="flex items-center gap-3">
              <i class="pi pi-file-pdf text-red-500 text-2xl"></i>
              <div>
                <span class="font-bold text-slate-700">Current Document</span>
                <p class="text-xs text-slate-500">{{ settings.privacyPolicyPdfPath }}</p>
              </div>
            </div>
            <a :href="settings.privacyPolicyPdfPath" target="_blank" class="p-button p-button-sm p-button-secondary no-underline">
              View Current
            </a>
          </div>

          <FileUpload
            mode="basic"
            name="file"
            accept="application/pdf"
            :auto="true"
            custom-upload
            @uploader="onUpload"
            :choose-label="settings.privacyPolicyPdfPath ? 'Replace PDF' : 'Upload PDF'"
            :disabled="uploading"
            class="w-full"
          />
          <div v-if="uploading" class="text-center mt-2">
            <i class="pi pi-spin pi-spinner mr-2"></i> Uploading...
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.settings-card {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.settings-title {
    @apply text-lg font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100;
    font-family: 'Barlow Condensed', sans-serif;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;

    label {
        @apply font-bold uppercase text-xs;
        font-family: 'Barlow Condensed', sans-serif;
    }
}

.no-underline {
    text-decoration: none;
}
</style>
