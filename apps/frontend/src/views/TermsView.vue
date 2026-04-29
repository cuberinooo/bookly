<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const legalSettings = ref<any>(null);
const loading = ref(true);

async function fetchLegalSettings() {
    try {
        const response = await api.get('/legal-settings');
        legalSettings.value = response.data;
    } catch (e) {
        console.error('Failed to load legal settings');
    } finally {
        loading.value = false;
    }
}

const renderedTerms = computed(() => {
    if (!legalSettings.value?.termsAndConditionsMarkdown) return null;
    const rawHtml = marked.parse(legalSettings.value.termsAndConditionsMarkdown) as string;
    return DOMPurify.sanitize(rawHtml);
});

onMounted(fetchLegalSettings);
</script>

<template>
  <div class="min-h-screen bg-slate-50 py-12 px-4">
    <div class="max-w-4xl mx-auto">
      <div class="mb-8 flex items-center gap-4">
        <Button
          icon="pi pi-arrow-left"
          severity="secondary"
          text
          rounded
          @click="$router.back()"
        />
        <h1 class="text-4xl font-black uppercase tracking-tighter text-slate-900 font-barlow">
          Terms & Conditions
        </h1>
      </div>

      <div class="phoenix-card p-8 md:p-12 bg-white">
        <div v-if="loading" class="flex justify-center py-12">
          <i class="pi pi-spin pi-spinner text-4xl text-primary" />
        </div>

        <div v-else-if="renderedTerms" class="markdown-content" v-html="renderedTerms"></div>

        <div v-else class="text-center py-12">
          <i class="pi pi-info-circle text-4xl text-slate-300 mb-4" />
          <p class="text-slate-500 italic">No terms and conditions have been defined yet.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.font-barlow {
  font-family: 'Barlow Condensed', sans-serif;
}

.markdown-content {
  line-height: 1.8;
  color: var(--text-secondary-color);

  :deep(h1) {
    @apply text-3xl font-black uppercase tracking-tight text-slate-900 mb-8 mt-12 first:mt-0 border-b-4 border-primary pb-2 inline-block;
    font-family: 'Barlow Condensed', sans-serif;
  }

  :deep(h2) {
    @apply text-xl font-bold uppercase tracking-wide text-slate-800 mb-4 mt-8;
    font-family: 'Barlow Condensed', sans-serif;
  }

  :deep(h3) {
    @apply text-lg font-bold text-slate-800 mb-3 mt-6;
  }

  :deep(p) {
    @apply mb-4;
  }

  :deep(ul) {
    @apply mb-6 ml-6 list-disc;
    li {
      @apply mb-2;
    }
  }

  :deep(strong) {
    @apply font-bold text-slate-900;
  }
}
</style>
