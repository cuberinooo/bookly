<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    count: number;
    limit: number;
}>();

const remaining = computed(() => Math.max(0, props.limit - props.count));
const progressWidth = computed(() => Math.max(0, Math.min(100, (props.count / props.limit) * 100)));
</script>

<template>
  <div class="trial-status-card">
    <div class="flex items-center gap-4 mb-4">
      <div class="status-icon">
        <i class="pi pi-bolt" />
      </div>
      <div>
        <h3 class="text-lg font-black uppercase tracking-tighter leading-tight">Trial Progress</h3>
        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Workout Credits</p>
      </div>
    </div>

    <div class="flex justify-between items-end mb-2">
      <span class="text-xs font-bold text-slate-400 pb-1"> {{ remaining }} LEFT</span>
    </div>

    <div class="progress-bar-bg">
      <div
        class="progress-bar-fill"
        :style="{ width: progressWidth + '%' }"
      />
    </div>

    <p class="mt-4 text-xs font-medium text-slate-600 leading-relaxed">
      Upgrade to a full membership to unlock unlimited bookings.
    </p>
  </div>
</template>

<style scoped lang="scss">
.trial-status-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);

    .status-icon {
        width: 40px;
        height: 40px;
        background: #fffbeb;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f59e0b;
        font-size: 1.25rem;
    }

    .progress-bar-bg {
        height: 6px;
        background: #f1f5f9;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: #f59e0b;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .upgrade-btn {
        background: #0f172a;
        border: none;
        font-weight: 800;
        letter-spacing: 0.05em;
        font-family: 'Barlow Condensed', sans-serif;
        &:hover { background: #1e293b; }
    }
}
</style>
