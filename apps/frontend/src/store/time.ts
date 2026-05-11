import { reactive, ref, onMounted, onUnmounted } from 'vue';

export const timeStore = reactive({
  now: new Date(),
  timer: null as any,

  init() {
    if (this.timer) return;
    this.timer = setInterval(() => {
      this.now = new Date();
    }, 1000);
  },

  destroy() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }
});
