<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useSettingsStore } from '../store/useSettingsStore';
import { useI18n } from 'vue-i18n';
import api from '../services/api';
import { downloadPrivacyPolicy } from "../services/download";
import LegalDialog from "./LegalDialog.vue";

const { t } = useI18n();
const showLegalDialog = ref(false);
const dialogType = ref<'terms' | 'legal'>('legal');
const legalSettings = ref<any>(null);
const settingsStore = useSettingsStore();

// Mobile accordion state
const expandedSections = ref<{ [key: string]: boolean }>({
  legal: false,
  hosting: false
});

function toggleSection(section: string) {
  if (window.innerWidth <= 768) {
    expandedSections.value[section] = !expandedSections.value[section];
  }
}

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
        <h4
          class="footer-title collapsible-trigger"
          @click="toggleSection('legal')"
        >
          {{ t('footer.legal') }}
          <i :class="['pi', expandedSections.legal ? 'pi-chevron-up' : 'pi-chevron-down', 'mobile-only']" />
        </h4>
        <ul :class="['footer-list', { 'mobile-hidden': !expandedSections.legal }]">
          <li v-if="legalSettings?.privacyPolicyPdfPath">
            <a
              href="javascript:void(0)"
              class="footer-link"
              @click="downloadPrivacyPolicy()"
            >
              <i class="pi pi-download" /> {{ t('settings.privacyPolicy') }}
            </a>
          </li>
          <li>
            <a
              href="javascript:void(0)"
              class="footer-link"
              @click="onClickShowLegal"
            >
              <i class="pi pi-info-circle" /> {{ t('settings.legalNotice') }}
            </a>
          </li>
          <li>
            <a
              href="javascript:void(0)"
              class="footer-link"
              @click="onClickShowTerms"
            >
              <i class="pi pi-file-text" /> {{ t('settings.termsAndConditions') }}
            </a>
          </li>
        </ul>
      </div>

      <div class="footer-section">
        <h4
          class="footer-title collapsible-trigger"
          @click="toggleSection('hosting')"
        >
          {{ t('footer.hosting') }}
          <i :class="['pi', expandedSections.hosting ? 'pi-chevron-up' : 'pi-chevron-down', 'mobile-only']" />
        </h4>
        <div :class="['hosting-content', { 'mobile-hidden': !expandedSections.hosting }]">
          <p class="footer-text">
            <a
              href="https://booklyfit.de"
              target="_blank"
              rel="noopener"
              class="footer-link highlight"
            >{{ t('footer.hostedBy') }}</a>
          </p>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; {{ new Date().getFullYear() }} {{ settingsStore.companyName }}.</p>
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
  padding: 4rem 4rem 4rem;
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
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.collapsible-trigger {
  cursor: default;
}

.mobile-only {
  display: none;
}

.desktop-only {
    @media (max-width: 768px) {
        display: none !important;
    }
}

.footer-tagline {
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.5);
  margin-top: -0.5rem;
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
    padding: 2rem 1.5rem 5rem;
    margin-top: 2rem;
  }

  .footer-content {
    grid-template-columns: 1fr;
    gap: 0;
    margin-bottom: 1.5rem;
  }

  .footer-section {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding: 1rem 0;
    gap: 0;

    &.brand-section {
      display: none;
    }
  }

  .footer-title {
    font-size: 1.1rem;
  }

  .collapsible-trigger {
    cursor: pointer;
    padding: 0.5rem 0;
  }

  .mobile-only {
    display: block;
    font-size: 0.8rem;
    opacity: 0.7;
  }

  .footer-list, .hosting-content {
    max-height: 500px;
    overflow: hidden;
    transition: all 0.3s ease-in-out;
    padding-top: 0.5rem;

    &.mobile-hidden {
      max-height: 0;
      padding-top: 0;
      opacity: 0;
      pointer-events: none;
    }
  }

  .footer-link {
    padding: 0.5rem 0;
  }

  .footer-bottom {
    padding-top: 1.5rem;
  }
}
</style>
