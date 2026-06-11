<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import api from '../services/api';
import { downloadPlatformPrivacyPolicy } from '../services/download';

const { t } = useI18n();

const props = defineProps<{
  visible: boolean;
}>();

const emit = defineEmits(['update:visible']);

const show = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
});

const settings = ref<any>(null);
const loading = ref(false);

async function fetchPlatformSettings() {
  loading.value = true;
  try {
    const response = await api.get('/platform-settings');
    settings.value = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
  } catch (error) {
    console.error('Failed to load platform settings', error);
  } finally {
    loading.value = false;
  }
}

watch(() => props.visible, (newVal) => {
  if (newVal && !settings.value) {
    fetchPlatformSettings();
  }
});
</script>

<template>
  <Dialog
    v-model:visible="show"
    :header="t('platformLegal.title')"
    :modal="true"
    class="w-full max-w-lg"
    :breakpoints="{'960px': '75vw', '640px': '90vw'}"
  >
    <div class="platform-legal-content text-slate-300">
      <div v-if="loading" class="flex justify-center py-6">
        <i class="pi pi-spin pi-spinner text-2xl text-primary" />
      </div>
      <div v-else>
        <section>
          <h3 class="font-bold text-lg text-primary mb-2">
            {{ t('platformLegal.operatorTitle') }}
          </h3>
          <p class="leading-relaxed mb-4">
            {{ settings?.operatorName || t('platformLegal.operatorName') }}<br />
            {{ settings?.operatorCompany || t('platformLegal.operatorCompany') }}<br />
            {{ settings?.operatorDetails || t('platformLegal.operatorDetails') }}<br />
            <span v-if="settings?.operatorStreet">
              {{ settings.operatorStreet }} {{ settings.operatorHouseNumber }}, {{ settings.operatorZipCode }} {{ settings.operatorCity }}
            </span>
            <span v-else>
              {{ t('platformLegal.operatorAddress') }}
            </span>
          </p>
        </section>

        <section class="mt-4">
          <h3 class="font-bold text-lg text-primary mb-2">
            {{ t('platformLegal.contactTitle') }}
          </h3>
          <p class="leading-relaxed mb-2">
            <span v-if="settings?.operatorPhone">
              {{ t('settings.phone') }}: {{ settings.operatorPhone }}
            </span>
            <span v-else>
              {{ t('platformLegal.phone') }}
            </span>
            <br />
            {{ t('platformLegal.email') }}
            <a :href="'mailto:' + (settings?.operatorEmail || 'kubilay.anil@codingcube.de')" class="text-primary hover:underline">
              {{ settings?.operatorEmail || 'kubilay.anil@codingcube.de' }}
            </a>
          </p>
        </section>

        <section class="mt-4">
          <h3 class="font-bold text-lg text-primary mb-2">
            {{ t('platformLegal.professionTitle') }}
          </h3>
          <p class="leading-relaxed mb-4">
            {{ settings?.profession ? 'Berufsbezeichnung: ' + settings.profession : t('platformLegal.profession') }}<br />
            {{ settings?.country ? 'Verliehen in: ' + settings.country : t('platformLegal.country') }}
          </p>
        </section>

        <section v-if="settings?.taxId || settings?.vatId" class="mt-4">
          <h3 class="font-bold text-lg text-primary mb-2">
            {{ t('settings.steuern') }}
          </h3>
          <p class="leading-relaxed mb-4">
            <span v-if="settings.taxId">{{ t('settings.steuernummer') }}: {{ settings.taxId }}<br /></span>
            <span v-if="settings.vatId">{{ t('settings.vatIdNote') }}: {{ settings.vatId }}</span>
          </p>
        </section>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end">
        <Button
          :label="t('app.close')"
          severity="primary"
          icon="pi pi-check"
          class="btn-primary"
          @click="show = false"
        />
      </div>
    </template>
  </Dialog>
</template>

<style scoped lang="scss">
.platform-legal-content {
  line-height: 1.6;

  h3 {
    color: var(--primary-color) !important;
  }

  p {
    margin-bottom: 1rem;
  }

  a {
    color: var(--primary-color);
    text-decoration: none;
    &:hover {
      text-decoration: underline;
    }
  }
}
</style>
