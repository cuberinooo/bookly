import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useTimeStore = defineStore('time', () => {
  const now = ref(new Date());
  const timer = ref<any>(null);

  function init() {
    if (timer.value) return;
    timer.value = setInterval(() => {
      now.value = new Date();
    }, 1000);
  }

  function destroy() {
    if (timer.value) {
      clearInterval(timer.value);
      timer.value = null;
    }
  }

  return {
    now,
    init,
    destroy
  };
});
