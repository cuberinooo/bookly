<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';

const showLegalNotice = ref(false);
const settings = ref<any>(null);

async function fetchSettings() {
    try {
        const response = await api.get('/settings');
        settings.value = response.data;
    } catch (e) {
        console.error('Failed to load footer settings');
    }
}

const privacyPolicyUrl = computed(() => {
    if (!settings.value?.privacyPolicyPdfPath) return '/privacy-policy.pdf'; // Fallback
    const baseURL = import.meta.env.VITE_API_URL || '';
    return settings.value.privacyPolicyPdfPath.startsWith('http') 
        ? settings.value.privacyPolicyPdfPath 
        : baseURL + settings.value.privacyPolicyPdfPath;
});

onMounted(fetchSettings);
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
          <li>
            <a :href="privacyPolicyUrl" download class="footer-link">
              <i class="pi pi-download"></i> Privacy Policy (Datenschutz)
            </a>
          </li>
          <li>
            <a href="javascript:void(0)" @click="showLegalNotice = true" class="footer-link">
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
      <div v-if="settings" class="legal-notice-content">
        <section v-if="settings.legalNoticeCompanyName">
          <h3 class="text-primary">Angaben gemäß § 5 TMG</h3>
          <p>
            {{ settings.legalNoticeRepresentative }}<br v-if="settings.legalNoticeRepresentative" />
            {{ settings.legalNoticeCompanyName }}<br />
            {{ settings.legalNoticeStreet }} {{ settings.legalNoticeHouseNumber }}<br />
            {{ settings.legalNoticeZipCode }} {{ settings.legalNoticeCity }}
          </p>
        </section>

        <section v-if="settings.legalNoticeEmail || settings.legalNoticePhone" class="mt-4">
          <h3 class="text-primary">Kontakt</h3>
          <p>
            <span v-if="settings.legalNoticePhone">Telefon: {{ settings.legalNoticePhone }}<br /></span>
            <span v-if="settings.legalNoticeEmail">E-Mail: <a :href="'mailto:' + settings.legalNoticeEmail">{{ settings.legalNoticeEmail }}</a></span>
          </p>
        </section>

        <section v-if="settings.legalNoticeTaxId || settings.legalNoticeVatId" class="mt-4">
          <h3 class="text-primary">Steuern</h3>
          <p>
            <span v-if="settings.legalNoticeTaxId">Steuernummer: {{ settings.legalNoticeTaxId }}<br /></span>
            <span v-if="settings.legalNoticeVatId">Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz: {{ settings.legalNoticeVatId }}</span>
          </p>
        </section>

        <section class="mt-4">
          <h3 class="text-primary">EU-Streitschlichtung</h3>
          <p>
            Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
            <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a>.<br />
            Unsere E-Mail-Adresse finden Sie oben im Impressum.
          </p>
        </section>

        <section class="mt-4">
          <h3 class="text-primary">Verbraucherstreitbeilegung/Universalschlichtungsstelle</h3>
          <p>
            Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
          </p>
        </section>
      </div>
      <div v-else class="text-center py-8">
        <p>Legal information not yet configured.</p>
      </div>
      <template #footer>
        <Button label="Close" icon="pi pi-check" @click="showLegalNotice = false" autofocus />
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
