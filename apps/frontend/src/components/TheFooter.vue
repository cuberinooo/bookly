<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const showLegalNotice = ref(false);
const legalSettings = ref<any>(null);

async function fetchLegalSettings() {
    try {
        const response = await api.get('/legal-settings');
        legalSettings.value = response.data;
    } catch (e) {
        console.error('Failed to load legal settings');
    }
}

const privacyPolicyUrl = computed(() => {
    return `${api.defaults.baseURL}/legal-settings/privacy-policy/download`;
});

async function onClickShow() {
  await fetchLegalSettings();
  showLegalNotice.value = true;
}

const renderedLegalNotice = computed(() => {
    if (!legalSettings.value?.legalNoticeMarkdown) return null;
    const rawHtml = marked.parse(legalSettings.value.legalNoticeMarkdown) as string;
    return DOMPurify.sanitize(rawHtml);
});

onMounted(fetchLegalSettings);
</script>

<template>
  <footer class="app-footer">
    <div class="footer-content">
      <div class="footer-section">
        <h4 class="footer-title">Phoenix Athletics</h4>
        <p class="footer-text">
          Visit our official website for information on
          <a href="https://phoenix-athletics.de/" target="_blank" rel="noopener">Satzung, Beitragsordnung</a> and more.
        </p>
        <a href="https://phoenix-athletics.de/" target="_blank" rel="noopener" class="footer-link">
          phoenix-athletics.de <i class="pi pi-external-link"></i>
        </a>
      </div>

      <div class="footer-section">
        <h4 class="footer-title">Legal</h4>
        <ul class="footer-list">
          <li v-if="legalSettings?.privacyPolicyPdfPath">
            <a :href="privacyPolicyUrl" download class="footer-link">
              <i class="pi pi-download"></i> Privacy Policy (Datenschutz)
            </a>
          </li>
          <li>
            <a href="javascript:void(0)" @click="onClickShow" class="footer-link">
              <i class="pi pi-info-circle"></i> Legal Notice (Impressum)
            </a>
          </li>
        </ul>
      </div>

      <div class="footer-section">
        <h4 class="footer-title">Hosting</h4>
        <p class="footer-text">
          This App is hosted by
          <a href="https://codingcube.de/" target="_blank" rel="noopener" class="footer-link highlight">codingcube</a>
        </p>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; {{ new Date().getFullYear() }} Phoenix Athletics. All rights reserved.</p>
    </div>

    <Dialog v-model:visible="showLegalNotice" header="Legal Notice" :modal="true" class="w-full max-w-2xl">
      <div class="legal-notice-content">
        <div v-if="legalSettings?.legalNoticeCompanyName">
          <section>
            <h3 class="primary-text">Angaben gemäß § 5 TMG</h3>
            <p>
              {{ legalSettings.legalNoticeRepresentative }}<br v-if="legalSettings.legalNoticeRepresentative" />
              {{ legalSettings.legalNoticeCompanyName }}<br />
              {{ legalSettings.legalNoticeStreet }} {{ legalSettings.legalNoticeHouseNumber }}<br />
              {{ legalSettings.legalNoticeZipCode }} {{ legalSettings.legalNoticeCity }}
            </p>
          </section>

          <section v-if="legalSettings.legalNoticeEmail || legalSettings.legalNoticePhone" class="mt-4">
            <h3 class="primary-text">Kontakt</h3>
            <p>
              <span v-if="legalSettings.legalNoticePhone">Telefon: {{ legalSettings.legalNoticePhone }}<br /></span>
              <span v-if="legalSettings.legalNoticeEmail">E-Mail: <a :href="'mailto:' + legalSettings.legalNoticeEmail">{{ legalSettings.legalNoticeEmail }}</a></span>
            </p>
          </section>

          <section v-if="legalSettings.legalNoticeTaxId || legalSettings.legalNoticeVatId" class="mt-4">
            <h3 class="primary-text">Steuern</h3>
            <p>
              <span v-if="legalSettings.legalNoticeTaxId">Steuernummer: {{ legalSettings.legalNoticeTaxId }}<br /></span>
              <span v-if="legalSettings.legalNoticeVatId">Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz: {{ legalSettings.legalNoticeVatId }}</span>
            </p>
          </section>
          <div v-if="renderedLegalNotice" class="markdown-content" v-html="renderedLegalNotice"></div>
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
      </div>
      <template #footer>
        <Button severity="primary" label="Close" icon="pi pi-check" @click="showLegalNotice = false" autofocus />
      </template>
    </Dialog>
  </footer>
</template>

<style scoped lang="scss">
.app-footer {
  background-color: var(--bg-primary-color);
  color: white;
  padding: 4rem 2rem 2rem;
  margin-top: 4rem;
  border-top: 4px solid var(--primary-color);
}

.footer-content {
  max-width: 1400px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 3rem;
  margin-bottom: 3rem;
}

.footer-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.footer-title {
  color: var(--primary-color);
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0;
}

.footer-text {
  font-size: 0.95rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.7);
  margin: 0;

  a {
    color: white;
    text-decoration: underline;
    &:hover {
      color: var(--primary-color);
    }
  }
}

.footer-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.footer-link {
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  font-size: 0.95rem;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;

  &:hover {
    color: var(--primary-color);
    transform: translateX(4px);
  }

  &.highlight {
    font-weight: 700;
    color: var(--primary-color);
    text-decoration: none;
    &:hover {
      text-decoration: underline;
    }
  }
}

.footer-bottom {
  max-width: 1400px;
  margin: 0 auto;
  padding-top: 2rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  text-align: center;
  color: rgba(255, 255, 255, 0.5);
  font-size: 0.85rem;
}

.legal-notice-content {
  color: white;
  line-height: 1.6;

  h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
  }

  p {
    color: rgba(255, 255, 255, 0.8);
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
    :deep(h1), :deep(h2), :deep(h3) {
      color: var(--primary-color);
      margin-top: 1.5rem;
      margin-bottom: 0.75rem;
    }
    :deep(p) {
      margin-bottom: 1rem;
    }
    :deep(ul) {
      margin-left: 1.5rem;
      margin-bottom: 1rem;
      list-style-type: disc;
    }
    :deep(a) {
      color: var(--primary-color);
      text-decoration: underline;
    }
  }
}

@media (max-width: 768px) {
  .app-footer {
    padding: 3rem 1.5rem 1.5rem;
  }

  .footer-content {
    gap: 2rem;
  }
}
</style>
