<script setup lang="ts">
import { ref, computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const props = defineProps<{
  content: string;
  placeholder?: string;
  title?: string;
}>();

const isExpanded = ref(false);

const renderedMarkdown = computed(() => {
  if (!props.content) return null;
  return DOMPurify.sanitize(marked.parse(props.content) as string);
});

function toggleExpand() {
  isExpanded.value = !isExpanded.value;
}
</script>

<template>
  <Panel header="Preview" toggleable :collapsed="true">
    <div  class="preview-content phoenix-card">
      <div v-if="renderedMarkdown" class="markdown-body" v-html="renderedMarkdown"></div>
      <div v-else class="preview-empty">
        <p class="italic text-slate-400">{{ placeholder || 'Nothing to preview yet...' }}</p>
      </div>
    </div>
  </Panel>
</template>

<style scoped lang="scss">
.markdown-preview-container {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  transition: all 0.3s ease;
}

.preview-content {
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  padding: 1.5rem;
  max-height: 500px;
  overflow-y: auto;
}

.preview-empty {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100px;
}

.markdown-body {
  line-height: 1.6;
  color: #1e293b;

  :deep(h1) {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
    color: var(--primary-color);
  }

  :deep(h2) {
    font-size: 1.25rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: var(--primary-color);
  }

  :deep(h3) {
    font-size: 1.1rem;
    font-weight: 700;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
  }

  :deep(p) {
    margin-bottom: 1rem;
  }

  :deep(ul), :deep(ol) {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
  }

  :deep(ul) {
    list-style-type: disc;
  }

  :deep(ol) {
    list-style-type: decimal;
  }

  :deep(li) {
    margin-bottom: 0.25rem;
  }

  :deep(code) {
    background: #e2e8f0;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9em;
  }

  :deep(pre) {
    background: #1e293b;
    color: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
    margin-bottom: 1rem;

    code {
      background: transparent;
      padding: 0;
      color: inherit;
    }
  }

  :deep(blockquote) {
    border-left: 4px solid #cbd5e1;
    padding-left: 1rem;
    margin-left: 0;
    margin-bottom: 1rem;
    color: #64748b;
    font-style: italic;
  }

  :deep(hr) {
    border: 0;
    border-top: 1px solid #e2e8f0;
    margin: 2rem 0;
  }

  :deep(a) {
    color: var(--primary-color);
    text-decoration: underline;
    font-weight: 600;
  }
}
</style>
