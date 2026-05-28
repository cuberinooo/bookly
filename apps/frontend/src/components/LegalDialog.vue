<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
  visible: boolean;
  type: 'terms' | 'legal';
  data: any;
  companyName: string;
}>();

const emit = defineEmits(['update:visible']);

const show = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
});

const header = computed(() => {
  return props.type === 'terms' 
    ? t('settings.termsHeader', { company: props.companyName }) 
    : t('settings.legalHeader', { company: props.companyName });
});

const renderedMarkdown = computed(() => {
  const markdown = props.type === 'terms'
      ? props.data?.termsAndConditionsMarkdown
      : props.data?.legalNoticeMarkdown;

  if (!markdown) return null;
  return DOMPurify.sanitize(marked.parse(markdown) as string);
});
</script>

<template>
  <Dialog
    v-model:visible="show"
    :header="header"
    :modal="true"
    class="w-full max-w-3xl"
    :breakpoints="{'960px': '75vw', '640px': '90vw'}"
  >
    <div class="legal-dialog-content">
      <template v-if="type === 'legal'">
        <div v-if="data?.legalNoticeRepresentative">
          <section>
            <h3 class="primary-text">
              {{ $t('settings.angabenTmg') }}
            </h3>
            <p>
              {{ data.legalNoticeRepresentative }}<br v-if="data.legalNoticeRepresentative">
              {{ data.legalNoticeStreet }} {{ data.legalNoticeHouseNumber }}<br>
              {{ data.legalNoticeZipCode }} {{ data.legalNoticeCity }}
            </p>
          </section>

          <section
            v-if="data.legalNoticeEmail || data.legalNoticePhone"
            class="mt-4"
          >
            <h3 class="primary-text">
              {{ $t('settings.kontakt') }}
            </h3>
            <p>
              <span v-if="data.legalNoticePhone">{{ $t('settings.telefon') }} {{ data.legalNoticePhone }}<br></span>
              <span v-if="data.legalNoticeEmail">{{ $t('settings.email') }} <a :href="'mailto:' + data.legalNoticeEmail">{{ data.legalNoticeEmail }}</a></span>
            </p>
          </section>

          <section
            v-if="data.legalNoticeTaxId || data.legalNoticeVatId"
            class="mt-4"
          >
            <h3 class="primary-text">
              {{ $t('settings.steuern') }}
            </h3>
            <p>
              <span v-if="data.legalNoticeTaxId">{{ $t('settings.steuernummer') }} {{ data.legalNoticeTaxId }}<br></span>
              <span v-if="data.legalNoticeVatId">{{ $t('settings.vatIdNote') }} {{ data.legalNoticeVatId }}</span>
            </p>
          </section>
        </div>
        <div v-else>
          <!-- Placeholders -->
          <section>
            <h3 class="primary-text">
              {{ $t('settings.angabenTmg') }}
            </h3>
            <p>
              [{{ $t('admin.users.athleteName') }}]<br>
              [{{ $t('settings.representative') }}]<br>
              [{{ $t('settings.street') }}] [{{ $t('settings.number') }}]<br>
              [{{ $t('settings.zipCode') }}] [{{ $t('settings.city') }}]
            </p>
          </section>
          <section class="mt-4">
            <h3 class="primary-text">
              {{ $t('settings.kontakt') }}
            </h3>
            <p>
              {{ $t('settings.telefon') }} [{{ $t('settings.phone') }}]<br>
              {{ $t('settings.email') }} [{{ $t('auth.email') }}]
            </p>
          </section>
        </div>
      </template>

      <!-- eslint-disable vue/no-v-html -->
      <div
        v-if="renderedMarkdown"
        class="markdown-content mt-6"
        v-html="renderedMarkdown"
      />
      <!-- eslint-enable vue/no-v-html -->
      <div
        v-else-if="type === 'terms' && !renderedMarkdown"
        class="text-center py-8"
      >
        <p class="text-slate-500 italic">
          {{ $t('settings.noTermsDefined') }}
        </p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <p
          v-if="type === 'terms'"
          class="text-xs text-slate-500 italic"
        >
          {{ $t('settings.readCarefully') }}
        </p>
        <div v-else />
        <Button
          :label="$t('app.close')"
          severity="primary"
          icon="pi pi-check"
          class="btn-primary"
          autofocus
          @click="show = false"
        />
      </div>
    </template>
  </Dialog>
</template>

<style scoped lang="scss">
.legal-dialog-content {
  line-height: 1.6;

  h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
  }

  p {
    color: var(--text-secondary-color);
    margin-bottom: 1rem;
  }

  a {
    color: var(--primary-color);
    text-decoration: none;
    &:hover {
      text-decoration: underline;
    }
  }

  .markdown-content {
    :deep(h1) {
      font-size: 1.5rem;
      font-weight: 800;
      margin-bottom: 1rem;
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 0.5rem;
    }

    :deep(h2) {
      font-size: 1.25rem;
      font-weight: 700;
      margin-top: 1.5rem;
      margin-bottom: 0.75rem;
    }

    :deep(p) {
      margin-bottom: 1rem;
      line-height: 1.7;
    }

    :deep(ul) {
      list-style-type: disc;
      padding-left: 1.5rem;
      margin-bottom: 1rem;
    }

    :deep(li) {
      margin-bottom: 0.5rem;
    }

    :deep(strong) {
      font-weight: 700;
    }
  }
}

:deep(h1), :deep(h2), :deep(h3),
:deep(h4), :deep(h5), :deep(h6) {
  color: var(--primary-color) !important;
}
</style>
