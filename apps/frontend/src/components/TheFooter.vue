<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { settingsStore } from '../store/settings';
import api from '../services/api';
import { downloadPrivacyPolicy } from "../services/download";
import LegalDialog from "./LegalDialog.vue";

const showLegalDialog = ref(false);
const dialogType = ref<'terms' | 'legal'>('legal');
const legalSettings = ref<any>(null);

async function fetchSettings() {
    try {
        const response = await api.get('/admin-settings');
        legalSettings.value = response.data;
    } catch (e) {
        console.error('Failed to load legal settings');
    }
}

async function onClickShowLegal() {
  await fetchSettings();
  dialogType.value = 'legal';
  showLegalDialog.value = true;
}

async function onClickShowTerms() {
  await fetchSettings();
  dialogType.value = 'terms';
  showLegalDialog.value = true;
}

onMounted(fetchSettings);
</script>

<template>
  <footer class="app-footer">
    <div class="footer-content">
      <div class="footer-section">
        <h4 class="footer-title">{{ settingsStore.companyName }}</h4>
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
            <a href="javascript:void(0)"
               @click="downloadPrivacyPolicy()"
               class="footer-link">
              <i class="pi pi-download"></i> Privacy Policy (Datenschutz)
            </a>
          </li>
          <li>
            <a href="javascript:void(0)" @click="onClickShowLegal" class="footer-link">
              <i class="pi pi-info-circle"></i> Legal Notice (Impressum)
            </a>
          </li>
          <li>
            <a href="javascript:void(0)" @click="onClickShowTerms" class="footer-link">
              <i class="pi pi-file-text"></i> Terms & Conditions (AGB)
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
      <p>&copy; {{ new Date().getFullYear() }} {{ settingsStore.companyName }}. All rights reserved.</p>
    </div>

    <LegalDialog
      v-model:visible="showLegalDialog"
      :type="dialogType"
      :data="legalSettings"
      :company-name="settingsStore.companyName"
    />
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
