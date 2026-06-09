<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import {downloadPrivacyPolicy} from "../services/download";
import MarkdownPreview from './MarkdownPreview.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();
const settings = ref({
    legalNoticeRepresentative: '',
    legalNoticeStreet: '',
    legalNoticeHouseNumber: '',
    legalNoticeZipCode: '',
    legalNoticeCity: '',
    legalNoticeEmail: '',
    legalNoticePhone: '',
    legalNoticeTaxId: '',
    legalNoticeVatId: '',
    legalNoticeMarkdown: '',
    termsAndConditionsMarkdown: '',
    privacyPolicyPdfPath: ''
});
const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/admin-settings');
        settings.value = {
            legalNoticeRepresentative: response.data.legalNoticeRepresentative || '',
            legalNoticeStreet: response.data.legalNoticeStreet || '',
            legalNoticeHouseNumber: response.data.legalNoticeHouseNumber || '',
            legalNoticeZipCode: response.data.legalNoticeZipCode || '',
            legalNoticeCity: response.data.legalNoticeCity || '',
            legalNoticeEmail: response.data.legalNoticeEmail || '',
            legalNoticePhone: response.data.legalNoticePhone || '',
            legalNoticeTaxId: response.data.legalNoticeTaxId || '',
            legalNoticeVatId: response.data.legalNoticeVatId || '',
            legalNoticeMarkdown: response.data.legalNoticeMarkdown || '',
            termsAndConditionsMarkdown: response.data.termsAndConditionsMarkdown || '',
            privacyPolicyPdfPath: response.data.privacyPolicyPdfPath || ''
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.loadFailed'), life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/admin-settings', settings.value);
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
        const response = await api.post('/admin-settings/privacy-policy', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        settings.value.privacyPolicyPdfPath = response.data.path;
        toast.add({ severity: 'success', summary: t('app.uploaded'), detail: t('profile.updateSuccess'), life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.uploadFailed'), life: 5000 });
    } finally {
        uploading.value = false;
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="legal-settings-form">
    <div
      v-if="loading"
      class="flex justify-center py-8"
    >
      <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
    </div>

    <div
      v-else
      class="settings-grid flex flex-col gap-8"
    >
      <div class="settings-card phoenix-card">
        <h3 class="settings-title">
          {{ $t('settings.legalNoticeTitle') }}
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="md:col-span-2">
            <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-900 text-sm mb-6">
              <p class="font-bold mb-1">
                {{ $t('settings.markdownEnabled') }}
              </p>
              <p>{{ $t('settings.legalNoticeMarkdownNote') }}</p>
            </div>
            <div class="field">
              <label
                class="secondary-text"
                for="markdown"
              >{{ $t('settings.contentMarkdown') }}</label>
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Textarea
                  id="markdown"
                  v-model="settings.legalNoticeMarkdown"
                  rows="15"
                  :placeholder="$t('settings.legalNoticePlaceholder')"
                  class="w-full font-mono text-sm"
                />
                <MarkdownPreview
                  :content="settings.legalNoticeMarkdown"
                  :title="$t('settings.legalNoticePreview')"
                  :placeholder="$t('settings.legalNoticePlaceholder')"
                />
              </div>
            </div>
          </div>

          <Divider class="md:col-span-2" />

          <div class="md:col-span-2">
            <h4 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-4">
              {{ $t('settings.companyDetails') }}
            </h4>
          </div>

          <div class="field">
            <label
              class="secondary-text"
              for="representative"
            >{{ $t('settings.representative') }}</label>
            <InputText
              id="representative"
              v-model="settings.legalNoticeRepresentative"
              placeholder="Max Mustermann"
            />
          </div>

          <div class="md:col-span-2 grid grid-cols-4 gap-4">
            <div class="field col-span-3">
              <label
                class="secondary-text"
                for="street"
              >{{ $t('settings.street') }}</label>
              <InputText
                id="street"
                v-model="settings.legalNoticeStreet"
                placeholder="Heideweg"
              />
            </div>
            <div class="field col-span-1">
              <label
                class="secondary-text"
                for="houseNumber"
              >{{ $t('settings.number') }}</label>
              <InputText
                id="houseNumber"
                v-model="settings.legalNoticeHouseNumber"
                placeholder="13"
              />
            </div>
          </div>

          <div class="md:col-span-2 grid grid-cols-4 gap-4">
            <div class="field col-span-1">
              <label
                class="secondary-text"
                for="zipCode"
              >{{ $t('settings.zipCode') }}</label>
              <InputText
                id="zipCode"
                v-model="settings.legalNoticeZipCode"
                placeholder="33659"
              />
            </div>
            <div class="field col-span-3">
              <label
                class="secondary-text"
                for="city"
              >{{ $t('settings.city') }}</label>
              <InputText
                id="city"
                v-model="settings.legalNoticeCity"
                placeholder="Bielefeld"
              />
            </div>
          </div>

          <div class="field">
            <label
              class="secondary-text"
              for="email"
            >{{ $t('auth.email') }}</label>
            <InputText
              id="email"
              v-model="settings.legalNoticeEmail"
              placeholder="hello@codingcube.de"
            />
          </div>

          <div class="field">
            <label
              class="secondary-text"
              for="phone"
            >{{ $t('settings.phone') }}</label>
            <InputText
              id="phone"
              v-model="settings.legalNoticePhone"
              placeholder="+49 (0) 176 47325434"
            />
          </div>

          <div class="field">
            <label
              class="secondary-text"
              for="taxId"
            >{{ $t('settings.taxId') }} <span class="text-xs text-slate-400 normal-case font-normal">({{ $t('settings.optional') }})</span></label>
            <InputText
              id="taxId"
              v-model="settings.legalNoticeTaxId"
            />
          </div>

          <div class="field">
            <label
              class="secondary-text"
              for="vatId"
            >{{ $t('settings.vatId') }} <span class="text-xs text-slate-400 normal-case font-normal">({{ $t('settings.optional') }})</span></label>
            <InputText
              id="vatId"
              v-model="settings.legalNoticeVatId"
              placeholder="DE365333557"
            />
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <Button
            severity="primary"
            :label="$t('settings.saveLegalNotice')"
            icon="pi pi-save"
            :loading="saving"
            @click="updateSettings"
          />
        </div>
      </div>

      <div class="settings-card phoenix-card">
        <h3 class="settings-title">
          {{ $t('settings.termsTitle') }}
        </h3>
        <div class="flex flex-col gap-4">
          <p class="text-sm text-slate-600">
            {{ $t('settings.termsNote') }}
          </p>
          <div class="field">
            <label
              class="secondary-text"
              for="termsMarkdown"
            >{{ $t('settings.contentMarkdown') }}</label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Textarea
                id="termsMarkdown"
                v-model="settings.termsAndConditionsMarkdown"
                rows="15"
                placeholder="# Allgemeine Geschäftsbedingungen (AGB)&#10;&#10;1. Geltungsbereich..."
                class="w-full font-mono text-sm"
              />
              <MarkdownPreview
                :content="settings.termsAndConditionsMarkdown"
                :title="$t('settings.termsPreview')"
                :placeholder="$t('settings.termsPlaceholder')"
              />
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <Button
              severity="primary"
              :label="$t('settings.saveTerms')"
              icon="pi pi-save"
              :loading="saving"
              @click="updateSettings"
            />
          </div>
        </div>
      </div>

      <div class="settings-card phoenix-card">
        <h3 class="settings-title">
          {{ $t('settings.privacyTitle') }}
        </h3>
        <div class="flex flex-col gap-4">
          <p class="text-sm text-slate-600">
            {{ $t('settings.privacyNote') }}
          </p>

          <div
            v-if="settings.privacyPolicyPdfPath"
            class="p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <i class="pi pi-file-pdf text-red-500 text-2xl" />
              <div>
                <span class="font-bold text-slate-700">{{ $t('settings.currentDocument') }}</span>
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
              @click="downloadPrivacyPolicy()"
            />
          </div>
          <FileUpload
            mode="basic"
            name="file"
            accept="application/pdf"
            :auto="true"
            custom-upload
            :choose-label="settings.privacyPolicyPdfPath ? $t('settings.replacePdf') : $t('settings.uploadPdf')"
            :disabled="uploading"
            class="w-full"
            @uploader="onUpload"
          />
          <div
            v-if="uploading"
            class="text-center mt-2"
          >
            <i class="pi pi-spin pi-spinner mr-2" /> {{ $t('settings.uploading') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>

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

    :deep(.p-inputtext) {
      width: 100%;
    }
}

.no-underline {
    text-decoration: none;
}
</style>
