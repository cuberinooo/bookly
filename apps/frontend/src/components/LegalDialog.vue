<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

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
  return props.type === 'terms' ? `Terms & Conditions - ${props.companyName}` : `Legal Notice - ${props.companyName}`;
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
            <h3 class="primary-text">Angaben gemäß § 5 TMG</h3>
            <p>
              {{ data.legalNoticeRepresentative }}<br v-if="data.legalNoticeRepresentative" />
              {{ data.legalNoticeStreet }} {{ data.legalNoticeHouseNumber }}<br />
              {{ data.legalNoticeZipCode }} {{ data.legalNoticeCity }}
            </p>
          </section>

          <section v-if="data.legalNoticeEmail || data.legalNoticePhone" class="mt-4">
            <h3 class="primary-text">Kontakt</h3>
            <p>
              <span v-if="data.legalNoticePhone">Telefon: {{ data.legalNoticePhone }}<br /></span>
              <span v-if="data.legalNoticeEmail">E-Mail: <a :href="'mailto:' + data.legalNoticeEmail">{{ data.legalNoticeEmail }}</a></span>
            </p>
          </section>

          <section v-if="data.legalNoticeTaxId || data.legalNoticeVatId" class="mt-4">
            <h3 class="primary-text">Steuern</h3>
            <p>
              <span v-if="data.legalNoticeTaxId">Steuernummer: {{ data.legalNoticeTaxId }}<br /></span>
              <span v-if="data.legalNoticeVatId">Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz: {{ data.legalNoticeVatId }}</span>
            </p>
          </section>
        </div>
        <div v-else>
          <!-- Placeholders -->
          <section>
            <h3 class="primary-text">Angaben gemäß § 5 TMG</h3>
            <p>
              [Name/Company Name]<br />
              [Representative]<br />
              [Street] [Number]<br />
              [Zip Code] [City]
            </p>
          </section>
          <section class="mt-4">
            <h3 class="primary-text">Kontakt</h3>
            <p>
              Telefon: [Phone Number]<br />
              E-Mail: [Email Address]
            </p>
          </section>
        </div>
      </template>

      <div v-if="renderedMarkdown" class="markdown-content mt-6" v-html="renderedMarkdown"></div>
      <div v-else-if="type === 'terms' && !renderedMarkdown" class="text-center py-8">
        <p class="text-slate-500 italic">No terms and conditions have been defined yet.</p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <p v-if="type === 'terms'" class="text-xs text-slate-500 italic">Please read carefully before accepting.</p>
        <div v-else></div>
        <Button
          label="Close"
          severity="primary"
          icon="pi pi-check"
          @click="show = false"
          class="btn-primary"
          autofocus
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
      color: var(--text-primary-color);
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 0.5rem;
    }

    :deep(h2) {
      font-size: 1.25rem;
      font-weight: 700;
      margin-top: 1.5rem;
      margin-bottom: 0.75rem;
      color: var(--text-primary-color);
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
</style>
